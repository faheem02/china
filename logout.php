<?php
/**
 * Logout Page
 * Manufacturing ERP System
 */

// Start session
session_start();

// Clear all session variables
$_SESSION = array();

// Destroy the session
session_destroy();

// Redirect to login page (relative path)
header('Location: login.php');
exit;
?>