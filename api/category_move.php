<?php
/**
 * API: 分类移动（改变父级）
 * 已禁用：移动子分类会影响 SKU，不允许移动
 */
define('PMS_ENTRY', true);
require_once __DIR__ . '/../includes/bootstrap.php';
if (!PMS_INSTALLED) { header('Location: /install.php'); exit; }
requireAdmin();

// 禁止移动操作
flash('error', '不允许移动分类：移动子分类会影响产品 SKU，如需调整分类结构请删除后重建');
header('Location: /categories.php');
exit;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /categories.php');
    exit;
}
verifyCsrf();

$id = (int)($_POST['id'] ?? 0);
$newParentId = (int)($_POST['parent_id'] ?? $_POST['new_parent_id'] ?? 0);

if ($id <= 0 || $newParentId <= 0) {
    flash('error', '参数错误');
    header('Location: /categories.php');
    exit;
}

$cat = Category::find($id);
if (!$cat) {
    flash('error', '分类不存在');
    header('Location: /categories.php');
    exit;
}
// 只能移动二级分类
if ((int)$cat['parent_id'] === 0) {
    flash('error', '只能移动二级分类');
    header('Location: /categories.php');
    exit;
}
// 新父级必须是一级分类
$newParent = Category::find($newParentId);
if (!$newParent || (int)$newParent['parent_id'] !== 0) {
    flash('error', '目标父级不是一级分类');
    header('Location: /categories.php');
    exit;
}
if ((int)$newParent['id'] === (int)$cat['parent_id']) {
    // 没变
    header('Location: /categories.php');
    exit;
}

try {
    $oldName = $cat['name'];
    $oldParentName = Category::find((int)$cat['parent_id'])['name'] ?? '?';
    Category::moveTo($id, $newParentId);
    logAction('move', 'category', $id, "移动分类「{$oldName}」：{$oldParentName} → {$newParent['name']}");
    flash('success', '分类已移动到「' . $newParent['name'] . '」');
} catch (Throwable $e) {
    flash('error', '移动失败：' . $e->getMessage());
}

header('Location: /categories.php');
exit;
