<?php
/**
 * Excel 导出（HTML 表格伪装 .xls，零依赖）
 * 支持按当前筛选条件导出
 */
define('PMS_ENTRY', true);
require_once __DIR__ . '/includes/bootstrap.php';
if (!PMS_INSTALLED) { header('Location: /install.php'); exit; }
requireLogin();

$keyword = trim($_GET['keyword'] ?? '');
$categoryId = (int)($_GET['category_id'] ?? 0);
$status = $_GET['status'] ?? '';

$conditions = [];
$params = [];
if ($keyword !== '') {
    $conditions[] = '(p.name LIKE :kw OR p.sku LIKE :kw)';
    $params[':kw'] = '%' . $keyword . '%';
}
if ($categoryId > 0) {
    // 包含子分类
    $subIds = Category::descendantIds($categoryId);
    $subIds[] = $categoryId;
    $placeholders = [];
    foreach ($subIds as $i => $sid) {
        $ph = ":cat_$i";
        $placeholders[] = $ph;
        $params[$ph] = $sid;
    }
    $conditions[] = 'p.category_id IN (' . implode(',', $placeholders) . ')';
}
if ($status !== '' && in_array($status, ['0', '1'], true)) {
    $conditions[] = 'p.status = :status';
    $params[':status'] = (int)$status;
}

$where = $conditions ? ('WHERE ' . implode(' AND ', $conditions)) : '';
$sql = "SELECT p.*, c.name AS category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id $where ORDER BY p.id DESC";
$rows = db()->prepare($sql);
$rows->execute($params);
$products = $rows->fetchAll();

$filename = 'products_' . date('Ymd_His') . '.xls';
logAction('export', 'product', null, "导出产品列表（共 " . count($products) . " 条）");

// BOM 防止中文乱码
header('Content-Type: application/vnd.ms-excel; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');
?>
<html>
<head>
<meta charset="utf-8">
<style>
table { border-collapse: collapse; }
th, td { border: 1px solid #999; padding: 6px 10px; font-size: 12px; }
th { background: #f0f0f0; font-weight: bold; }
.num { text-align: right; }
</style>
</head>
<body>
<table>
<thead>
<tr>
    <th>ID</th>
    <th>SKU</th>
    <th>产品名称</th>
    <th>分类</th>
    <th>规格</th>
    <th>单位</th>
    <th>综合进价(¥)</th>
    <th>指导售价(¥)</th>
    <th>最低折扣</th>
    <th>毛利率(%)</th>
    <th>状态</th>
    <th>备注</th>
    <th>创建时间</th>
    <th>更新时间</th>
</tr>
</thead>
<tbody>
<?php foreach ($products as $p):
    $margin = ((float)$p['guide_price'] > 0)
        ? round(((float)$p['guide_price'] - (float)$p['cost_price']) / (float)$p['guide_price'] * 100, 2)
        : 0;
?>
<tr>
    <td class="num"><?= (int)$p['id'] ?></td>
    <td><?= h($p['sku']) ?></td>
    <td><?= h($p['name']) ?></td>
    <td><?= h($p['category_name'] ?? '-') ?></td>
    <td><?= h($p['spec'] ?? '') ?></td>
    <td><?= h($p['unit'] ?? '') ?></td>
    <td class="num"><?= number_format((float)$p['cost_price'], 2) ?></td>
    <td class="num"><?= number_format((float)$p['guide_price'], 2) ?></td>
    <td class="num"><?= number_format((float)$p['min_discount'], 2) ?></td>
    <td class="num"><?= $margin ?></td>
    <td><?= (int)$p['status'] === 1 ? '上架' : '下架' ?></td>
    <td><?= h($p['remark'] ?? '') ?></td>
    <td><?= h($p['created_at']) ?></td>
    <td><?= h($p['updated_at']) ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</body>
</html>
