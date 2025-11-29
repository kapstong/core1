<?php
require_once __DIR__ . '/backend/config/database.php';

$db = Database::getInstance();
$conn = $db->getConnection();

// Check the PO with number like 'PO-2025-00004'
$stmt = $conn->prepare("
    SELECT 
        po.id, 
        po.po_number, 
        po.supplier_id, 
        s.id as supplier_user_id,
        s.full_name as supplier_name, 
        s.role,
        s.is_active
    FROM purchase_orders po 
    LEFT JOIN users s ON po.supplier_id = s.id 
    WHERE po.po_number LIKE 'PO-2025-00004%' 
    LIMIT 1
");
$stmt->execute();
$result = $stmt->fetch(PDO::FETCH_ASSOC);

echo "PO Data:\n";
echo json_encode($result, JSON_PRETTY_PRINT);

// Also check all suppliers
echo "\n\nAll Suppliers:\n";
$stmt = $conn->prepare("SELECT id, full_name, role, is_active FROM users WHERE id > 0 LIMIT 10");
$stmt->execute();
$suppliers = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($suppliers, JSON_PRETTY_PRINT);
