<?php
session_start();
require_once '../db/db.php';
require_once '../db/auth.php';

// Redirect if already logged in
if (isset($_SESSION['user'])) {
    header("Location: admin/dashboard.php");
    exit();
}

$error = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($email) || empty($password)) {
        $error = "All fields are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email address.";
    } elseif (!preg_match('/^(?=.*[a-zA-Z])(?=.*\d).{6,}$/', $password)) {
        $error = "Password must be at least 6 characters and include a letter and a number.";
    } else {
        $stmt = $con->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res->num_rows > 0) {
            $error = "Email is already in use.";
        } else {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $role_id = 1;

            $stmt = $con->prepare("INSERT INTO users (username, email, password, role_id) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("sssi", $username, $email, $hashedPassword, $role_id);

            if ($stmt->execute()) {
                $_SESSION['user'] = [
                    'id' => $stmt->insert_id,
                    'username' => $username,
                    'email' => $email,
                    'role_id' => $role_id
                ];
                header("Location: subscription.php");
                exit();
            } else {
                $error = "Failed to create account.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Register</title>
    <style>
        * { box-sizing: border-box; }
        html, body {
            margin: 0;
            padding: 0;
            overflow-x: hidden;
            font-family: Arial, sans-serif;
            background: #fff;
            color: #000;
        }

        .register-container {
            max-width: 400px;
            margin: 100px auto;
            background-color: rgba(255, 255, 255, 0.95);
            padding: 30px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            border-radius: 10px;
            border: 1px solid #e0e0e0;
        }

        .register-container h2 {
            text-align: center;
            margin-bottom: 20px;
            color: #007BFF;
        }

        .register-container label {
            display: block;
            margin-bottom: 5px;
            color: #333;
        }

        .register-container input {
            width: 100%;
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 5px;
            border: 1px solid #ccc;
            font-size: 14px;
        }

        .register-container button {
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

        .register-container button:hover {
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

        .register-container p {
            text-align: center;
            margin-top: 15px;
        }

        .register-container a {
            color: #007BFF;
            text-decoration: none;
        }

        .register-container a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="register-container">
        <h2>Create Account</h2>

        <?php if ($error): ?>
            <div class="message error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST">
            <label for="username">Username:</label>
            <input type="text" name="username" id="username" required>

            <label for="email">Email:</label>
            <input type="email" name="email" id="email" required>

            <label for="password">Password:</label>
            <input type="password" name="password" id="password" required>

            <button type="submit">Register</button>
        </form>

        <p>Already have an account? <a href="login.php">Login here</a></p>
    </div>
</body>
</html>
