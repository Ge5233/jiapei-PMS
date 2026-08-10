<?php
/**
 * API: 供应商列表
 */
define('PMS_ENTRY', true);
require_once __DIR__ . '/../includes/bootstrap.php';
if (!PMS_INSTALLED) { jsonResponse(['ok' => false]); }
requireLogin();

$suppliers = Supplier::allActive();
$list = [];
foreach ($suppliers as $s) {
    $label = $s['name'] . ($s['contact'] ? '（' . h($s['contact']) . '）' : '');
    $list[] = ['id' => (int)$s['id'], 'name' => $label];
}
jsonResponse(['ok' => true, 'suppliers' => $list]);
