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

    // 确认动作
    $action = $_POST['confirm'] ?? '';
    $statusMap = [
        'confirm_requirement' => 'requirement_confirmed', // 项目负责人确认需求
        'confirm_production' => 'confirmed',              // 产品经理确认生产
        'start_production' => 'in_production',            // 开始生产
        'finish_production' => 'done',                    // 生产完成
    ];
    if (isset($statusMap[$action])) {
        ProductionTask::updateStatus($id, $statusMap[$action]);
    }

    logAction('update', 'production_task', $id, '更新生产任务单 ' . $task['task_no']);
    $db->commit();
    jsonResponse(['ok' => true]);
} catch (\Throwable $e) {
    $db->rollBack();
    jsonResponse(['ok' => false, 'message' => '保存失败：' . $e->getMessage()]);
}
