<?php
// sms_helper.php
// SMS sender using K-SMS (Esicia) API — https://sms.esicia.africa/
// success === 0 means sent OK (not HTTP 200 alone)

define('KSMS_API_KEY',  'c6841118350de9af050cebc25c418f392a9746d7');
define('KSMS_SENDER',   'CUR MIS');
define('KSMS_ENDPOINT', 'https://sms.esicia.africa/');
define('SMS_TO',        '+250784910745');

/**
 * Format a phone number to 250XXXXXXXXX (no +, no spaces).
 * Accepts: +250788410065, 0788410065, 788410065, 250788410065
 */
function formatPhoneRwanda(string $phone): string {
    $p = preg_replace('/\D/', '', $phone); // digits only
    if (str_starts_with($p, '250') && strlen($p) === 12) return $p;  // already correct
    if (str_starts_with($p, '0')   && strlen($p) === 10) return '250' . substr($p, 1);
    if (strlen($p) === 9)                                 return '250' . $p;
    if (str_starts_with($p, '07')  && strlen($p) === 10) return '250' . substr($p, 1);
    return $p; // return as-is and let API validate
}

/**
 * Send an SMS via K-SMS (Esicia).
 *
 * @param string $to      Recipient — any common Rwanda format
 * @param string $message Message text (max 160 chars)
 * @return array ['sent'=>bool, 'code'=>int, 'response'=>string, 'error'=>string]
 */
function sendKSMS(string $to, string $message): array {
    $phone = formatPhoneRwanda($to);

    // Validate formatted number
    if (!preg_match('/^250[0-9]{9}$/', $phone)) {
        return ['sent' => false, 'code' => 0, 'response' => '', 'error' => "Invalid phone: $phone"];
    }

    $payload = json_encode([
        'sender'  => KSMS_SENDER,
        'phone'   => $phone,
        'message' => substr($message, 0, 160), // enforce 160-char limit
    ]);

    $ch = curl_init(KSMS_ENDPOINT);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . KSMS_API_KEY,
        ],
        CURLOPT_POSTFIELDS => $payload,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    // curl_close() is a no-op since PHP 8.0 — omitted intentionally

    $decoded = json_decode($response, true);

    // K-SMS: success === 0 means OK
    $sent = isset($decoded['success']) && $decoded['success'] === 0;

    // Map error codes to readable messages
    $errorCodes = [
        600 => 'User not found',
        601 => 'Unauthorized IP (whitelist your server IP)',
        602 => 'Invalid API key',
        603 => 'IP blacklisted',
        604 => 'Account suspended',
        605 => 'Insufficient balance',
        606 => 'Invalid phone number',
        607 => 'Destination not routed',
        608 => 'Invalid numeric sender ID',
        609 => 'Invalid alpha sender ID (must be 1-11 chars)',
    ];

    $errorMsg = '';
    if (!$sent) {
        $code     = $decoded['success'] ?? null;
        $errorMsg = $errorCodes[$code] ?? ($decoded['message'] ?? 'Unknown error');
    }

    return [
        'sent'     => $sent,
        'code'     => $httpCode,
        'response' => $response,
        'error'    => $errorMsg,
    ];
}
?>
