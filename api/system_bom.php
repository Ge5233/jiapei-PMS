<?php
/**
 * API: 获取系统项目完整 BOM
 */
define('PMS_ENTRY', true);
require_once __DIR__ . '/../includes/bootstrap.php';
if (!PMS_INSTALLED) { jsonResponse(['ok' => false, 'message' => '未安装']); }
requireLogin();
requireCostView();

$projectId = (int)($_GET['project_id'] ?? 0);
if ($projectId <= 0) { jsonResponse(['ok' => false, 'message' => '无效项目ID']); }

$modules = SystemProject::fullBom($projectId);
jsonResponse(['ok' => true, 'modules' => $modules]);
