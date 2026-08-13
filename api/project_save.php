<?php
/**
 * API: 项目保存（新增/编辑 + 项目产品）
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

$action = $_POST['action'] ?? 'save';

try {
    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) throw new \RuntimeException('无效ID');
        Project::delete($id);
        logAction('delete', 'project', $id, '删除项目');
        jsonResponse(['ok' => true]);
    }

    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $name = trim($_POST['name'] ?? '');
    if ($name === '') throw new \RuntimeException('项目名称不能为空');

    if ($id > 0) {
        Project::update($id, [
            'name' => $name,
            'customer_name' => $_POST['customer_name'] ?? null,
            'status' => $_POST['status'] ?? 'active',
            'remark' => $_POST['remark'] ?? null,
        ]);
        $actionLabel = '编辑';
    } else {
        $id = Project::create([
            'name' => $name,
            'customer_name' => $_POST['customer_name'] ?? null,
            'status' => $_POST['status'] ?? 'active',
            'remark' => $_POST['remark'] ?? null,
            'created_by' => $_SESSION['user_id'] ?? null,
        ]);
        $actionLabel = '创建';
    }

    // 项目产品
    $itemsJson = $_POST['items'] ?? '';
    if ($itemsJson !== '') {
        $items = json_decode($itemsJson, true);
        if (is_array($items)) {
            Project::saveProducts($id, $items);
        }
    }

    logAction($id > 0 && $actionLabel === '编辑' ? 'update' : 'create', 'project', $id, "{$actionLabel}项目：{$name}");
    jsonResponse(['ok' => true, 'id' => $id]);
} catch (\Throwable $e) {
    jsonResponse(['ok' => false, 'message' => $e->getMessage()]);
}
