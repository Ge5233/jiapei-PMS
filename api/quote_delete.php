<?php
/**
 * API: 报价单 删除
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
if ($id <= 0) jsonResponse(['ok' => false, 'message' => '无效ID']);

$quote = Quote::find($id);
if (!$quote) jsonResponse(['ok' => false, 'message' => '报价单不存在']);

try {
    Quote::delete($id);
    logAction('delete', 'quote', $id, "删除报价单：{$quote['project_name']}");
    jsonResponse(['ok' => true, 'message' => '已删除']);
} catch (Throwable $e) {
    jsonResponse(['ok' => false, 'message' => '删除失败：' . $e->getMessage()]);
}
