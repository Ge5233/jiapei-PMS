<?php
/**
 * API: 分类重排序
 */
define('PMS_ENTRY', true);
require_once __DIR__ . '/../includes/bootstrap.php';
if (!PMS_INSTALLED) { jsonResponse(['ok' => false, 'message' => '未安装']); }
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['ok' => false, 'message' => 'Method not allowed'], 405);
}
verifyCsrf();

$ids = $_POST['ids'] ?? [];
if (!is_array($ids) || empty($ids)) {
    jsonResponse(['ok' => false, 'message' => '无 ID']);
}
$ids = array_map('intval', $ids);

try {
    Category::reorder($ids);
    logAction('reorder', 'category', null, '重新排序分类：' . implode(',', $ids));
    jsonResponse(['ok' => true]);
} catch (Throwable $e) {
    jsonResponse(['ok' => false, 'message' => $e->getMessage()]);
}
