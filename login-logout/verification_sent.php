<?php
session_start();
$email = $_GET['email'] ?? 'your email';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verification Sent - NutriDeq</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #10b981;
            --text-dark: #0f172a;
            --bounce: cubic-bezier(0.34, 1.56, 0.64, 1);
        }
        body {
            margin: 0; padding: 0; min-height: 100vh;
            display: flex; align-items: center; justify-content: center;
            font-family: 'Inter', sans-serif;
            background: #f8fafc;
        }
        .card {
            width: 100%; max-width: 500px; padding: 40px;
            background: white; border-radius: 32px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.1);
            text-align: center;
        }
        .icon {
            font-size: 64px; color: var(--primary); margin-bottom: 20px;
        }
        h1 { color: var(--text-dark); margin-bottom: 10px; }
        p { color: #64748b; line-height: 1.6; margin-bottom: 30px; }
        .btn {
            display: inline-block; padding: 14px 30px;
            background: var(--primary); color: white;
            text-decoration: none; border-radius: 12px;
            font-weight: 700; transition: 0.3s;
        }
        .btn:hover { transform: translateY(-3px); box-shadow: 0 10px 20px rgba(16, 185, 129, 0.2); }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon"><i class="fas fa-paper-plane"></i></div>
        <h1>Check Your Email</h1>
        <p>We've sent a verification link to <strong><?php echo htmlspecialchars($email); ?></strong>. Please click the link in the email to activate your account.</p>
        <p>If you don't see it, check your spam folder.</p>
        <a href="NutriDeqN-Login.php" class="btn">Back to Login</a>
    </div>
</body>
</html>
