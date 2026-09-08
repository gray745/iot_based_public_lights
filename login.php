<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - iot based public light fault detection and reporting system 2026</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f9;
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
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            text-align: center;
            max-width: 400px;
            width: 100%;
        }
        h1 {
            margin-bottom: 20px;
        }
        input {
            width: calc(100% - 20px);
            padding: 10px;
            margin: 10px 0;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        .pw-wrap {
            position: relative;
            margin: 10px 0;
        }
        .pw-wrap input {
            width: 100%;
            box-sizing: border-box;
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
        .button {
            padding: 10px 20px;
            background-color: #007bff;
            color: #fff;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            font-size: 1rem;
        }
        .button:hover {
            background-color: #0056b3;
        }
        .message {
            margin-top: 20px;
            font-size: 0.9rem;
            color: #555;
        }
        .link {
            color: #007bff;
            text-decoration: none;
        }
        .link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Login</h1>
        <form method="post" action="authenticate.php">
            <input type="email" name="email" placeholder="Email Address" required>
            <div class="pw-wrap">
                <input type="password" id="pw" name="password" placeholder="Password" required>
                <span class="eye" onclick="togglePw()">👁️</span>
            </div>
            <button type="submit" class="button">Login</button>
        </form>
        <div class="message">
            Don't have an account? <a href="create_user.php" class="link">Create an account</a>
            <br><a href="forgot_password.php" class="link">Forgot your password?</a>
        </div>
    </div>
    <script>
        function togglePw() {
            const inp  = document.getElementById('pw');
            const eye  = document.querySelector('.eye');
            if (inp.type === 'password') {
                inp.type = 'text';
                eye.textContent = '🙈';
            } else {
                inp.type = 'password';
                eye.textContent = '👁️';
            }
        }
    </script>
</body>
</html>
