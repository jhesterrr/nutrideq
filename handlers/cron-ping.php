<?php
/**
 * NutriDeq Stay-Alive Ping Handler
 * This script is designed to be called by external monitoring services (like UptimeRobot)
 * to prevent Render from putting the app into sleep mode.
 * 
 * It returns a minimal response to keep resource usage extremely low.
 */

// Set header to JSON or text
header('Content-Type: application/json');

// Send success response
echo json_encode([
    'status' => 'active',
    'timestamp' => date('Y-m-d H:i:s'),
    'message' => 'NutriDeq is awake and responsive.'
]);

exit;
