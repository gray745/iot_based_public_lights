<?php
// reset_password.php
// User clicks link from email → enters new password → saved to DB.

include 'db.php';

$token   = trim($_GET['token'] ?? $_POST['token'] ?? '');
$message = '';
$msgType = '';
$valid   = false;
$user    = null;

// Validate token
if ($token) {
    $stmt = $conn->prepare("
        SELECT id, name, email FROM users
        WHERE reset_token = ?
          AND reset_expires_at > NOW()
    ");
    $stmt->execute([$token]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    $valid = (bool)$user;
}

if (!$token || !$valid) {
    $message = "<svg style='width:14px;height:14px;vertical-align:middle;margin-right:4px' viewBox='0 0 24 24' fill='#721c24'><path d='M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z'/></svg> This reset link is invalid or has expired. Please request a new one.";
    $msgType = 'error';
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $valid) {
    $password  = $_POST['password']  ?? '';
    $password2 = $_POST['password2'] ?? '';

    if (strlen($password) < 6) {
        $message = "<svg style='width:14px;height:14px;vertical-align:middle;margin-right:4px' viewBox='0 0 24 24' fill='#721c24'><path d='M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z'/></svg> Password must be at least 6 characters.";
        $msgType = 'error';
    } elseif ($password !== $password2) {
        $message = "<svg style='width:14px;height:14px;vertical-align:middle;margin-right:4px' viewBox='0 0 24 24' fill='#721c24'><path d='M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z'/></svg> Passwords do not match.";
        $msgType = 'error';
    } else {
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        // Save new password and clear token
        $conn->prepare("
            UPDATE users
            SET password = ?, reset_token = NULL, reset_expires_at = NULL
            WHERE id = ?
        ")->execute([$hashed, $user['id']]);

        $conn = null;
        $message = "<svg style='width:14px;height:14px;vertical-align:middle;margin-right:4px' viewBox='0 0 24 24' fill='#155724'><path d='M9 16.17 4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z'/></svg> Password updated successfully!";
        $msgType = 'success';
        $valid   = false; // hide form
    }
}

if ($conn) $conn = null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Reset Password — iot based public light fault detection and reporting system</title>
  <style>
    * { box-sizing: border-box; font-family: Arial, sans-serif; }
    body { margin:0; background:#f4f4f9; display:flex; justify-content:center; align-items:center; min-height:100vh; }
    .container { background:#fff; padding:32px 28px; border-radius:10px; box-shadow:0 4px 16px rgba(0,0,0,0.1); width:100%; max-width:400px; }
    h1 { margin:0 0 6px; font-size:1.5rem; color:#0b76ef; }
    p.sub { margin:0 0 20px; color:#666; font-size:0.9rem; }
    label { font-size:0.9rem; color:#444; }
    input { width:100%; padding:10px 12px; margin:6px 0 14px; border:1px solid #ddd; border-radius:5px; font-size:1rem; }
    input:focus { outline:none; border-color:#0b76ef; }
    .btn { width:100%; padding:11px; background:#0b76ef; color:#fff; border:none; border-radius:5px; font-size:1rem; font-weight:bold; cursor:pointer; }
    .btn:hover { background:#0056b3; }
    .msg { padding:10px 14px; border-radius:5px; margin-bottom:16px; font-size:0.9rem; line-height:1.5; }
    .msg.success { background:#d4edda; color:#155724; border:1px solid #c3e6cb; }
    .msg.error   { background:#f8d7da; color:#721c24; border:1px solid #f5c6cb; }
    .back { display:block; text-align:center; margin-top:16px; color:#0b76ef; text-decoration:none; font-size:0.9rem; }
    .back:hover { text-decoration:underline; }
    .strength { height:4px; border-radius:2px; margin:-10px 0 14px; transition:0.3s; }
    .hint { font-size:0.78rem; color:#888; margin:-10px 0 14px; }
  </style>
</head>
<body>
<div class="container">
  <h1>Reset Password</h1>

  <?php if ($user && $valid): ?>
    <p class="sub">Hello <b><?= htmlspecialchars($user['name']) ?></b>, enter your new password below.</p>
  <?php else: ?>
    <p class="sub">Set a new password for your account.</p>
  <?php endif; ?>

  <?php if ($message): ?>
    <div class="msg <?= $msgType ?>"><?= $message ?></div>
  <?php endif; ?>

  <?php if ($msgType === 'success'): ?>
    <a href="login.php" class="btn" style="display:block;text-align:center;text-decoration:none;padding:11px;">
      Go to Login
    </a>
  <?php elseif ($valid): ?>
  <form method="POST" action="" onsubmit="return validateForm()">
    <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">

    <label for="password">New Password</label>
    <input type="password" id="password" name="password"
           placeholder="Minimum 6 characters" required
           oninput="checkStrength(this.value)">
    <div class="strength" id="strengthBar"></div>
    <div class="hint" id="strengthHint"></div>

    <label for="password2">Confirm New Password</label>
    <input type="password" id="password2" name="password2"
           placeholder="Repeat your password" required>

    <button type="submit" class="btn">Update Password</button>
  </form>
  <?php else: ?>
    <a href="forgot_password.php" class="back">Request a new reset link</a>
  <?php endif; ?>

  <a href="login.php" class="back"><svg style="width:12px;height:12px;vertical-align:middle;margin-right:3px" viewBox="0 0 24 24" fill="currentColor"><path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/></svg>Back to Login</a>
</div>

<script>
function checkStrength(val) {
  const bar  = document.getElementById('strengthBar');
  const hint = document.getElementById('strengthHint');
  if (!val) { bar.style.width='0'; bar.style.background=''; hint.textContent=''; return; }
  let score = 0;
  if (val.length >= 6)  score++;
  if (val.length >= 10) score++;
  if (/[A-Z]/.test(val)) score++;
  if (/[0-9]/.test(val)) score++;
  if (/[^a-zA-Z0-9]/.test(val)) score++;
  const levels = [
    { w:'20%',  bg:'#dc3545', t:'Very weak' },
    { w:'40%',  bg:'#fd7e14', t:'Weak' },
    { w:'60%',  bg:'#ffc107', t:'Fair' },
    { w:'80%',  bg:'#28a745', t:'Strong' },
    { w:'100%', bg:'#155724', t:'Very strong' },
  ];
  const l = levels[Math.min(score, 4)];
  bar.style.width = l.w; bar.style.background = l.bg;
  hint.textContent = l.t;
}

function validateForm() {
  const p1 = document.getElementById('password').value;
  const p2 = document.getElementById('password2').value;
  if (p1.length < 6) { alert('Password must be at least 6 characters.'); return false; }
  if (p1 !== p2)     { alert('Passwords do not match.');                  return false; }
  return true;
}
</script>
</body>
</html>
