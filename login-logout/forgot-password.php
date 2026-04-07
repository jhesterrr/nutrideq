<?php
session_start();
require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../includes/mail_helper.php';

$message = '';
$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim($_POST['email']));
    
    // GMAIL ONLY RESTRICTION
    if (!preg_match('/@gmail\.com$/i', $email)) {
        $error = "Only Gmail accounts are supported for password reset.";
    } else {
        try {
            $db = new Database();
            $conn = $db->getConnection();

            // Check if user exists and is active
            $stmt = $conn->prepare("SELECT id, name FROM users WHERE email = ? AND status = 'active'");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user) {
                // Generate secure token
                $token = bin2hex(random_bytes(32));
                $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

                // Fail-safe: Create table if not exists
                $conn->exec("CREATE TABLE IF NOT EXISTS password_resets (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    email VARCHAR(255) NOT NULL,
                    token VARCHAR(255) NOT NULL,
                    expires_at DATETIME NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX (email),
                    INDEX (token)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

                // Delete any existing tokens for this email
                $delStmt = $conn->prepare("DELETE FROM password_resets WHERE email = ?");
                $delStmt->execute([$email]);

                // Insert new token
                $insStmt = $conn->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)");
                $insStmt->execute([$email, $token, $expires]);

                // Determine reset link
                $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
                $host = $_SERVER['HTTP_HOST'];
                $resetLink = $protocol . "://" . $host . "/login-logout/reset-password.php?token=" . $token;

                // Send email
                $subject = "NutriDeq - Password Reset Request";
                $body = "
                    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: auto; padding: 20px; border: 1px solid #eee; border-radius: 10px;'>
                        <h2 style='color: #10b981; text-align: center;'>NutriDeq Password Reset</h2>
                        <p>Hello <strong>{$user['name']}</strong>,</p>
                        <p>We received a request to reset your password. Click the button below to set a new one. This link will expire in 1 hour.</p>
                        <div style='text-align: center; margin: 30px 0;'>
                            <a href='$resetLink' style='background-color: #10b981; color: white; padding: 15px 25px; text-decoration: none; border-radius: 8px; font-weight: bold;'>Reset Password Now</a>
                        </div>
                        <p style='color: #666; font-size: 0.9rem;'>If you didn't request this, you can safely ignore this email.</p>
                        <hr style='border: 0; border-top: 1px solid #eee; margin: 20px 0;'>
                        <p style='font-size: 0.8rem; color: #999; text-align: center;'>NutriDeq Clinical Platform &copy; " . date('Y') . "</p>
                    </div>
                ";
                
                if (sendEmail($email, $subject, $body)) {
                    $message = "A secure reset link has been sent to your Gmail address. Please check your inbox.";
                } else {
                    // For development, provide the link if mail fails or SMTP is not configured
                    if (!getenv('SMTP_HOST') || getenv('APP_ENV') === 'development') {
                        $message = "A reset link has been generated. <br><br>
                                    <div style='background: rgba(16,185,129,0.1); padding: 15px; border-radius: 10px; border: 1px dashed var(--primary);'>
                                        <strong>DEVELOPMENT SIMULATION:</strong><br>
                                        Click here to reset: <a href='$resetLink' style='color: var(--primary); font-weight: bold;'>Reset Password Now</a>
                                    </div>";
                    } else {
                        $error = "Failed to send email. Please check your SMTP configuration in Railway settings.";
                    }
                }
            } else {
                // Security: Generic message to prevent email enumeration
                $message = "If an account exists for that email address, a reset link has been sent.";
            }
        } catch (Exception $e) {
            $error = "Error processing request: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - NutriDeq</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/base.css">
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
        .auth-card {
            width: 100%; max-width: 450px; padding: 40px;
            background: white; border-radius: 32px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.1);
        }
        .logo { text-align: center; margin-bottom: 30px; }
        .logo img { height: 50px; border-radius: 12px; }
        h1 { font-size: 1.8rem; color: var(--text-dark); text-align: center; margin-bottom: 10px; }
        p { color: #64748b; text-align: center; margin-bottom: 30px; line-height: 1.6; }
        .alert { padding: 15px; border-radius: 12px; margin-bottom: 20px; font-size: 0.9rem; }
        .alert-success { background: #d1fae5; color: #065f46; border: 1px solid #10b981; }
        .alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #ef4444; }
        .form-group { margin-bottom: 20px; }
        .form-control {
            width: 100%; padding: 14px 16px; border: 2px solid #e2e8f0;
            border-radius: 16px; box-sizing: border-box; font-size: 1rem;
            transition: all 0.3s;
        }
        .form-control:focus { outline: none; border-color: var(--primary); }
        .btn-submit {
            width: 100%; padding: 16px; background: var(--primary); color: white;
            border: none; border-radius: 16px; font-weight: 700; font-size: 1rem;
            cursor: pointer; transition: 0.3s;
        }
        .btn-submit:hover { transform: translateY(-3px); box-shadow: 0 10px 20px rgba(16, 185, 129, 0.2); }
        .back-link { display: block; text-align: center; margin-top: 20px; color: var(--primary); text-decoration: none; font-weight: 600; }
    </style>
</head>
<body>
    <div class="auth-card">
        <div class="logo">
            <img src="../assets/img/logo.png" alt="NutriDeq">
        </div>
        <h1>Forgot Password?</h1>
        <p>No worries! Enter your registered Gmail address and we'll send you a link to reset your password.</p>

        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo $message; ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>

        <form action="" method="POST">
            <div class="form-group">
                <input type="email" name="email" class="form-control" placeholder="Enter your Gmail" required value="<?php echo htmlspecialchars($email); ?>">
            </div>
            <button type="submit" class="btn-submit">Send Reset Link</button>
        </form>
        
        <a href="NutriDeqN-Login.php" class="back-link">Back to Login</a>
    </div>
</body>
</html>
