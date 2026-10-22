<?php
/**
 * Customer Party Ledger
 * This page has moved to customer_party_ledger.php — kept as a redirect for old bookmarks.
 */
require_once '../includes/database.php';
requireLogin();

$query = http_build_query($_GET);
header('Location: customer_party_ledger.php' . ($query ? '?' . $query : ''));
exit;
