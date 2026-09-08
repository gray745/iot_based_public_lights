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
const char* serverInsert  = "http://10.69.242.147:8080/insert_data.php";
const char* serverCommand = "http://10.69.242.147:8080/get_command.php";

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
int  loopCount    = 0;
int  failCount    = 0;   // consecutive low-current readings — must hit 3 before FAILED
int  currentPWM   = 255; // current brightness level (255 = full ON)

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

  // ── 1. Keep WiFi alive ────────────────────────────────────────────────
  if (WiFi.status() != WL_CONNECTED) {
    Serial.println("WiFi lost — reconnecting...");
    WiFi.reconnect();
    for (int i = 0; i < 10 && WiFi.status() != WL_CONNECTED; i++) delay(500);
    if (WiFi.status() == WL_CONNECTED) Serial.println("Reconnected!");
  }

  // ── 2. Poll command EVERY loop (every 1 second) ───────────────────────
  // This is what makes ON/OFF respond almost instantly
  if (WiFi.status() == WL_CONNECTED) {
    HTTPClient http;
    http.setTimeout(2000);   // short timeout — don't block long
    String url = String(serverCommand) + "?device_id=" + String(device_id);
    http.begin(url);
    int code = http.GET();
    if (code == 200) {
      String body = http.getString();
      if (body.indexOf("\"ON\"") >= 0) {
        relayOn = true;
        // Extract dim_pwm value from JSON response
        int pwmIdx = body.indexOf("\"dim_pwm\":");
        if (pwmIdx >= 0) {
          currentPWM = body.substring(pwmIdx + 10).toInt();
          if (currentPWM <= 0 || currentPWM > 255) currentPWM = 255;
        }
        ledcWrite(LAMPS, currentPWM);  // apply brightness
        Serial.printf(">> RELAY ON | PWM %d\n", currentPWM);
      } else if (body.indexOf("\"OFF\"") >= 0) {
        relayOn = false;
        ledcWrite(LAMPS, 0);           // off
        Serial.println(">> RELAY OFF");
      }
    }
    http.end();
  }

  // ── 3. Read GPS (every loop) ─────────────────────────────────────────
  while (GPS_Serial.available()) gps.encode(GPS_Serial.read());
  if (gps.location.isValid()) {
    Latitude  = gps.location.lat();
    Longitude = gps.location.lng();
  }

  // ── 4. Read sensors and post data every 10 seconds ───────────────────
  loopCount++;
  if (loopCount >= 10) {
    loopCount = 0;

    // Read current — average 20 samples to eliminate ACS712 noise
    long currentSum = 0;
    for (int i = 0; i < 20; i++) {
      currentSum += analogRead(CURR_SEN);
      delay(2);
    }
    int raw    = currentSum / 20;
    float adcV = raw * (3.3 / 4095.0);
    current    = (adcV - zeroCurrentVolt) / sensitivity;
    if (abs(current) < 0.05) current = 0.0;
    Serial.printf("Current: %.3f A\n", current);

    // Read brightness
    int ldrRaw     = analogRead(LDR_1);
    int brightness = map(ldrRaw, 0, 4095, 0, 255);
    Serial.printf("Brightness: %d\n", brightness);

    // Determine status
    // OVERCURRENT = short circuit, wiring fire, overload — cut power immediately
    // FAILED      = relay ON but:
    //               a) current too low (bulb dead/fuse blown), OR
    //               b) brightness below expected minimum for current dim level
    //                  (lamp not emitting enough light — below DIM_LOW threshold)
    // ON          = relay ON and current + brightness OK → working
    // OFF         = relay OFF → intentionally off

    // Expected minimum brightness per dim level
    // DIM_LOW (PWM 64)=25%  → brightness should be at least 20
    // DIM_MEDIUM (PWM 128)=50% → brightness should be at least 40
    // DIM_HIGH (PWM 255)=100% → brightness should be at least 80
    int minBrightness = 20; // default for DIM_LOW
    if (currentPWM >= 200)      minBrightness = 80;  // HIGH
    else if (currentPWM >= 100) minBrightness = 40;  // MEDIUM
    else                        minBrightness = 20;  // LOW

    String status;
    if (relayOn) {
      if (abs(current) >= OVERCURRENT_MAX) {
        // DANGER — cut power immediately to prevent fire
        relayOn = false;
        ledcWrite(LAMPS, 0);
        digitalWrite(ledPin, HIGH);
        status    = "OVERCURRENT";
        failCount = 0;
        Serial.printf("!! OVERCURRENT %.3f A — relay cut OFF !!\n", current);
      } else if (abs(current) > CURRENT_THRESHOLD && brightness >= minBrightness) {
        // Current flowing AND brightness acceptable → lamp recovered / working normally
        if (failCount > 0) Serial.println("Lamp recovered — back to ON");
        status    = "ON";
        failCount = 0;  // reset immediately on recovery
        digitalWrite(ledPin, LOW);
      } else {
        // Either current too low OR brightness below expected minimum
        failCount++;
        if (failCount >= 3) {   // 3 consecutive bad readings = 30 seconds
          status = "FAILED";
          digitalWrite(ledPin, HIGH);
          if (abs(current) <= CURRENT_THRESHOLD)
            Serial.printf("FAILED: low current %.3f A\n", current);
          else
            Serial.printf("FAILED: brightness %d below min %d for PWM %d\n", brightness, minBrightness, currentPWM);
        } else {
          status = "ON";        // benefit of doubt while counter builds
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

    // POST to server
    if (WiFi.status() == WL_CONNECTED) {
      HTTPClient http;
      http.setTimeout(5000);
      http.begin(serverInsert);
      http.addHeader("Content-Type", "application/x-www-form-urlencoded");

      String post = "device_id=" + String(device_id)
                  + "&current="  + String(current, 3)
                  + "&ldr="      + String(brightness)
                  + "&relay="    + String(relayOn ? "ON" : "OFF")
                  + "&status="   + status;  // send actual status so server knows OVERCURRENT even when relay is now OFF

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

  delay(1000);  // 1 second per loop — commands checked every ~1 second
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
