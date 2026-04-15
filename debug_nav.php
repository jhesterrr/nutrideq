<?php
session_start();
require_once 'navigation.php';
$role = $_SESSION['user_role'] ?? 'admin'; // default to admin for trace
$page = 'fct-library.php';
$links = getNavigationLinks($role, $page);
echo "<pre>";
print_r($links);
echo "</pre>";
?>
