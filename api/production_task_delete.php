<?php
/**
 * API: 生产任务单删除
 */
define('PMS_ENTRY', true);
require_once __DIR__ . '/../includes/bootstrap.php';
if (!PMS_INSTALLED) { jsonResponse(['ok' => false, 'message' => '系统未安装']); }
requireLogin();
requireCostView();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['ok' => false, 'message' => 'Method not allowed'], 405);
}
verifyCsrf();

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) jsonResponse(['ok' => false, 'message' => '无效ID']);

$task = ProductionTask::find($id);
if (!$task) jsonResponse(['ok' => false, 'message' => '任务单不存在']);

try {
    ProductionTask::delete($id);
    logAction('delete', 'production_task', $id, '删除生产任务单 ' . $task['task_no']);
    jsonResponse(['ok' => true]);
} catch (\Throwable $e) {
    jsonResponse(['ok' => false, 'message' => '删除失败：' . $e->getMessage()]);
}
