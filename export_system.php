<?php
/**
 * 大型系统 BOM — Excel 导出
 */
define('PMS_ENTRY', true);
require_once __DIR__ . '/includes/bootstrap.php';
if (!PMS_INSTALLED) { header('Location: /install.php'); exit; }
requireLogin();
requireCostView();

$projectId = (int)($_GET['id'] ?? 0);
if ($projectId <= 0) { header('Location: /systems.php'); exit; }

$project = SystemProject::find($projectId);
if (!$project) { header('Location: /systems.php'); exit; }

$modules = SystemProject::fullBom($projectId);

$filename = $project['name'] . '_BOM_' . date('Ymd') . '.xls';
header('Content-Type: application/vnd.ms-excel; charset=utf-8');
header('Content-Disposition: attachment; filename="' . rawurlencode($filename) . '"');
header('Cache-Control: max-age=0');
echo "\xEF\xBB\xBF";
?>
<!DOCTYPE html>
<html><head><meta charset="UTF-8"></head><body>
<h2>大型系统 BOM — <?= h($project['name']) ?></h2>
<p>编制日期：<?= date('Y 年 m 月 d 日') ?> | 状态：<?= $project['status'] == 1 ? '在建' : '完工' ?></p>

<?php $grandTotal = 0; foreach ($modules as $mod): $moduleTotal = 0; ?>
<table border="1" style="margin-bottom:20px">
    <tr style="background:#e2e8f0">
        <th colspan="7" style="text-align:left">模块：<?= h($mod['name']) ?></th>
    </tr>
    <tr>
        <th>序号</th><th>物料名称</th><th>规格型号</th><th>单位</th><th>数量</th><th>单价</th><th>金额小计</th>
    </tr>
    <?php $seq = 1; foreach ($mod['items'] as $it):
        $name = $it['item_name'] ?? ($it['product_name'] ?? ($it['sp_name'] ?? '-'));
        $spec = $it['spec'] ?? '';
        $unit = $it['unit'] ?? '';
        $qty = (float)$it['quantity'];
        $price = (float)$it['unit_price'];
        $subtotal = $qty * $price;
        $moduleTotal += $subtotal;
    ?>
    <tr>
        <td align="center"><?= $seq++ ?></td>
        <td><?= h($name) ?></td>
        <td><?= h($spec) ?></td>
        <td align="center"><?= h($unit) ?></td>
        <td align="right"><?= number_format($qty, 4) ?></td>
        <td align="right"><?= number_format($price, 2) ?></td>
        <td align="right"><?= number_format($subtotal, 2) ?></td>
    </tr>
    <?php if (!empty($it['sub_items'])): ?>
        <?php foreach ($it['sub_items'] as $sub):
            $sname = $sub['item_name'] ?? ($sub['product_name'] ?? ($sub['sp_name'] ?? '-'));
            $sspec = $sub['spec'] ?? '';
            $sunit = $sub['unit'] ?? '';
            $sqty = (float)$sub['quantity'];
            $sprice = (float)$sub['unit_price'];
            $ssub = $sqty * $sprice;
            $moduleTotal += $ssub;
        ?>
        <tr>
            <td align="center"><?= $seq++ ?></td>
            <td style="padding-left:20px;color:#64748b">　└ <?= h($sname) ?></td>
            <td style="color:#64748b"><?= h($sspec) ?></td>
            <td align="center"><?= h($sunit) ?></td>
            <td align="right"><?= number_format($sqty, 4) ?></td>
            <td align="right"><?= number_format($sprice, 2) ?></td>
            <td align="right"><?= number_format($ssub, 2) ?></td>
        </tr>
        <?php endforeach; ?>
    <?php endif; ?>
    <?php endforeach; ?>
    <tr style="font-weight:bold;background:#f1f5f9">
        <td colspan="6" align="right">模块小计</td>
        <td align="right"><?= number_format($moduleTotal, 2) ?></td>
    </tr>
</table>
<?php $grandTotal += $moduleTotal; endforeach; ?>

<table border="1">
    <tr style="background:#dbeafe;font-weight:bold;font-size:14px">
        <td colspan="6" align="right">系统总成本</td>
        <td align="right"><?= number_format($grandTotal, 2) ?></td>
    </tr>
</table>
</body></html>
