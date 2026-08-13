<?php
/**
 * API: 从项目生成生产任务单
 * POST project_id + requirementMap（可选，项目产品ID → 需求说明）
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

$projectId = (int)($_POST['project_id'] ?? 0);
if ($projectId <= 0) jsonResponse(['ok' => false, 'message' => '无效项目ID']);

$requirementMap = [];
$reqJson = $_POST['requirements'] ?? '';
if ($reqJson !== '') {
    $req = json_decode($reqJson, true);
    if (is_array($req)) $requirementMap = $req;
}

try {
    $ids = ProductionTask::generateFromProject($projectId, $requirementMap);
    if (empty($ids)) {
        jsonResponse(['ok' => false, 'message' => '该项目没有自产产品，无法生成生产任务']);
    }
    logAction('create', 'production_task', 0, "从项目{$projectId}生成 " . count($ids) . " 条生产任务");
    jsonResponse(['ok' => true, 'count' => count($ids), 'ids' => $ids]);
} catch (\Throwable $e) {
    jsonResponse(['ok' => false, 'message' => '生成失败：' . $e->getMessage()]);
}
