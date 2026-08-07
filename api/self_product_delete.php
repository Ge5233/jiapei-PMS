<?php
/**
 * API: 自产产品 删除（级联删 BOM + 图片）
 */
define('PMS_ENTRY', true);
require_once __DIR__ . '/../includes/bootstrap.php';
if (!PMS_INSTALLED) { jsonResponse(['ok' => false, 'message' => '系统未安装'], 400); }
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['ok' => false, 'message' => 'Method not allowed'], 405);
}
verifyCsrf();

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$id = (int)($input['id'] ?? 0);

if ($id <= 0) {
    jsonResponse(['ok' => false, 'message' => '无效的产品 ID']);
}

$product = SelfProduct::find($id);
if (!$product) {
    jsonResponse(['ok' => false, 'message' => '产品不存在']);
}

try {
    SelfProduct::delete($id);
    logAction('delete', 'self_product', $id, "删除自产产品：{$product['name']}");
    jsonResponse(['ok' => true, 'message' => '已删除']);
} catch (Throwable $e) {
    jsonResponse(['ok' => false, 'message' => '删除失败：' . $e->getMessage()]);
}
