<?php
// process_register.php
session_start();

// require the Database class from project root
require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../includes/mail_helper.php';

// Check if form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $name = trim($_POST['name']);
    $email = strtolower(trim($_POST['email']));
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // Initialize errors array
    $errors = [];

    // Validation
    if (empty($name) || strlen($name) < 2) {
        $errors[] = "Name must be at least 2 characters long";
    }

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Please enter a valid email address";
    }

    // GMAIL ONLY RESTRICTION
    if (!preg_match('/@gmail\.com$/i', $email)) {
        $errors[] = "Only Gmail accounts are allowed to register";
    }

    if (strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters long";
    }

    if (!preg_match('/^(?=.*[A-Za-z])(?=.*\d).{8,}$/', $password)) {
        $errors[] = "Password must contain both letters and numbers";
    }

    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match";
    }

    // If no validation errors, proceed with registration
    if (empty($errors)) {
        try {
            $database = new Database();
            $conn = $database->getConnection();

            // Check if email already exists
            $check_sql = "SELECT id FROM users WHERE email = ?";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->execute([$email]);

            if ($check_stmt->rowCount() > 0) {
                $errors[] = "Email already registered";
            } else {
                // Hash password
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $verification_token = bin2hex(random_bytes(32));

                // Insert user into database - automatically set as 'user' role
                $insert_sql = "INSERT INTO users (name, email, password, role, status, is_verified, verification_token, created_at, updated_at) VALUES (?, ?, ?, 'regular', 'active', 0, ?, NOW(), NOW())";
                $insert_stmt = $conn->prepare($insert_sql);

                if ($insert_stmt->execute([$name, $email, $hashed_password, $verification_token])) {
                    $user_id = $conn->lastInsertId();

                    // Verification Link
                    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
                    $host = $_SERVER['HTTP_HOST'];
                    $verifyLink = $protocol . "://" . $host . "/login-logout/verify-email.php?token=" . $verification_token;

                    // Send verification email
                    $subject = "Verify Your NutriDeq Account";
                    $body = "
                        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: auto; padding: 20px; border: 1px solid #eee; border-radius: 10px;'>
                            <h2 style='color: #10b981; text-align: center;'>Welcome to NutriDeq!</h2>
                            <p>Hello <strong>{$name}</strong>,</p>
                            <p>Thank you for registering with NutriDeq. To complete your registration and access the dashboard, please verify your email address by clicking the button below.</p>
                            <div style='text-align: center; margin: 30px 0;'>
                                <a href='$verifyLink' style='background-color: #10b981; color: white; padding: 15px 25px; text-decoration: none; border-radius: 8px; font-weight: bold;'>Verify Email Address</a>
                            </div>
                            <p style='color: #666; font-size: 0.9rem;'>If you didn't create an account, you can safely ignore this email.</p>
                            <hr style='border: 0; border-top: 1px solid #eee; margin: 20px 0;'>
                            <p style='font-size: 0.8rem; color: #999; text-align: center;'>NutriDeq Clinical Platform &copy; " . date('Y') . "</p>
                        </div>
                    ";

                    sendEmail($email, $subject, $body);

                    // If a clients row already exists for this email (imported/unlinked), attach it to the new user
                    $attach_stmt = $conn->prepare("SELECT id FROM clients WHERE email = ? LIMIT 1");
                    $attach_stmt->execute([$email]);
                    $existing_client = $attach_stmt->fetch(PDO::FETCH_ASSOC);

                    if ($existing_client) {
                        // update existing client to link user_id (preserve staff assignment if present)
                        $update_client = $conn->prepare("UPDATE clients SET user_id = ?, updated_at = NOW() WHERE id = ?");
                        $update_client->execute([$user_id, $existing_client['id']]);
                    } else {
                        // create a new client record and leave staff_id NULL (unassigned)
                        $client_sql = "INSERT INTO clients (user_id, name, email, staff_id, status, created_at, updated_at) VALUES (?, ?, ?, NULL, 'active', NOW(), NOW())";
                        $client_stmt = $conn->prepare($client_sql);
                        $client_stmt->execute([$user_id, $name, $email]);
                    }

                    // DO NOT Log user in yet. Instead, redirect to a success page.
                    header("Location: verification_sent.php?email=" . urlencode($email));
                    exit();
                } else {
                    $errors[] = "Error creating account. Please try again.";
                }
            }

        } catch (PDOException $e) {
            $errors[] = "Database error: " . $e->getMessage();
        }
    }

    // If there are errors, redirect back with error messages
    if (!empty($errors)) {
        $error_string = urlencode(implode(". ", $errors));
        $redirect_url = "NutriDeqN-Signup.php?error=" . $error_string . "&name=" . urlencode($name) . "&email=" . urlencode($email);
        header("Location: " . $redirect_url);
        exit();
    }
} else {
    // If not POST request, redirect to register page
    header("Location: NutriDeqN-Signup.php");
    exit();
}
?>