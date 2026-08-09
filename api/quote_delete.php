<?php
/**
 * API: 报价单 删除
 */
define('PMS_ENTRY', true);
require_once __DIR__ . '/../includes/bootstrap.php';
if (!PMS_INSTALLED) { header('Content-Type: application/json'); echo json_encode(['ok' => false, 'message' => '系统未安装']); exit; }
requireAdmin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'message' => 'Method not allowed']);
    exit;
}
verifyCsrf();

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$id = (int)($input['id'] ?? 0);
if ($id <= 0) { echo json_encode(['ok' => false, 'message' => '无效ID']); exit; }

$quote = Quote::find($id);
if (!$quote) { echo json_encode(['ok' => false, 'message' => '报价单不存在']); exit; }

try {
    Quote::delete($id);
    logAction('delete', 'quote', $id, "删除报价单：{$quote['project_name']}");
    echo json_encode(['ok' => true, 'message' => '已删除']);
} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'message' => '删除失败：' . $e->getMessage()]);
}
