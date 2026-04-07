<?php
session_start();
require_once '../database.php';

// Load PHPMailer if available (Railway deployment)
if (file_exists('../vendor/autoload.php')) {
    require '../vendor/autoload.php';
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$message = '';
$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    
    try {
        $db = new Database();
        $conn = $db->getConnection();

        // Check if user exists
        $stmt = $conn->prepare("SELECT id, name FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            // Generate secure token
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

            // Delete any existing tokens for this email
            $delStmt = $conn->prepare("DELETE FROM password_resets WHERE email = ?");
            $delStmt->execute([$email]);

            // Insert new token
            $insStmt = $conn->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)");
            $insStmt->execute([$email, $token, $expires]);

            // Determine environment and reset link
            $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
            $host = $_SERVER['HTTP_HOST'];
            $resetLink = $protocol . "://" . $host . "/login-logout/reset-password.php?token=" . $token;

            // Try sending real email if PHPMailer is loaded and SMTP is configured
            $mailSent = false;
            
            // SMTP variables
            $smtpHost = getenv('SMTP_HOST') ?: ($_ENV['SMTP_HOST'] ?? null);
            $smtpUser = getenv('SMTP_USER') ?: ($_ENV['SMTP_USER'] ?? null);
            $smtpPass = getenv('SMTP_PASS') ?: ($_ENV['SMTP_PASS'] ?? null);
            $smtpPort = getenv('SMTP_PORT') ?: ($_ENV['SMTP_PORT'] ?? 587);

            if ($smtpHost && $smtpUser && $smtpPass) {
                try {
                    $mail = new PHPMailer(true);
                    
                    // Server settings
                    $mail->isSMTP();
                    $mail->Host       = $smtpHost;
                    $mail->SMTPAuth   = true;
                    $mail->Username   = $smtpUser;
                    $mail->Password   = $smtpPass;
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                    $mail->Port       = $smtpPort;
                    $mail->Timeout    = 10;

                    // Recipients
                    $mail->setFrom($smtpUser, 'NutriDeq Support');
                    $mail->addAddress($email, $user['name']);
                    
                    // Content
                    $mail->isHTML(true);
                    $mail->Subject = 'NutriDeq - Password Reset Request';
                    $mail->Body    = "
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
                    
                    if($mail->send()) {
                        $mailSent = true;
                    }
                } catch (Exception $e) {
                    // Silently fail or log
                }
            }

            if ($mailSent) {
                $message = "A secure reset link has been sent to your email address. Please check your inbox.";
            } else {
                // Simulation link if email fails
                $message = "If an account exists for <strong>$email</strong>, a reset link has been generated.<br><br>
                            <a href='$resetLink' style='color: #10b981; font-weight: bold;'>Reset Password Now (Manual Link)</a>";
            }
        } else {
            $message = "If an account exists for that email address, a reset link has been generated.";
        }
    } catch (Exception $e) {
        $error = "Error processing request: " . $e->getMessage();
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
    <style>
        :root {
            --primary: #10b981;
            --text-dark: #0f172a;
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
        <p>Enter your registered email address and we'll send you a link to reset your password.</p>

        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo $message; ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>

        <form action="" method="POST">
            <div class="form-group">
                <input type="email" name="email" class="form-control" placeholder="Enter your email" required value="<?php echo htmlspecialchars($email); ?>">
            </div>
            <button type="submit" class="btn-submit">Send Reset Link</button>
        </form>
        
        <a href="NutriDeqN-Login.php" class="back-link">Back to Login</a>
    </div>
</body>
</html>
