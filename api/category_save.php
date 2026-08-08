<?php
/**
 * API: 分类保存（新增/更新）
 */
define('PMS_ENTRY', true);
require_once __DIR__ . '/../includes/bootstrap.php';
if (!PMS_INSTALLED) { header('Location: /install.php'); exit; }
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /categories.php');
    exit;
}
verifyCsrf();

$id = (int)($_POST['id'] ?? 0);
$parentId = (int)($_POST['parent_id'] ?? 0);
$name = trim($_POST['name'] ?? '');
$gm = (float)($_POST['guide_margin_rate'] ?? 30.00);
$mm = (float)($_POST['min_margin_rate'] ?? 15.00);

if ($name === '') {
    flash('error', '分类名称必填');
    header('Location: /categories.php');
    exit;
}
if (mb_strlen($name) > 50) {
    flash('error', '分类名称最多 50 字');
    header('Location: /categories.php');
    exit;
}

// 不能把一级分类的 parent_id 改成非 0
if ($id > 0) {
    $existing = Category::find($id);
    if (!$existing) {
        flash('error', '分类不存在');
        header('Location: /categories.php');
        exit;
    }
    // 一级分类不能改成二级
    if ((int)$existing['parent_id'] === 0 && $parentId !== 0) {
        flash('error', '一级分类不能改为二级');
        header('Location: /categories.php');
        exit;
    }
    // 二级分类不能改 parent_id（要用"移动"功能）
    if ((int)$existing['parent_id'] !== 0 && $parentId !== (int)$existing['parent_id']) {
        flash('error', '请使用"移动"功能调整分类归属');
        header('Location: /categories.php');
        exit;
    }
}

try {
    if ($id > 0) {
        Category::update($id, $name, $parentId, (int)($existing['sort_order'] ?? 0), $gm, $mm);
        logAction('update', 'category', $id, "更新分类：{$name}");
    } else {
        // 排序到末尾
        $sort = 0;
        if ($parentId === 0) {
            $rows = Category::allLevel1();
        } else {
            $rows = Category::childrenOf($parentId);
        }
        $sort = count($rows);
        $newId = Category::create($name, $parentId, $sort, $gm, $mm);
        logAction('create', 'category', $newId, "创建分类：{$name}（parent=" . ($parentId ?: '无') . "）");
    }
    flash('success', '保存成功');
} catch (Throwable $e) {
    flash('error', '保存失败：' . $e->getMessage());
}

header('Location: /categories.php');
exit;
