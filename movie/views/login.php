<?php
session_start();

require_once '../db/db.php';
require_once '../db/auth.php';

$error_message = false;
$success_message = false;
$message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    $response = login($username, $password);

    if ($response['success']) {
        $_SESSION['user'] = $response['user']; 

        $success_message = true;
        $message = "Login successful!";


        if ($response['user']['role_id'] == 2) {
            header("Location: ../views/index.php");
        } else {
            header("Location: ../views/index.php");
        }
        exit;
    } else {
        $error_message = true;
        $message = $response['message'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login</title>
    <style>
       
        * {
            box-sizing: border-box;
        }

        html, body {
            margin: 0;
            padding: 0;
            overflow-x: hidden;
            font-family: Arial, sans-serif;
            background: #fff;
            color: #000;
        }

        .section {
            padding: 40px;
        }

        .login-container {
            max-width: 400px;
            margin: 100px auto;
            background-color: rgba(255, 255, 255, 0.95);
            padding: 30px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            border-radius: 10px;
            border: 1px solid #e0e0e0;
        }

        .login-container h2 {
            text-align: center;
            margin-bottom: 20px;
            color: #007BFF;
        }

        .login-container label {
            display: block;
            margin-bottom: 5px;
            color: #333;
        }

        .login-container input {
            width: 100%;
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 5px;
            border: 1px solid #ccc;
            font-size: 14px;
        }

        .login-container button {
            width: 100%;
            padding: 10px;
            border: none;
            background: #007BFF;
            color: white;
            font-weight: bold;
            border-radius: 5px;
            cursor: pointer;
            transition: background 0.3s;
        }

        .login-container button:hover {
            background: #0056b3;
        }

        .message {
            text-align: center;
            margin-bottom: 15px;
            font-weight: bold;
        }

        .message.error {
            color: red;
        }

        .message.success {
            color: green;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <h2>Login</h2>

        <?php if ($error_message): ?>
            <div class="message error"><?= htmlspecialchars($message) ?></div>
        <?php elseif ($success_message): ?>
            <div class="message success"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <form action="login.php" method="post">
            <label for="username">Email ou Username:</label>
            <input type="text" name="username" id="username" required>

            <label for="password">Palavra-passe:</label>
            <input type="password" name="password" id="password" required>

            <button type="submit">Entrar</button>
        </form>
        <div style="text-align:center; margin-top:15px;">
            <span>Don't have an account? <a href="register.php">Register here</a></span>
        </div>
    </div>
</body>
</html>