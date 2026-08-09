<?php
/**
 * 一次性：重写所有产品 SKU 为规范格式
 * 格式：父分类ID(2位) + 子分类ID(2位) + 序号(3位)
 */
define('PMS_ENTRY', true);
require_once __DIR__ . '/../includes/bootstrap.php';
if (!PMS_INSTALLED) { die("未安装\n"); }

$db = Database::getInstance();
$stmt = $db->query(
    "SELECT p.id, p.sku, p.category_id,
            pc.parent_sort_id, c.sub_id
     FROM products p
     JOIN categories c ON c.id = p.category_id
     JOIN categories pc ON pc.id = c.parent_id
     ORDER BY p.category_id, p.id"
);
$rows = $stmt->fetchAll();

$updated = 0;
$counts = []; // per category counter
foreach ($rows as $r) {
    $catId = (int)$r['category_id'];
    $parentSortId = (int)$r['parent_sort_id'];
    $subId = (int)$r['sub_id'];
    
    if (!isset($counts[$catId])) $counts[$catId] = 0;
    $counts[$catId]++;
    
    $prefix = str_pad($parentSortId, 2, '0', STR_PAD_LEFT)
            . str_pad($subId, 2, '0', STR_PAD_LEFT);
    $seq = $counts[$catId];
    // 防止重复：检查 SKU 是否已存在，递增直到唯一
    while (true) {
        $newSku = $prefix . str_pad($seq, 3, '0', STR_PAD_LEFT);
        $check = $db->prepare("SELECT id FROM products WHERE sku = ? AND id != ?");
        $check->execute([$newSku, (int)$r['id']]);
        if (!$check->fetch()) break;
        $seq++;
    }
    $counts[$catId] = $seq; // 更新计数器
    
    if ($r['sku'] !== $newSku) {
        $db->prepare("UPDATE products SET sku = ? WHERE id = ?")
           ->execute([$newSku, (int)$r['id']]);
        $updated++;
        echo "  {$r['sku']} -> {$newSku} (id={$r['id']})\n";
    }
}

echo "\n总计更新: {$updated} 个\n";
echo "完成!\n";
