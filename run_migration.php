<?php
require_once 'database.php';

try {
    $database = new Database();
    $db = $database->getConnection();

    echo "<h2>NutriDeq Production Migration</h2>";

    // 1. Update food_logs
    $sql1 = "ALTER TABLE food_logs MODIFY COLUMN meal_type ENUM('Breakfast', 'AM Snack', 'Lunch', 'PM Snack', 'Dinner', 'Snack') NOT NULL";
    $db->exec($sql1);
    echo "✅ Successfully updated 'food_logs' table.<br>";

    // 2. Update food_tracking
    $sql2 = "ALTER TABLE food_tracking MODIFY COLUMN meal_type ENUM('Breakfast', 'AM Snack', 'Lunch', 'PM Snack', 'Dinner', 'Snack') NOT NULL";
    $db->exec($sql2);
    echo "✅ Successfully updated 'food_tracking' table.<br>";

    echo "<br><b>Migration complete!</b><br>";
    echo "<span style='color:red;'>IMPORTANT: Please delete this file (run_migration.php) from your repository immediately for security.</span>";

} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>
