#include <TinyGPSPlus.h>
#include <WiFi.h>
#include <HTTPClient.h>

HardwareSerial GPS_Serial(1);
HardwareSerial GSM_Serial(2);

static const int GSM_RXPin = 16, GSM_TXPin = 17;
static const int RXPin = 22,     TXPin     = 23;
static const uint32_t GPSBaud = 115200;

double Latitude  = 0.0;
double Longitude = 0.0;
TinyGPSPlus gps;

// ── WiFi ──────────────────────────────────────────────────────────────────
const char* ssid     = "Gray";
const char* password = "Gray@123";

// ── Server endpoints ──────────────────────────────────────────────────────
const char* serverInsert  = "http://10.83.244.147:8080/insert_data.php";
const char* serverCommand = "http://10.83.244.147:8080/get_command.php";

// ── Pins ──────────────────────────────────────────────────────────────────
#define LDR_1    34
#define ledPin   18
#define LAMPS    32
#define CURR_SEN 33

// ── Device ID ─────────────────────────────────────────────────────────────
const char* device_id = "SL001";

// ── ACS712 calibration ────────────────────────────────────────────────────
float sensitivity     = 0.185;
float zeroCurrentVolt = 2.5;
float current         = 0.0;

// ── Thresholds ────────────────────────────────────────────────────────────
const float CURRENT_THRESHOLD = 0.10;   // A — below this = lamp not drawing power
const float OVERCURRENT_MAX   = 2.50;   // A — above this = short circuit / fire risk
const int   DARK_THRESHOLD    = 80;     // LDR 0-255 — below this = dark outside

// ── State ─────────────────────────────────────────────────────────────────
bool relayOn      = true;
int  failCount    = 0;   // consecutive low-current readings — must hit 3 before FAILED
int  currentPWM   = 255; // current brightness level (255 = full ON)

// ── Timing (non-blocking) ─────────────────────────────────────────────────
unsigned long lastCommandPoll = 0;   // last time we polled get_command.php
unsigned long lastDataPost    = 0;   // last time we posted sensor data
const unsigned long COMMAND_INTERVAL = 500;    // poll command every 500ms → fast response
const unsigned long DATA_INTERVAL    = 10000;  // post sensor data every 10 seconds

// ─────────────────────────────────────────────────────────────────────────
void setup() {
  Serial.begin(115200);
  GPS_Serial.begin(GPSBaud, SERIAL_8N1, RXPin, TXPin);
  GSM_Serial.begin(9600,    SERIAL_8N1, GSM_RXPin, GSM_TXPin);

  pinMode(LDR_1,    INPUT);
  pinMode(CURR_SEN, INPUT);
  pinMode(LAMPS,    OUTPUT);
  pinMode(ledPin,   OUTPUT);

  // LAMPS pin uses PWM for dimming (ESP32 core v3.x API)
  ledcAttach(LAMPS, 5000, 8);  // pin, 5kHz, 8-bit resolution (0-255)
  ledcWrite(LAMPS, 255);       // start at full brightness

  WiFi.begin(ssid, password);
  Serial.print("Connecting to WiFi...");
  while (WiFi.status() != WL_CONNECTED) { delay(1000); Serial.print("."); }
  Serial.println("\nConnected! IP: " + WiFi.localIP().toString());

  // Calibrate ACS712 zero-current
  long sum = 0;
  for (int i = 0; i < 100; i++) { sum += analogRead(CURR_SEN); delay(5); }
  zeroCurrentVolt = (sum / 100.0) * (3.3 / 4095.0);
  Serial.printf("Zero current voltage: %.3f V\n", zeroCurrentVolt);

  // Read last relay state from server on boot (peek — does not clear command)
  HTTPClient http;
  http.setTimeout(3000);
  String url = String(serverCommand) + "?device_id=" + String(device_id) + "&peek=1";
  http.begin(url);
  if (http.GET() == 200) {
    String body = http.getString();
    Serial.println("Boot command: " + body);
    if (body.indexOf("\"OFF\"") >= 0) {
      relayOn = false;
      digitalWrite(LAMPS, LOW);
      Serial.println("Boot: relay OFF");
    } else {
      relayOn = true;
      digitalWrite(LAMPS, HIGH);
      Serial.println("Boot: relay ON");
    }
  }
  http.end();
}

// ─────────────────────────────────────────────────────────────────────────
void loop() {
  unsigned long now = millis();

  // ── 1. Keep WiFi alive ────────────────────────────────────────────────
  if (WiFi.status() != WL_CONNECTED) {
    Serial.println("WiFi lost — reconnecting...");
    WiFi.reconnect();
    for (int i = 0; i < 10 && WiFi.status() != WL_CONNECTED; i++) delay(500);
    if (WiFi.status() == WL_CONNECTED) Serial.println("Reconnected!");
  }

  // ── 2. Poll command every 500ms — fast ON/OFF/dim response ───────────
  if (WiFi.status() == WL_CONNECTED && (now - lastCommandPoll >= COMMAND_INTERVAL)) {
    lastCommandPoll = now;
    HTTPClient http;
    http.setTimeout(1500);   // tight timeout — must not block data post
    String url = String(serverCommand) + "?device_id=" + String(device_id);
    http.begin(url);
    int code = http.GET();
    if (code == 200) {
      String body = http.getString();
      if (body.indexOf("\"ON\"") >= 0) {
        relayOn = true;
        int pwmIdx = body.indexOf("\"dim_pwm\":");
        if (pwmIdx >= 0) {
          currentPWM = body.substring(pwmIdx + 10).toInt();
          if (currentPWM <= 0 || currentPWM > 255) currentPWM = 255;
        }
        ledcWrite(LAMPS, currentPWM);
        Serial.printf(">> RELAY ON | PWM %d\n", currentPWM);
      } else if (body.indexOf("\"OFF\"") >= 0) {
        relayOn = false;
        ledcWrite(LAMPS, 0);
        Serial.println(">> RELAY OFF");
      }
    }
    http.end();
  }

  // ── 3. Read GPS (every loop iteration) ───────────────────────────────
  while (GPS_Serial.available()) gps.encode(GPS_Serial.read());
  if (gps.location.isValid()) {
    Latitude  = gps.location.lat();
    Longitude = gps.location.lng();
  }

  // ── 4. Read sensors and post data every 10 seconds ───────────────────
  if (now - lastDataPost >= DATA_INTERVAL) {
    lastDataPost = now;

    // Read current — average 20 samples to eliminate ACS712 noise
    long currentSum = 0;
    for (int i = 0; i < 20; i++) {
      currentSum += analogRead(CURR_SEN);
      delay(2);
    }
    int raw    = currentSum / 20;
    float adcV = raw * (3.3 / 4095.0);
    current    = abs((adcV - zeroCurrentVolt) / sensitivity);
    if (current < 0.05) current = 0.0;
    Serial.printf("Current: %.3f A\n", current);

    // Read brightness
    int ldrRaw     = analogRead(LDR_1);
    int brightness = map(ldrRaw, 0, 4095, 0, 255);
    Serial.printf("Brightness: %d\n", brightness);

    // Expected minimum brightness per dim level
    int minBrightness = 20;
    if (currentPWM >= 200)      minBrightness = 80;
    else if (currentPWM >= 100) minBrightness = 40;
    else                        minBrightness = 20;

    String status;
    if (relayOn) {
      if (current >= OVERCURRENT_MAX) {
        relayOn = false;
        ledcWrite(LAMPS, 0);
        digitalWrite(ledPin, HIGH);
        status    = "OVERCURRENT";
        failCount = 0;
        Serial.printf("!! OVERCURRENT %.3f A — relay cut OFF !!\n", current);      } else if (current > CURRENT_THRESHOLD && brightness >= minBrightness) {
        if (failCount > 0) Serial.println("Lamp recovered — back to ON");
        status    = "ON";
        failCount = 0;
        digitalWrite(ledPin, LOW);
      } else {
        failCount++;
        if (failCount >= 3) {
          status = "FAILED";
          digitalWrite(ledPin, HIGH);
          if (current <= CURRENT_THRESHOLD)
            Serial.printf("FAILED: low current %.3f A\n", current);
          else
            Serial.printf("FAILED: brightness %d below min %d for PWM %d\n", brightness, minBrightness, currentPWM);
        } else {
          status = "ON";
          Serial.printf("Warning %d/3 — current=%.3fA brightness=%d minBrightness=%d\n",
                        failCount, current, brightness, minBrightness);
        }
      }
    } else {
      status    = "OFF";
      failCount = 0;
      digitalWrite(ledPin, LOW);
    }
    Serial.printf("Relay: %s | Status: %s\n", relayOn ? "ON" : "OFF", status.c_str());

    // POST sensor data
    if (WiFi.status() == WL_CONNECTED) {
      HTTPClient http;
      http.setTimeout(5000);
      http.begin(serverInsert);
      http.addHeader("Content-Type", "application/x-www-form-urlencoded");

      String post = "device_id=" + String(device_id)
                  + "&current="  + String(current, 3)
                  + "&ldr="      + String(brightness)
                  + "&relay="    + String(relayOn ? "ON" : "OFF")
                  + "&status="   + status;

      if (gps.location.isValid()) {
        post += "&latitude="  + String(Latitude,  6)
              + "&langitude=" + String(Longitude, 6);
      }

      int respCode = http.POST(post);
      if (respCode == 200) {
        Serial.println("Data posted OK");
      } else {
        Serial.println("POST error: " + String(respCode));
      }
      http.end();
    }
  }
  // No delay() here — loop runs as fast as possible, timing controlled by millis()
}

// ── GSM SMS helpers ───────────────────────────────────────────────────────
void sendSMS(String message) {
  Serial.println("Sending SMS...");
  delay(1000);
  GSM_Serial.println("AT");                          updateSerial();
  GSM_Serial.println("AT+CMGF=1");                   updateSerial();
  GSM_Serial.println("AT+CMGS=\"+250788410065\"");   updateSerial();
  GSM_Serial.print(message);                         updateSerial();
  GSM_Serial.write(26);
}

void updateSerial() {
  delay(500);
  while (Serial.available())     GSM_Serial.write(Serial.read());
  while (GSM_Serial.available()) Serial.write(GSM_Serial.read());
}
