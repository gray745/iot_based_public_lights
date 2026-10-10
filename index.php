<?php
// Start session if needed
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>iot based public light fault detection and reporting system</title>
  <style>
    * {
      box-sizing: border-box;
      font-family: Arial, Helvetica, sans-serif;
    }
    body {
      margin: 0;
      background: #f4f7fb;
      color: #222;
      display: flex;
      flex-direction: column;
      min-height: 100vh;
    }
    header {
      background: #0b76ef;
      color: #fff;
      padding: 16px 30px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      box-shadow: 0 3px 8px rgba(0,0,0,0.1);
    }
    header h1 {
      margin: 0;
      font-size: 1.4rem;
    }
    nav a {
      color: #fff;
      text-decoration: none;
      margin-left: 20px;
      font-weight: bold;
    }
    nav a:hover {
      text-decoration: underline;
    }
    main {
      flex: 1;
      display: flex;
      flex-direction: column;
      justify-content: center;
      align-items: center;
      text-align: center;
      padding: 40px 20px;
    }
    main h2 {
      font-size: 2rem;
      margin-bottom: 20px;
      color: #0b76ef;
    }
    main p {
      max-width: 700px;
      line-height: 1.6;
      margin-bottom: 30px;
      font-size: 1.1rem;
    }
    .buttons a {
      display: inline-block;
      margin: 8px;
      padding: 12px 24px;
      border-radius: 6px;
      text-decoration: none;
      font-weight: bold;
      transition: background 0.3s;
    }
    .btn-login {
      background: #0b76ef;
      color: #fff;
    }
    .btn-login:hover {
      background: #0056b3;
    }
    .btn-register {
      background: #fff;
      color: #0b76ef;
      border: 2px solid #0b76ef;
    }
    .btn-register:hover {
      background: #0b76ef;
      color: #fff;
    }
    footer {
      text-align: center;
      padding: 15px;
      background: #fff;
      box-shadow: 0 -2px 5px rgba(0,0,0,0.05);
    }
  </style>
</head>
<body>

<header>
  <h1>iot based public light fault detection and reporting system</h1>
  <nav>
    <a href="login.php">Login</a>
    <a href="create_user.php">Register</a>
  </nav>
</header>

<main>
  <h2>iot based public light fault detection and reporting system</h2>
  <p>
    This system continuously iot based public light fault detection and reporting system that detect current and brightness. 
    If any lamp fails to light when expected, the system automatically sends an SMS alert to the maintenance team, 
    ensuring reliable and energy-efficient street lighting.
  </p>
  <div class="buttons">
    <a href="login.php" class="btn-login">Login</a>
    <a href="create_user.php" class="btn-register">Create Account</a>
  </div>
</main>

<footer>
  <small>© <?php echo date("Y"); ?> iot based public light fault detection and reporting system — Powered by Safari Gray</small>
</footer>

</body>
</html>
