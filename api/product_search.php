<?php
/**
 * API: 产品名相似查询
 */
define('PMS_ENTRY', true);
require_once __DIR__ . '/../includes/bootstrap.php';
if (!PMS_INSTALLED) { jsonResponse(['ok' => false, 'message' => 'Not installed']); }
requireLogin();

$q = trim($_GET['q'] ?? '');
if (mb_strlen($q) < 1) { jsonResponse(['ok' => false, 'matches' => []]); }

$db = Database::getInstance();
$stmt = $db->prepare("SELECT p.id, p.sku, p.name, p.spec, p.unit FROM products p WHERE p.name LIKE ? ORDER BY p.name, p.sku LIMIT 8");
$stmt->execute(['%' . $q . '%']);
$rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

$matches = [];
foreach ($rows as $r) {
    $matches[] = ['id' => (int)$r['id'], 'sku' => $r['sku'], 'name' => $r['name'], 'spec' => $r['spec'] ?: '', 'unit' => $r['unit'] ?: ''];
}

jsonResponse(['ok' => true, 'count' => count($matches), 'matches' => $matches]);
