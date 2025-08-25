<?php
session_start();
if (isset($_SESSION['username'])) {
    header("Location: index.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Catat Cepat - Login</title>
    <style>
        :root {
            --cc-burgundy: #3B1A1F;
            --cc-dark-red: #772F1A;
            --cc-rusty-orange: #A23B00;
            --cc-golden-amber: #D68C45;
            --cc-pale-yellow: #ECE170;
            --cc-white: #ffffff;
            --cc-light: #f8f9fa;
            --cc-gray: #6c757d;
            --cc-dark-text: #222222;
            --cc-error-red: #dc3545;
            --transition-speed: 0.3s;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Arial', sans-serif;
        }

        body {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background-color: var(--cc-light);
        }

        .container {
            display: flex;
            width: 800px;
            height: 500px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .login-section {
            width: 50%;
            background-color: var(--cc-white);
            padding: 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .welcome-section {
            width: 50%;
            background-color: var(--cc-rusty-orange);
            color: var(--cc-white);
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            padding: 40px;
        }

        .login-section h2 {
            text-align: center;
            margin-bottom: 30px;
            color: var(--cc-dark-text);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .login-icon {
            width: 40px;
            height: 40px;
            margin-right: 10px;
        }

        .input-group {
            margin-bottom: 20px;
        }

        .input-group input {
            width: 100%;
            padding: 10px;
            border: 1px solid var(--cc-gray);
            border-radius: 4px;
            font-size: 16px;
        }

        .signin-btn {
            width: 100%;
            padding: 12px;
            background-color: var(--cc-rusty-orange);
            color: var(--cc-white);
            border: none;
            border-radius: 4px;
            font-size: 16px;
            cursor: pointer;
            transition: background-color var(--transition-speed);
        }

        .signin-btn:hover {
            background-color: var(--cc-dark-red);
        }

        .welcome-section h3 {
            margin-bottom: 20px;
            font-size: 24px;
        }

        .welcome-section p {
            margin-bottom: 20px;
            line-height: 1.6;
        }

        .note-icon {
            width: 100px;
            height: 100px;
            margin-bottom: 20px;
        }

        .error-message {
            background-color: var(--cc-error-red);
            color: var(--cc-white);
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 20px;
            text-align: center;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="login-section">
            <h2>
                <svg class="login-icon" fill="var(--cc-rusty-orange)" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 3c1.66 0 3 1.34 3 3s-1.34 3-3 3-3-1.34-3-3 1.34-3 3-3zm0 14.2c-2.5 0-4.71-1.28-6-3.22.03-1.99 4-3.08 6-3.08 1.99 0 5.97 1.09 6 3.08-1.29 1.94-3.5 3.22-6 3.22z" />
                </svg>
                Catat Cepat
            </h2>
            <?php
            // Display error message if exists
            if (isset($_SESSION['login_error'])) {
                echo '<div class="error-message">' . htmlspecialchars($_SESSION['login_error']) . '</div>';
                // Clear the error message after displaying
                unset($_SESSION['login_error']);
            }
            ?>
            <form method="post" action="auth.php">
                <div class="input-group">
                    <input type="text" name="username" placeholder="Nama Pengguna" required>
                </div>
                <div class="input-group">
                    <input type="password" name="password" placeholder="Kata Sandi" required>
                </div>
                <button type="submit" class="signin-btn">Masuk</button>
            </form>
        </div>
        <div class="welcome-section">
            <svg class="note-icon" fill="var(--cc-white)" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z" />
            </svg>
            <h3>Selamat Datang!</h3>
            <p>Catat Cepat membantu Anda mencatat dan mengorganisir Akuntansi Anda dengan mudah dan cepat.</p>
        </div>
    </div>
</body>

</html>