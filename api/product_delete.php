<?php
/**
 * API: 产品删除
 */
define('PMS_ENTRY', true);
require_once __DIR__ . '/../includes/bootstrap.php';
if (!PMS_INSTALLED) { header('Location: /install.php'); exit; }
requireAdmin(); // 仅管理员可删除

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /products.php');
    exit;
}
verifyCsrf();

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    flash('error', '无效的产品 ID');
    header('Location: /products.php');
    exit;
}

$product = Product::find($id);
if (!$product) {
    flash('error', '产品不存在');
    header('Location: /products.php');
    exit;
}

try {
    Product::delete($id);
    logAction('delete', 'product', $id, "删除产品：{$product['name']}（SKU: {$product['sku']}）");
    flash('success', '产品已删除');
} catch (Throwable $e) {
    flash('error', '删除失败：' . $e->getMessage());
}

header('Location: /products.php');
exit;
