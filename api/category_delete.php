<?php
/**
 * API: 分类删除
 */
define('PMS_ENTRY', true);
require_once __DIR__ . '/../includes/bootstrap.php';
if (!PMS_INSTALLED) { header('Location: /install.php'); exit; }
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /categories.php');
    exit;
}
verifyCsrf();

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    flash('error', '无效的 ID');
    header('Location: /categories.php');
    exit;
}

$cat = Category::find($id);
if (!$cat) {
    flash('error', '分类不存在');
    header('Location: /categories.php');
    exit;
}

try {
    $name = $cat['name'];
    
    // 获取该分类下的产品列表（用于记录日志）
    $stmt = Database::getInstance()->prepare("SELECT id, name FROM products WHERE category_id = ?");
    $stmt->execute([$id]);
    $products = $stmt->fetchAll();
    
    Category::delete($id);
    
    // 记录分类删除日志
    logAction('delete', 'category', $id, "删除分类：{$name}");
    
    // 记录每个产品的移动日志
    foreach ($products as $product) {
        logAction('update', 'product', $product['id'], "产品[{$product['name']}]因分类[{$name}]删除，自动移动到[未分类]");
    }
    
    flash('success', '分类已删除' . (count($products) > 0 ? '，' . count($products) . ' 个产品已移动到"未分类"' : ''));
} catch (Throwable $e) {
    flash('error', '删除失败：' . $e->getMessage());
}

header('Location: /categories.php');
exit;
