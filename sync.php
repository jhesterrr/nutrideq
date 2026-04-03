<?php
// sync.php - Emergency Sync Tool
require_once 'database.php';
try {
    $db = new Database();
    $pdo = $db->getConnection();
    
    // Check Wellness Messages
    $pdo->exec("ALTER TABLE wellness_messages ADD COLUMN IF NOT EXISTS attachment_path VARCHAR(255) NULL AFTER content");
    $pdo->exec("ALTER TABLE wellness_messages ADD COLUMN IF NOT EXISTS message_type ENUM('text', 'image', 'file') DEFAULT 'text' AFTER attachment_path");
    
    // Check Internal Messages
    $pdo->exec("ALTER TABLE internal_thread_messages ADD COLUMN IF NOT EXISTS attachment_path VARCHAR(255) NULL AFTER message");
    $pdo->exec("ALTER TABLE internal_thread_messages ADD COLUMN IF NOT EXISTS message_type ENUM('text', 'image', 'file') DEFAULT 'text' AFTER attachment_path");
    
    echo "<h1>✅ SUCCESS!</h1><p>Railway Database has been synchronized with the Elite Standard.</p><a href='dashboard.php'>Enter Portal</a>";
} catch (Exception $e) {
    echo "<h1>❌ Error</h1><p>" . $e->getMessage() . "</p>";
}
