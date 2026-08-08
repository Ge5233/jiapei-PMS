<?php
/**
 * BOM 物料清单 Excel 导出
 */
define('PMS_ENTRY', true);
require_once __DIR__ . '/includes/bootstrap.php';
if (!PMS_INSTALLED) { header('Location: /install.php'); exit; }
requireLogin();
requireCostView();

$spId = (int)($_GET['self_product_id'] ?? 0);
if ($spId <= 0) { header('Location: /self_products.php'); exit; }

$sp = SelfProduct::find($spId);
if (!$sp) { header('Location: /self_products.php'); exit; }

$items = SelfProduct::getBom($spId);

$filename = 'BOM_' . date('Ymd_His') . '.xls';
header('Content-Type: application/vnd.ms-excel; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');
echo "\xEF\xBB\xBF"; // BOM
?>
<!DOCTYPE html>
<html><head><meta charset="UTF-8"></head><body>
<h2>BOM 物料清单 — <?= h($sp['name']) ?></h2>
<table border="1">
<tr>
    <th>序号</th><th>物料名称</th><th>规格型号</th><th>数量</th><th>单位</th><th>综合进价</th><th>金额小计</th>
</tr>
<?php $totalCost = 0; ?>
<?php foreach ($items as $i => $row):
    $qty = (float)($row['quantity'] ?? 1);
    $cost = $row['product_id'] ? (float)($row['product_cost_price'] ?? 0) : (float)($row['unit_cost'] ?? 0);
    $lineTotal = $qty * $cost;
    $totalCost += $lineTotal;
?>
<tr>
    <td><?= $i + 1 ?></td>
    <td><?= h($row['product_name'] ?? '') ?></td>
    <td><?= h($row['spec'] ?? '') ?></td>
    <td><?= number_format($qty, 2) ?></td>
    <td><?= h($row['unit'] ?? '套') ?></td>
    <td>¥<?= number_format($cost, 2) ?></td>
    <td>¥<?= number_format($lineTotal, 2) ?></td>
</tr>
<?php endforeach; ?>
<tr><td colspan="6" align="right"><b>材料成本合计</b></td><td><b>¥<?= number_format($totalCost, 2) ?></b></td></tr>
</table>
</body></html>
