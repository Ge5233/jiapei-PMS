<?php
/**
 * 批量生成现有产品的 SKU
 * 格式：父分类ID(2位) + 子分类ID(2位) + 序号(3位)
 */
define('PMS_ENTRY', true);
require_once __DIR__ . '/../includes/bootstrap.php';
if (!PMS_INSTALLED) {
    http_response_code(400);
    echo json_encode(['error' => '系统未安装']);
    exit;
}
requireLogin();

// 只允许管理员
if (!isAdmin()) {
    http_response_code(403);
    echo json_encode(['error' => '无权限']);
    exit;
}

header('Content-Type: application/json');

// 只接受 POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => '不支持的请求方法']);
    exit;
}

// 验证 CSRF
if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['error' => 'CSRF 验证失败']);
    exit;
}

$db = Database::getInstance()->getConnection();

try {
    $db->beginTransaction();
    
    // 检查是否强制重新生成（清除现有 SKU）
    $force = isset($_POST['force']) && $_POST['force'] === 'true';
    
    if ($force) {
        // 清除所有产品的 SKU
        $db->exec("UPDATE products SET sku = ''");
    }
    
    // 查询所有 SKU 为空的产品（按分类 ID 排序）
    $stmt = $db->query("
        SELECT p.id, p.category_id, p.sku, c.parent_id
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE p.sku = '' OR p.sku IS NULL
        ORDER BY p.category_id, p.id
    ");
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($products)) {
        $db->rollBack();
        echo json_encode([
            'success' => true,
            'updated' => 0,
            'message' => '没有需要生成 SKU 的产品'
        ]);
        exit;
    }
    
    // 按分类分组
    $groupedByCategory = [];
    foreach ($products as $p) {
        $categoryId = (int)$p['category_id'];
        if (!isset($groupedByCategory[$categoryId])) {
            $groupedByCategory[$categoryId] = [];
        }
        $groupedByCategory[$categoryId][] = $p;
    }
    
    $updatedCount = 0;
    $errors = [];
    
    // 为每个分类下的产品生成 SKU
    foreach ($groupedByCategory as $categoryId => $categoryProducts) {
        // 查询分类信息
        $stmt = $db->prepare("SELECT id, parent_id FROM categories WHERE id = ?");
        $stmt->execute([$categoryId]);
        $category = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$category) {
            $errors[] = "分类 ID={$categoryId} 不存在";
            continue;
        }
        
        $parentId = (int)$category['parent_id'];
        $childId = (int)$category['id'];
        
        // 如果 parent_id = 0，说明是一级分类，跳过
        if ($parentId === 0) {
            $errors[] = "分类 ID={$categoryId} 是一级分类，无法生成 SKU";
            continue;
        }
        
        // 生成 SKU 前缀
        $prefix = str_pad($parentId, 2, '0', STR_PAD_LEFT) . str_pad($childId, 2, '0', STR_PAD_LEFT);
        
        // 查询该分类下已有产品数量（包括已有 SKU 的）
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM products WHERE category_id = ? AND sku != '' AND sku IS NOT NULL");
        $stmt->execute([$categoryId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $existingCount = (int)$result['count'];
        
        // 为该分类下的每个产品生成 SKU
        $seq = $existingCount;
        foreach ($categoryProducts as $product) {
            $seq++;
            $sku = $prefix . str_pad($seq, 3, '0', STR_PAD_LEFT);
            
            // 检查 SKU 是否已存在
            $stmt = $db->prepare("SELECT id FROM products WHERE sku = ?");
            $stmt->execute([$sku]);
            while ($stmt->fetch()) {
                $seq++;
                $sku = $prefix . str_pad($seq, 3, '0', STR_PAD_LEFT);
                $stmt = $db->prepare("SELECT id FROM products WHERE sku = ?");
                $stmt->execute([$sku]);
            }
            
            // 更新产品 SKU
            $stmt = $db->prepare("UPDATE products SET sku = ? WHERE id = ?");
            $stmt->execute([$sku, $product['id']]);
            $updatedCount++;
        }
    }
    
    $db->commit();
    
    // 记录操作日志
    Log::write('batch_generate_sku', 'product', 0, "批量生成 SKU：更新 {$updatedCount} 个产品");
    
    echo json_encode([
        'success' => true,
        'updated' => $updatedCount,
        'message' => "成功生成 {$updatedCount} 个产品的 SKU",
        'errors' => $errors
    ]);
    
} catch (Exception $e) {
    $db->rollBack();
    http_response_code(500);
    echo json_encode(['error' => '批量生成失败: ' . $e->getMessage()]);
}
