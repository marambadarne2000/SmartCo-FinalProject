<?php
declare(strict_types=1);

$token = trim((string)($_GET['token'] ?? ''));

if ($token === '') {
    http_response_code(400);
    die('Invalid token');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Reset Password</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f7f7fb;
            margin: 0;
            padding: 32px 16px;
        }
        .card {
            max-width: 420px;
            margin: 0 auto;
            background: #fff;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
        }
        h2 {
            margin-top: 0;
        }
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
        }
        input {
            width: 100%;
            box-sizing: border-box;
            padding: 12px;
            margin-bottom: 16px;
            border: 1px solid #d0d7de;
            border-radius: 8px;
        }
        button {
            width: 100%;
            padding: 12px;
            border: 0;
            border-radius: 8px;
            background: #0b57d0;
            color: #fff;
            font-weight: 700;
            cursor: pointer;
        }
        p {
            color: #555;
        }
    </style>
</head>
<body>
    <div class="card">
        <h2>Reset Your Password</h2>
        <p>Enter your new password below.</p>
        <form method="POST" action="update-password.php">
            <input type="hidden" name="token" value="<?php echo htmlspecialchars($token, ENT_QUOTES, 'UTF-8'); ?>">

            <label for="password">New Password</label>
            <input id="password" type="password" name="password" minlength="8" required>

            <button type="submit">Update Password</button>
        </form>
    </div>
</body>
</html>
