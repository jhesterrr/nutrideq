<?php
session_start();
require_once __DIR__ . '/../database.php';

$token = $_GET['token'] ?? '';
$success = false;
$message = "Invalid or expired verification token.";

if (!empty($token)) {
    try {
        $database = new Database();
        $conn = $database->getConnection();

        $stmt = $conn->prepare("SELECT id, name, email FROM users WHERE verification_token = ? AND is_verified = 0");
        $stmt->execute([$token]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            $update = $conn->prepare("UPDATE users SET is_verified = 1, verification_token = NULL, updated_at = NOW() WHERE id = ?");
            if ($update->execute([$user['id']])) {
                $success = true;
                $message = "Your email has been successfully verified! You can now log in to your account.";
                
                // Automatically log them in
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_role'] = 'user';
                $_SESSION['role'] = 'user';
                $_SESSION['logged_in'] = true;
            } else {
                $message = "Failed to update verification status. Please try again.";
            }
        }
    } catch (PDOException $e) {
        $message = "Database error: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Verification - NutriDeq</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #10b981;
            --error: #ef4444;
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
            font-size: 64px; color: <?php echo $success ? 'var(--primary)' : 'var(--error)'; ?>; margin-bottom: 20px;
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
        <div class="icon"><i class="fas <?php echo $success ? 'fa-check-circle' : 'fa-times-circle'; ?>"></i></div>
        <h1><?php echo $success ? 'Verification Successful!' : 'Verification Failed'; ?></h1>
        <p><?php echo htmlspecialchars($message); ?></p>
        <a href="<?php echo $success ? '../dashboard.php' : 'NutriDeqN-Login.php'; ?>" class="btn">
            <?php echo $success ? 'Go to Dashboard' : 'Back to Login'; ?>
        </a>
    </div>
</body>
</html>
