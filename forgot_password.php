<?php
// forgot_password.php
// User enters their email → system sends a password reset link valid for 30 minutes.

include 'db.php';

require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';
require 'PHPMailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

define('ALERT_EMAIL_FROM', 'officialgray09@gmail.com');
define('SMTP_PASSWORD',    'vkyv fyhf neor ptfv');

$message = '';
$msgType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = 'Please enter a valid email address.';
        $msgType = 'error';
    } else {
        // Check if email exists
        $stmt = $conn->prepare("SELECT id, name FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            // Generate secure token
            $token   = bin2hex(random_bytes(32)); // 64-char hex string
            $expires = date('Y-m-d H:i:s', strtotime('+30 minutes'));

            // Save token to DB
            $conn->prepare("UPDATE users SET reset_token = ?, reset_expires_at = ? WHERE email = ?")
                 ->execute([$token, $expires, $email]);

            // Build reset link
            $resetLink = "http://localhost:8080/reset_password.php?token=$token";

            // Send email
            try {
                $mail = new PHPMailer(true);
                $mail->isSMTP();
                $mail->Host       = 'smtp.gmail.com';
                $mail->SMTPAuth   = true;
                $mail->Username   = ALERT_EMAIL_FROM;
                $mail->Password   = SMTP_PASSWORD;
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = 587;
                $mail->CharSet    = 'UTF-8';
                $mail->setFrom(ALERT_EMAIL_FROM, 'Streetlight Monitor');
                $mail->addAddress($email, $user['name']);
                $mail->isHTML(true);
                $mail->Subject = 'Password Reset — IoT Street Light System';
                $mail->Body    = "
                    <h3>Hello {$user['name']},</h3>
                    <p>We received a request to reset your password.</p>
                    <p>Click the button below to set a new password. This link expires in <b>30 minutes</b>.</p>
                    <p style='margin:24px 0'>
                        <a href='$resetLink'
                           style='background:#0b76ef;color:#fff;padding:12px 24px;border-radius:6px;text-decoration:none;font-weight:bold;'>
                           Reset My Password
                        </a>
                    </p>
                    <p>Or copy this link into your browser:<br><small>$resetLink</small></p>
                    <hr>
                    <p><small>If you did not request a password reset, ignore this email. Your password will not change.</small></p>
                ";
                $mail->AltBody = "Hello {$user['name']},\n\nReset your password here (valid 30 min):\n$resetLink\n\nIf you did not request this, ignore this email.";
                $mail->send();
                $message = "<svg style='width:14px;height:14px;vertical-align:middle;margin-right:4px' viewBox='0 0 24 24' fill='#155724'><path d='M9 16.17 4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z'/></svg> Reset link sent to <b>$email</b>. Check your inbox (and spam folder). Link expires in 30 minutes.";                $msgType = 'success';
            } catch (Exception $e) {
                $message = "<svg style='width:14px;height:14px;vertical-align:middle;margin-right:4px' viewBox='0 0 24 24' fill='%23721c24'><path d='M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z'/></svg> Could not send email: " . $mail->ErrorInfo;
                $msgType = 'error';
            }
        } else {
            $message = "<svg style='width:14px;height:14px;vertical-align:middle;margin-right:4px' viewBox='0 0 24 24' fill='%23721c24'><path d='M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z'/></svg> No account found with that email address. Please check and try again.";
            $msgType = 'error';
        }
    }
    $conn = null;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Forgot Password — iot based public light fault detection and reporting system</title>
  <style>
    * { box-sizing: border-box; font-family: Arial, sans-serif; }
    body { margin:0; background:#f4f4f9; display:flex; justify-content:center; align-items:center; min-height:100vh; }
    .container { background:#fff; padding:32px 28px; border-radius:10px; box-shadow:0 4px 16px rgba(0,0,0,0.1); width:100%; max-width:400px; }
    h1 { margin:0 0 6px; font-size:1.5rem; color:#0b76ef; }
    p.sub { margin:0 0 20px; color:#666; font-size:0.9rem; }
    input { width:100%; padding:10px 12px; margin:8px 0 16px; border:1px solid #ddd; border-radius:5px; font-size:1rem; }
    input:focus { outline:none; border-color:#0b76ef; }
    .btn { width:100%; padding:11px; background:#0b76ef; color:#fff; border:none; border-radius:5px; font-size:1rem; font-weight:bold; cursor:pointer; }
    .btn:hover { background:#0056b3; }
    .msg { padding:10px 14px; border-radius:5px; margin-bottom:16px; font-size:0.9rem; line-height:1.5; }
    .msg.success { background:#d4edda; color:#155724; border:1px solid #c3e6cb; }
    .msg.error   { background:#f8d7da; color:#721c24; border:1px solid #f5c6cb; }
    .back { display:block; text-align:center; margin-top:16px; color:#0b76ef; text-decoration:none; font-size:0.9rem; }
    .back:hover { text-decoration:underline; }
  </style>
</head>
<body>
<div class="container">
  <h1>Forgot Password</h1>
  <p class="sub">Enter your email and we'll send you a reset link.</p>

  <?php if ($message): ?>
    <div class="msg <?= $msgType ?>"><?= $message ?></div>
  <?php endif; ?>

  <?php if ($msgType !== 'success'): ?>  <form method="POST" action="">
    <label for="email">Email Address</label>
    <input type="email" id="email" name="email"
           placeholder="your@email.com"
           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
    <button type="submit" class="btn">Send Reset Link</button>
  </form>
  <?php endif; ?>

  <a href="login.php" class="back"><svg style="width:12px;height:12px;vertical-align:middle;margin-right:3px" viewBox="0 0 24 24" fill="currentColor"><path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/></svg>Back to Login</a>
</div>
</body>
</html>
