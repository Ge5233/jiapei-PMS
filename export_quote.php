<?php
/**
 * 报价单 Excel 导出（整单报价 + 明细）
 */
define('PMS_ENTRY', true);
require_once __DIR__ . '/includes/bootstrap.php';
if (!PMS_INSTALLED) { header('Location: /install.php'); exit; }
requireLogin();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { header('Location: /quotes.php'); exit; }

$quote = Quote::find($id);
if (!$quote) { header('Location: /quotes.php'); exit; }
$items = Quote::getItems($id);

$filename = '报价单_' . $quote['quote_no'] . '_' . date('Ymd') . '.xls';
header('Content-Type: application/vnd.ms-excel; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');
echo "\xEF\xBB\xBF";
?>
<!DOCTYPE html>
<html><head><meta charset="UTF-8"></head><body>
<table>
<tr><td><b>佳培科技有限公司</b></td></tr>
<tr><td>&nbsp;</td></tr>
<tr><td><b>报价单</b></td><td>编号：<?= h($quote['quote_no']) ?></td></tr>
<tr><td>项目名称：<?= h($quote['project_name']) ?></td><td>日期：<?= date('Y-m-d', strtotime($quote['created_at'])) ?></td></tr>
<tr><td>客户名称：<?= h($quote['customer_name']) ?></td><td>有效期：<?= h($quote['valid_until'] ?: '—') ?></td></tr>
</table>
<br>
<table border="1">
<tr>
    <th>#</th><th>产品名称</th><th>规格</th><th>数量</th><th>单位</th><th>单价</th><th>折扣</th><th>小计</th>
</tr>
<?php $subtotal = 0; ?>
<?php foreach ($items as $i => $item):
    $name = $item['item_name'] ?: ($item['product_name'] ?: ($item['self_product_name'] ?: '未命名'));
    $qty = (float)$item['quantity'];
    $uprice = (float)$item['unit_price'];
    $disc = (float)$item['discount'];
    $ltotal = $qty * $uprice * $disc;
    $subtotal += $ltotal;
?>
<tr>
    <td><?= $i + 1 ?></td>
    <td><?= h($name) ?></td>
    <td><?= h($item['spec'] ?? '') ?></td>
    <td><?= number_format($qty, 4) ?></td>
    <td><?= h($item['unit'] ?: '套') ?></td>
    <td>¥<?= number_format($uprice, 2) ?></td>
    <td><?= round($disc * 100) ?>%</td>
    <td>¥<?= number_format($ltotal, 2) ?></td>
</tr>
<?php endforeach; ?>
</table>
<br>
<table>
<tr><td>小计：</td><td>¥<?= number_format($subtotal, 2) ?></td></tr>
<tr><td>税费（<?= round($quote['tax_rate'] * 100) ?>%）：</td><td>¥<?= number_format($subtotal * $quote['tax_rate'], 2) ?></td></tr>
<tr><td><b>合计：</b></td><td><b>¥<?= number_format($subtotal * (1 + $quote['tax_rate']), 2) ?></b></td></tr>
</table>
<br>
<table>
<?php if ($quote['payment_terms']): ?><tr><td>付款方式：</td><td><?= h($quote['payment_terms']) ?></td></tr><?php endif; ?>
<?php if ($quote['warranty']): ?><tr><td>质保：</td><td><?= h($quote['warranty']) ?></td></tr><?php endif; ?>
</table>
</body></html>
