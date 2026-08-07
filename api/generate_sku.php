<?php
/**
 * API: 生成 SKU
 */
define('PMS_ENTRY', true);
require_once __DIR__ . '/../includes/bootstrap.php';
if (!PMS_INSTALLED) { header('Location: /install.php'); exit; }
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}
verifyCsrf();

try {
    // 获取分类 ID
    $categoryId = (int)($_POST['category_id'] ?? 0);
    
    // 生成 SKU
    $sku = generateSku($categoryId);
    
    // 获取分类信息用于返回
    $db = Database::getInstance();
    $stmt = $db->prepare("SELECT id, parent_id, parent_sort_id, sub_id FROM categories WHERE id = ?");
    $stmt->execute([$categoryId]);
    $category = $stmt->fetch();
    
    $parentSortId = (int)($category['parent_sort_id'] ?? 0);
    $subId = (int)($category['sub_id'] ?? 0);
    $prefix = str_pad($parentSortId, 2, '0', STR_PAD_LEFT) . str_pad($subId, 2, '0', STR_PAD_LEFT);
    
    echo json_encode([
        'success' => true,
        'sku' => $sku,
        'prefix' => $prefix
    ]);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
