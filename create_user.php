<?php
include 'db.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name     = htmlspecialchars($_POST['name']);
    $email    = htmlspecialchars($_POST['email']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $role     = htmlspecialchars($_POST['role']);

    try {
        $stmt = $conn->prepare("INSERT INTO users (name, email, password, role) VALUES (:name, :email, :password, :role)");
        $stmt->execute([':name'=>$name, ':email'=>$email, ':password'=>$password, ':role'=>$role]);

        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'officialgray09@gmail.com';
            $mail->Password   = 'vkyv fyhf neor ptfv';
            $mail->SMTPSecure = 'tls';
            $mail->Port       = 587;
            $mail->setFrom('officialgray09@gmail.com', 'Streetlight System');
            $mail->addAddress($email, $name);
            $mail->isHTML(true);
            $mail->Subject = 'Welcome to Streetlight Monitoring System';
            $mail->Body    = "<h3>Hello $name,</h3><p>Your account has been created.</p><p><b>Role:</b> $role</p><p><a href='http://localhost:8080/login.php'>Click here to log in</a></p>";
            $mail->send();
        } catch (Exception $e) { /* silent */ }

        header("Location: login.php");
        exit;
    } catch (PDOException $e) {
        $message = "<p style='color:red;'>❌ " . $e->getMessage() . "</p>";
    }
}
$conn = null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Create User</title>
  <style>
    body {
      font-family: Arial, sans-serif;
      background: #f4f4f9;
      display: flex;
      justify-content: center;
      align-items: center;
      height: 100vh;
      margin: 0;
    }
    .container {
      padding: 20px;
      background: #fff;
      border-radius: 10px;
      box-shadow: 0 4px 6px rgba(0,0,0,0.1);
      text-align: center;
      max-width: 400px;
      width: 100%;
    }
    h1 { margin-bottom: 20px; }
    input, select {
      width: 100%;
      box-sizing: border-box;
      padding: 10px;
      margin: 10px 0;
      border: 1px solid #ddd;
      border-radius: 5px;
    }
    .button {
      padding: 10px 20px;
      background: #007bff;
      color: #fff;
      border: none;
      border-radius: 5px;
      cursor: pointer;
      font-size: 1rem;
      width: 100%;
      margin-top: 8px;
    }
    .button:hover { background: #0056b3; }
    /* Eye toggle wrapper */
    .pw-wrap {
      position: relative;
      margin: 10px 0;
    }
    .pw-wrap input {
      padding-right: 42px;
      margin: 0;
    }
    .pw-wrap .eye {
      position: absolute;
      right: 10px;
      top: 50%;
      transform: translateY(-50%);
      cursor: pointer;
      font-size: 1.1rem;
      color: #888;
      user-select: none;
    }
    .match-msg {
      font-size: 0.82rem;
      text-align: left;
      margin: -4px 0 6px;
      min-height: 18px;
    }
  </style>
</head>
<body>
  <div class="container">
    <h1>Create User</h1>
    <?php if ($message) echo $message; ?>
    <form method="post" action="" onsubmit="return validateForm()">
      <input type="text"  name="name"  placeholder="Full Name"      required>
      <input type="email" name="email" placeholder="Email Address"   required>

      <div class="pw-wrap">
        <input type="password" id="pw1" name="password" placeholder="Password" required>
        <span class="eye" onclick="toggleEye('pw1', this)">👁️</span>
      </div>

      <div class="pw-wrap">
        <input type="password" id="pw2" placeholder="Confirm Password" required oninput="liveMatch()">
        <span class="eye" onclick="toggleEye('pw2', this)">👁️</span>
      </div>
      <div class="match-msg" id="matchMsg"></div>

      <select name="role" required>
        <option value="">Select Role</option>
        <option value="admin">Admin</option>
        <option value="user">User</option>
      </select>
      <button type="submit" class="button">Create User</button>
    </form>
  </div>

  <script>
    function toggleEye(id, icon) {
      const inp = document.getElementById(id);
      inp.type  = inp.type === 'password' ? 'text' : 'password';
      icon.textContent = inp.type === 'password' ? '👁️' : '🙈';
    }

    function liveMatch() {
      const pw1 = document.getElementById('pw1').value;
      const pw2 = document.getElementById('pw2').value;
      const msg = document.getElementById('matchMsg');
      if (!pw2) { msg.textContent = ''; return; }
      if (pw1 === pw2) {
        msg.textContent = '✓ Passwords match';
        msg.style.color = '#28a745';
      } else {
        msg.textContent = '✗ Passwords do not match';
        msg.style.color = '#dc3545';
      }
    }

    function validateForm() {
      const pw1 = document.getElementById('pw1').value;
      const pw2 = document.getElementById('pw2').value;
      if (pw1 !== pw2) {
        alert('Passwords do not match.');
        return false;
      }
      return true;
    }
  </script>
</body>
</html>
