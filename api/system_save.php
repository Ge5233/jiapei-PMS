<?php
/**
 * API: 大型系统保存
 */
define('PMS_ENTRY', true);
require_once __DIR__ . '/../includes/bootstrap.php';
if (!PMS_INSTALLED) { jsonResponse(['ok' => false, 'message' => '未安装']); }
requireLogin();
requireCostView();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['ok' => false, 'message' => 'Method not allowed'], 405);
}
verifyCsrf();

$action = $_POST['action'] ?? 'save';

try {
    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) throw new \RuntimeException('无效ID');
        SystemProject::delete($id);
        jsonResponse(['ok' => true]);
    }

    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $name = trim($_POST['name'] ?? '');
    if ($name === '') throw new \RuntimeException('名称不能为空');

    if ($id > 0) {
        SystemProject::update($id, [
            'name' => $name,
            'description' => $_POST['description'] ?? '',
            'status' => (int)($_POST['status'] ?? 1),
        ]);
    } else {
        $id = SystemProject::create([
            'name' => $name,
            'description' => $_POST['description'] ?? '',
            'status' => (int)($_POST['status'] ?? 1),
        ]);
    }

    // BOM 数据
    $bomJson = $_POST['bom'] ?? '';
    if ($bomJson) {
        $modules = json_decode($bomJson, true);
        if (is_array($modules)) {
            SystemProject::saveBom($id, $modules);
        }
    }

    jsonResponse(['ok' => true, 'id' => $id]);
} catch (\Throwable $e) {
    jsonResponse(['ok' => false, 'message' => $e->getMessage()]);
}
