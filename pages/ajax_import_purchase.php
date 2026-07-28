<?php
require_once '../includes/database.php';
requireLogin();

header('Content-Type: application/json');

if (!isset($_GET['action'])) {
    echo json_encode(['error' => 'No action specified']);
    exit;
}

switch ($_GET['action']) {
    case 'get_supplier':
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        if ($id <= 0) {
            echo json_encode(['error' => 'Invalid supplier ID']);
            exit;
        }
        $supplier = getRow("SELECT id, supplier_name, company_name, current_balance, opening_balance FROM chinese_suppliers WHERE id = ?", 'i', [$id]);
        if ($supplier) {
            echo json_encode($supplier);
        } else {
            echo json_encode(['error' => 'Supplier not found']);
        }
        break;

    case 'get_material_price':
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        if ($id <= 0) {
            echo json_encode(['error' => 'Invalid material ID']);
            exit;
        }
        $material = getRow("SELECT id, material_code, material_name, unit, purchase_price_pkr, selling_price, current_stock FROM raw_materials WHERE id = ?", 'i', [$id]);
        if ($material) {
            echo json_encode($material);
        } else {
            echo json_encode(['error' => 'Material not found']);
        }
        break;

    default:
        echo json_encode(['error' => 'Unknown action']);
        break;
}
