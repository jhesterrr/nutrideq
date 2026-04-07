<?php
session_start();
require_once __DIR__ . '/../database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
$password = $input['password'] ?? '';
$confirm_password = $input['confirm_password'] ?? '';

if (empty($password) || strlen($password) < 8) {
    echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters long']);
    exit();
}

if (!preg_match('/^(?=.*[A-Za-z])(?=.*\d).{8,}$/', $password)) {
    echo json_encode(['success' => false, 'message' => 'Password must contain both letters and numbers']);
    exit();
}

if ($password !== $confirm_password) {
    echo json_encode(['success' => false, 'message' => 'Passwords do not match']);
    exit();
}

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    $user_id = $_SESSION['user_id'];
    
    $stmt = $conn->prepare("UPDATE users SET password = ?, has_password = 1, updated_at = NOW() WHERE id = ?");
    if ($stmt->execute([$hashed_password, $user_id])) {
        $_SESSION['has_password'] = 1;
        echo json_encode(['success' => true, 'message' => 'Password set successfully!']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update password.']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
