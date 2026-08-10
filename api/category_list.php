<?php
/**
 * API: 分类列表（给前端 refresh 用）
 */
define('PMS_ENTRY', true);
require_once __DIR__ . '/../includes/bootstrap.php';
if (!PMS_INSTALLED) { jsonResponse(['ok' => false]); }
requireLogin();

$cats = Category::allGrouped();
$list = [];
foreach ($cats as $c) {
    $item = ['id' => (int)$c['id'], 'name' => $c['name'], 'parent_sort_id' => (int)$c['parent_sort_id']];
    $item['children'] = [];
    foreach (($c['children'] ?? []) as $sub) {
        $item['children'][] = ['id' => (int)$sub['id'], 'name' => $sub['name'], 'parent_sort_id' => (int)($sub['parent_sort_id'] ?? 0), 'sub_id' => (int)($sub['sub_id'] ?? 0)];
    }
    $list[] = $item;
}
jsonResponse(['ok' => true, 'categories' => $list]);
