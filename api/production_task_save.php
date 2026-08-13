<?php
/**
 * API: 生产任务单保存（改 BOM、确认）
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

$db = Database::getInstance();
$db->beginTransaction();
try {
    // 更新需求说明
    $db->prepare("UPDATE production_tasks SET requirement = ? WHERE id = ?")
       ->execute([trim($_POST['requirement'] ?? '') ?: null, $id]);

    // BOM
    $bomJson = $_POST['bom'] ?? '';
    if ($bomJson !== '') {
        $modules = json_decode($bomJson, true);
        if (is_array($modules)) {
            ProductionTask::saveBom($id, $modules);
        }
    }

    // 确认
    if (($_POST['confirm'] ?? '') === '1') {
        ProductionTask::updateStatus($id, 'confirmed');
    }

    logAction('update', 'production_task', $id, '更新生产任务单 ' . $task['task_no']);
    $db->commit();
    jsonResponse(['ok' => true]);
} catch (\Throwable $e) {
    $db->rollBack();
    jsonResponse(['ok' => false, 'message' => '保存失败：' . $e->getMessage()]);
}
