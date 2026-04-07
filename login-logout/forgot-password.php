<?php
session_start();
require_once __DIR__ . '/../database.php';

$message = '';
$error = '';
$email = '';
$step = 1; // 1: Email entry, 2: Recovery Key Entry, 3: Reset Password

try {
    $db = new Database();
    $conn = $db->getConnection();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $email = trim($_POST['email'] ?? '');
        $action = $_POST['action'] ?? '';

        // Step 1: Verify Email
        if ($action === 'verify_email') {
            $stmt = $conn->prepare("SELECT id, name FROM users WHERE email = ? AND status = 'active'");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user) {
                $_SESSION['reset_user_id'] = $user['id'];
                $_SESSION['reset_user_email'] = $email;
                $_SESSION['reset_user_name'] = $user['name'];
                $step = 2;
            } else {
                $error = "No active account found with this email address.";
            }
        }

        // Step 2: Verify Recovery Key
        elseif ($action === 'verify_key') {
            $key = trim($_POST['recovery_key'] ?? '');
            $user_id = $_SESSION['reset_user_id'];

            $stmt = $conn->prepare("SELECT id FROM users WHERE id = ? AND recovery_key = ?");
            $stmt->execute([$user_id, $key]);
            
            if ($stmt->fetch()) {
                $step = 3;
            } else {
                $error = "Invalid recovery key. Please try again.";
                $step = 2;
            }
        }

        // Step 3: Final Password Reset
        elseif ($action === 'reset_password') {
            $pass = $_POST['new_password'] ?? '';
            $conf = $_POST['confirm_password'] ?? '';
            $user_id = $_SESSION['reset_user_id'];

            if (strlen($pass) < 8) {
                $error = "Password must be at least 8 characters long.";
                $step = 3;
            } elseif ($pass !== $conf) {
                $error = "Passwords do not match.";
                $step = 3;
            } else {
                $hashed = password_hash($pass, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$hashed, $user_id]);
                
                $_SESSION['success_message'] = "Password reset successfully! You can now log in.";
                header("Location: NutriDeqN-Login.php");
                exit();
            }
        }
    }
} catch (Exception $e) {
    $error = "Error: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Recovery - NutriDeq</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --primary: #10b981; --text-dark: #0f172a; --bounce: cubic-bezier(0.34, 1.56, 0.64, 1); }
        body { margin: 0; padding: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; font-family: 'Inter', sans-serif; background: #f8fafc; }
        .auth-card { width: 100%; max-width: 450px; padding: 40px; background: white; border-radius: 32px; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.1); text-align: center; }
        .logo img { height: 50px; margin-bottom: 20px; }
        h1 { font-size: 1.8rem; color: var(--text-dark); margin-bottom: 10px; }
        p { color: #64748b; line-height: 1.6; margin-bottom: 30px; }
        .alert { padding: 15px; border-radius: 12px; margin-bottom: 20px; font-size: 0.9rem; text-align: left; }
        .alert-success { background: #d1fae5; color: #065f46; border: 1px solid #10b981; }
        .alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #ef4444; }
        .form-control { width: 100%; padding: 14px 16px; border: 2px solid #e2e8f0; border-radius: 16px; box-sizing: border-box; font-size: 1rem; transition: 0.3s; margin-bottom: 20px; }
        .form-control:focus { outline: none; border-color: var(--primary); }
        .btn-submit { width: 100%; padding: 16px; background: var(--primary); color: white; border: none; border-radius: 16px; font-weight: 700; cursor: pointer; transition: 0.3s; margin-bottom: 10px; }
        .btn-submit:hover { transform: translateY(-3px); box-shadow: 0 10px 20px rgba(16, 185, 129, 0.2); }
        .back-link { display: block; margin-top: 20px; color: #64748b; text-decoration: none; font-weight: 600; font-size: 0.9rem; }
    </style>
</head>
<body>
    <div class="auth-card">
        <div class="logo"><img src="../assets/img/logo.png" alt="NutriDeq"></div>
        
        <?php if ($step == 1): ?>
            <h1>Account Recovery</h1>
            <p>Enter your email address to find your account.</p>
            <?php if ($message): ?><div class="alert alert-success"><?php echo $message; ?></div><?php endif; ?>
            <?php if ($error): ?><div class="alert alert-error"><?php echo $error; ?></div><?php endif; ?>
            <form method="POST">
                <input type="hidden" name="action" value="verify_email">
                <input type="email" name="email" class="form-control" placeholder="your@email.com" required value="<?php echo htmlspecialchars($email); ?>">
                <button type="submit" class="btn-submit">Find Account</button>
            </form>

        <?php elseif ($step == 2): ?>
            <h1>Recovery Key</h1>
            <p>Hi, <strong><?php echo htmlspecialchars($_SESSION['reset_user_name']); ?></strong>. Please enter your 12-character recovery key (e.g., ND-XXXX-XXXX).</p>
            <?php if ($error): ?><div class="alert alert-error"><?php echo $error; ?></div><?php endif; ?>
            <form method="POST">
                <input type="hidden" name="action" value="verify_key">
                <input type="text" name="recovery_key" class="form-control" placeholder="ND-XXXX-XXXX" required pattern="ND-[A-Z0-9]{4}-[A-Z0-9]{4}">
                <button type="submit" class="btn-submit">Verify Key</button>
            </form>

        <?php elseif ($step == 3): ?>
            <h1>Reset Password</h1>
            <p>Create a new secure password for your account.</p>
            <?php if ($error): ?><div class="alert alert-error"><?php echo $error; ?></div><?php endif; ?>
            <form method="POST">
                <input type="hidden" name="action" value="reset_password">
                <input type="password" name="new_password" class="form-control" placeholder="New Password" required minlength="8">
                <input type="password" name="confirm_password" class="form-control" placeholder="Confirm Password" required minlength="8">
                <button type="submit" class="btn-submit">Update Password</button>
            </form>
        <?php endif; ?>

        <a href="NutriDeqN-Login.php" class="back-link"><i class="fas fa-arrow-left"></i> Back to Login</a>
    </div>
</body>
</html>
