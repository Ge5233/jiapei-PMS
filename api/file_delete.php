<?php
/**
 * API: 文件删除
 */
define('PMS_ENTRY', true);
require_once __DIR__ . '/../includes/bootstrap.php';
if (!PMS_INSTALLED) { header('Location: /install.php'); exit; }
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /products.php');
    exit;
}
verifyCsrf();

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    flash('error', '无效的文件 ID');
    header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/products.php'));
    exit;
}

$file = FileModel::find($id);
if (!$file) {
    flash('error', '文件不存在');
    header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/products.php'));
    exit;
}

try {
    $storedName = FileModel::delete($id);
    if ($storedName) {
        $path = __DIR__ . '/../uploads/' . $storedName;
        if (is_file($path)) @unlink($path);
    }
    logAction('delete', 'product_file', $id, "删除文件：{$file['original_name']}");
    flash('success', '文件已删除');
} catch (Throwable $e) {
    flash('error', '删除失败：' . $e->getMessage());
}

$referer = $_SERVER['HTTP_REFERER'] ?? '';
if (strpos($referer, 'product_edit.php') !== false) {
    $pid = (int)$file['product_id'];
    header("Location: /product_edit.php?id={$pid}");
} else {
    header('Location: /products.php');
}
exit;
