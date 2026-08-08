<?php
/**
 * 报价单打印页（客户版，隐藏成本）
 */
define('PMS_ENTRY', true);
require_once __DIR__ . '/includes/bootstrap.php';
if (!PMS_INSTALLED) exit;
requireLogin();

$id = (int)($_GET['id'] ?? 0);
$quote = Quote::find($id);
if (!$quote) { echo '报价单不存在'; exit; }
$items = Quote::getItems($id);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= h($quote['quote_no']) ?> - 佳培科技报价单</title>
<script src="https://cdn.tailwindcss.com"></script>
<style>@media print{body{print-color-adjust:exact;-webkit-print-color-adjust:exact}.no-print{display:none!important}}</style>
</head>
<body class="bg-white text-slate-800 print:bg-white">
<div class="max-w-4xl mx-auto p-8 print:p-4">
    <!-- 打印按钮 -->
    <div class="no-print mb-4 text-right">
        <button onclick="window.print()" class="px-4 py-2 bg-blue-600 text-white rounded text-sm hover:bg-blue-700">打印 / 导出 PDF</button>
        <a href="/quote_edit.php?id=<?=$id?>" class="ml-2 px-4 py-2 border rounded text-sm">返回编辑</a>
    </div>

    <!-- 公司头 -->
    <div class="border-b-2 border-blue-600 pb-4 mb-6">
        <div class="text-2xl font-bold text-blue-700">无锡佳培科技有限公司</div>
        <div class="text-sm text-slate-500 mt-1">Wuxi igrowths Technology Co., Ltd.</div>
    </div>

    <!-- 报价单标题 -->
    <div class="text-center mb-6">
        <h1 class="text-xl font-bold">报 价 单</h1>
        <div class="text-sm text-slate-500 mt-1">编号：<?= h($quote['quote_no']) ?></div>
    </div>

    <!-- 客户信息 -->
    <div class="grid grid-cols-2 gap-4 mb-6 text-sm">
        <div>
            <div class="mb-2"><span class="text-slate-500">项目名称：</span><span class="font-medium"><?= h($quote['project_name']) ?></span></div>
            <div><span class="text-slate-500">客户名称：</span><span class="font-medium"><?= h($quote['customer_name'] ?: '-') ?></span></div>
        </div>
        <div class="text-right">
            <div class="mb-2"><span class="text-slate-500">日期：</span><?= date('Y-m-d', strtotime($quote['created_at'])) ?></div>
            <?php if ($quote['valid_until']): ?>
            <div><span class="text-slate-500">有效期至：</span><?= $quote['valid_until'] ?></div>
            <?php endif; ?>
        </div>
    </div>

    <!-- 明细表 -->
    <table class="w-full border-collapse mb-6 text-sm">
        <thead>
            <tr class="border-y-2 border-slate-300 bg-slate-50">
                <th class="px-3 py-2 text-left w-8">#</th>
                <th class="px-3 py-2 text-left">产品名称 / 规格</th>
                <th class="px-3 py-2 text-right w-20">数量</th>
                <th class="px-3 py-2 text-center w-16">单位</th>
                <th class="px-3 py-2 text-right w-28">单价</th>
                <th class="px-3 py-2 text-right w-28">小计</th>
            </tr>
        </thead>
        <tbody>
            <?php $grandTotal = 0; ?>
            <?php foreach ($items as $i => $item):
                $name = $item['item_name'] ?: ($item['product_name'] ?: ($item['self_product_name'] ?: '未命名'));
                $spec = $item['spec'] ?? '';
                $qty = (float)$item['quantity'];
                $uprice = (float)$item['unit_price'];
                $disc = (float)$item['discount'];
                $ltotal = $qty * $uprice * $disc;
                $grandTotal += $ltotal;
            ?>
            <tr class="border-b border-slate-200">
                <td class="px-3 py-2"><?= $i+1 ?></td>
                <td class="px-3 py-2">
                    <div class="font-medium"><?= h($name) ?></div>
                    <?php if ($spec): ?><div class="text-xs text-slate-400"><?= h($spec) ?></div><?php endif; ?>
                </td>
                <td class="px-3 py-2 text-right tabular-nums"><?= rtrim(rtrim(number_format($qty,4),'0'),'.') ?></td>
                <td class="px-3 py-2 text-center"><?= h($item['unit'] ?: '套') ?></td>
                <td class="px-3 py-2 text-right tabular-nums">¥<?= number_format($uprice,2) ?></td>
                <td class="px-3 py-2 text-right tabular-nums">¥<?= number_format($ltotal,2) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- 汇总 -->
    <div class="flex justify-end mb-6">
        <div class="w-64 text-sm">
            <?php
            $sub = $grandTotal;
            $taxRate = (float)$quote['tax_rate'];
            $tax = $sub * $taxRate;
            $total = $sub + $tax;
            ?>
            <div class="flex justify-between py-1"><span class="text-slate-500">小计</span><span class="tabular-nums">¥<?= number_format($sub,2) ?></span></div>
            <div class="flex justify-between py-1"><span class="text-slate-500">税费（<?= ($taxRate*100) ?>%）</span><span class="tabular-nums">¥<?= number_format($tax,2) ?></span></div>
            <div class="flex justify-between py-2 border-t-2 border-slate-300 font-bold text-base"><span>合计</span><span class="text-blue-700">¥<?= number_format($total,2) ?></span></div>
        </div>
    </div>

    <!-- 商务条款 -->
    <?php if ($quote['payment_terms'] || $quote['warranty'] || $quote['delivery_period']): ?>
    <div class="border-t border-slate-200 pt-4 mb-6 text-sm">
        <h3 class="font-medium mb-2">商务条款</h3>
        <div class="grid grid-cols-2 gap-2">
            <?php if ($quote['payment_terms']): ?><div><span class="text-slate-500">付款方式：</span><?= h($quote['payment_terms']) ?></div><?php endif; ?>
            <?php if ($quote['warranty']): ?><div><span class="text-slate-500">质保：</span><?= h($quote['warranty']) ?></div><?php endif; ?>
            <?php if ($quote['delivery_period']): ?><div><span class="text-slate-500">交期：</span><?= h($quote['delivery_period']) ?></div><?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>
</body>
</html>
