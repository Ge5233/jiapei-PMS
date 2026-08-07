<?php
/**
 * 供应商删除接口
 */
define('PMS_ENTRY', true);
require_once __DIR__ . '/../includes/bootstrap.php';
if (!PMS_INSTALLED) { http_response_code(404); exit; }
requireLogin();
if (!isAdmin()) { http_response_code(403); exit('Forbidden'); }

verifyCsrf();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    flash('error', '参数错误');
    header('Location: /supplier.php');
    exit;
}

$supplier = Supplier::find($id);
if (!$supplier) {
    flash('error', '供应商不存在');
    header('Location: /supplier.php');
    exit;
}

try {
    Supplier::delete($id);
    Log::record('delete', 'supplier', $id, "删除供应商：{$supplier['name']}");
    flash('success', '供应商已删除');
} catch (Throwable $e) {
    flash('error', $e->getMessage());
}

header('Location: /supplier.php');
exit;
