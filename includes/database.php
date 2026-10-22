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

$conn = new mysqli($host, $user, $pass, $db);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set charset to UTF-8
$conn->set_charset("utf8");

// Keep employee salary payment references available in older installations.
$payableColumn = $conn->query("SHOW COLUMNS FROM employee_payments LIKE 'payable_id'");
if ($payableColumn->num_rows === 0) {
    $conn->query("ALTER TABLE employee_payments ADD COLUMN payable_id INT NULL AFTER notes");
}

$importPaymentColumn = $conn->query("SHOW COLUMNS FROM import_purchases LIKE 'payment_method'");
if ($importPaymentColumn->num_rows === 0) {
    $conn->query("ALTER TABLE import_purchases ADD COLUMN payment_method VARCHAR(50) NULL DEFAULT 'credit' AFTER payment_status");
}

$saleItemTypeColumn = $conn->query("SHOW COLUMNS FROM sale_items LIKE 'product_type'");
if ($saleItemTypeColumn->num_rows === 0) {
    $conn->query("ALTER TABLE sale_items ADD COLUMN product_type VARCHAR(20) NOT NULL DEFAULT 'finished_good' AFTER product_id");
}

$salesPrevBalColumn = $conn->query("SHOW COLUMNS FROM sales LIKE 'previous_balance'");
if ($salesPrevBalColumn->num_rows === 0) {
    $conn->query("ALTER TABLE sales ADD COLUMN previous_balance DECIMAL(18,2) NOT NULL DEFAULT 0.00 AFTER balance");
}

// Walk-in (counter) sales have no customer record, so sales.customer_id must allow NULL
$salesCustomerIdCol = $conn->query("SHOW COLUMNS FROM sales LIKE 'customer_id'");
if ($salesCustomerIdCol->num_rows > 0) {
    $cidCol = $salesCustomerIdCol->fetch_assoc();
    if (strtoupper($cidCol['Null'] ?? 'NO') !== 'YES') {
        $conn->query("ALTER TABLE sales MODIFY customer_id INT NULL");
    }
}

// Bank accounts table migration
$bankTableChk = $conn->query("SHOW TABLES LIKE 'bank_accounts'");
if ($bankTableChk->num_rows === 0) {
    $conn->query("CREATE TABLE IF NOT EXISTS bank_accounts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        bank_name VARCHAR(150) NOT NULL,
        account_title VARCHAR(150) NULL,
        account_number VARCHAR(100) NOT NULL,
        branch VARCHAR(150) NULL,
        opening_balance DECIMAL(18,2) NOT NULL DEFAULT 0.00,
        current_balance DECIMAL(18,2) NOT NULL DEFAULT 0.00,
        status ENUM('active','inactive') NOT NULL DEFAULT 'active',
        date_time DATETIME NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

// Ensure bank_id and bank_name columns in payment/transaction tables
$tablesWithBank = [
    'customer_receipts' => ['bank_id' => "INT NULL AFTER payment_method", 'bank_name' => "VARCHAR(150) NULL AFTER bank_id"],
    'supplier_payments' => ['bank_id' => "INT NULL AFTER payment_type"],
    'chinese_supplier_payments' => ['bank_id' => "INT NULL AFTER payment_type"],
    'expenses' => ['bank_id' => "INT NULL AFTER paid_by", 'bank_name' => "VARCHAR(150) NULL AFTER bank_id"],
    'sales' => ['bank_id' => "INT NULL AFTER payment_method", 'bank_name' => "VARCHAR(150) NULL AFTER bank_id"],
    'local_purchases' => ['bank_id' => "INT NULL AFTER payment_method", 'bank_name' => "VARCHAR(150) NULL AFTER bank_id"],
    'import_purchases' => ['bank_id' => "INT NULL AFTER payment_method", 'bank_name' => "VARCHAR(150) NULL AFTER bank_id"],
    'party_transactions' => ['bank_id' => "INT NULL AFTER payment_method", 'bank_name' => "VARCHAR(150) NULL AFTER bank_id"]
];

foreach ($tablesWithBank as $tbl => $colDefs) {
    $tCheck = $conn->query("SHOW TABLES LIKE '$tbl'");
    if ($tCheck && $tCheck->num_rows > 0) {
        foreach ($colDefs as $cName => $cDef) {
            $cCheck = $conn->query("SHOW COLUMNS FROM `$tbl` LIKE '$cName'");
            if ($cCheck && $cCheck->num_rows === 0) {
                $conn->query("ALTER TABLE `$tbl` ADD COLUMN `$cName` $cDef");
            }
        }
    }
}


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

/**
 * Get all active bank accounts
 * @return array
 */
function getActiveBankAccounts() {
    return getRows("SELECT * FROM bank_accounts WHERE status = 'active' ORDER BY bank_name ASC, account_number ASC");
}

/**
 * Adjust bank account current_balance
 * @param int $bankId
 * @param float $amount
 * @param string $direction 'in' (increases balance) or 'out' (decreases balance)
 * @return bool
 */
function updateBankBalance($bankId, $amount, $direction = 'in') {
    $bankId = (int)$bankId;
    $amount = (float)$amount;
    if ($bankId <= 0 || $amount <= 0) {
        return false;
    }
    if ($direction === 'in') {
        return modifyData("UPDATE bank_accounts SET current_balance = current_balance + ? WHERE id = ?", 'di', [$amount, $bankId]);
    } else {
        return modifyData("UPDATE bank_accounts SET current_balance = current_balance - ? WHERE id = ?", 'di', [$amount, $bankId]);
    }
}
?>