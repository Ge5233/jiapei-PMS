<?php
/**
 * 首页 - 仪表盘
 */
define('PMS_ENTRY', true);
require_once __DIR__ . '/includes/bootstrap.php';

if (!PMS_INSTALLED) {
    header('Location: /install.php');
    exit;
}
requireLogin();

$stats = Product::stats();
$recent = Product::recentUpdated(5);
$supplierCount = Supplier::count();

$pageTitle = '首页';
$activeMenu = 'dashboard';
require __DIR__ . '/includes/views/header.php';
?>

<div class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-6">
    <div class="card p-5">
        <div class="flex items-center justify-between">
            <div>
                <div class="text-sm text-slate-500">产品总数</div>
                <div class="text-2xl font-semibold text-slate-800 mt-1 tabular-nums"><?= $stats['total'] ?></div>
            </div>
            <div class="w-10 h-10 bg-blue-50 rounded-lg flex items-center justify-center">
                <i data-lucide="package" class="w-5 h-5 text-blue-600"></i>
            </div>
        </div>
    </div>
    <div class="card p-5">
        <div class="flex items-center justify-between">
            <div>
                <div class="text-sm text-slate-500">上架中</div>
                <div class="text-2xl font-semibold text-green-600 mt-1 tabular-nums"><?= $stats['on_sale'] ?></div>
            </div>
            <div class="w-10 h-10 bg-green-50 rounded-lg flex items-center justify-center">
                <i data-lucide="check-circle" class="w-5 h-5 text-green-600"></i>
            </div>
        </div>
    </div>
    <div class="card p-5">
        <div class="flex items-center justify-between">
            <div>
                <div class="text-sm text-slate-500">已下架</div>
                <div class="text-2xl font-semibold text-slate-500 mt-1 tabular-nums"><?= $stats['off_sale'] ?></div>
            </div>
            <div class="w-10 h-10 bg-slate-100 rounded-lg flex items-center justify-center">
                <i data-lucide="archive" class="w-5 h-5 text-slate-500"></i>
            </div>
        </div>
    </div>
    <a href="/supplier.php" class="card p-5 hover:border-blue-200 transition-colors">
        <div class="flex items-center justify-between">
            <div>
                <div class="text-sm text-slate-500">供应商</div>
                <div class="text-2xl font-semibold text-purple-600 mt-1 tabular-nums"><?= $supplierCount ?></div>
            </div>
            <div class="w-10 h-10 bg-purple-50 rounded-lg flex items-center justify-center">
                <i data-lucide="truck" class="w-5 h-5 text-purple-600"></i>
            </div>
        </div>
    </a>
    <div class="card p-5">
        <div class="flex items-center justify-between">
            <div>
                <div class="text-sm text-slate-500">平均毛利率</div>
                <div class="text-2xl font-semibold mt-1 tabular-nums <?= marginClass($stats['avg_margin']) ?>">
                    <?= number_format($stats['avg_margin'], 2) ?>%
                </div>
            </div>
            <div class="w-10 h-10 bg-amber-50 rounded-lg flex items-center justify-center">
                <i data-lucide="trending-up" class="w-5 h-5 text-amber-600"></i>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header flex items-center justify-between">
        <span>最近修改</span>
        <a href="/products.php" class="text-sm text-blue-600 hover:text-blue-700">查看全部 →</a>
    </div>
    <?php if (empty($recent)): ?>
        <div class="card-body text-center text-slate-400 py-12">
            <i data-lucide="inbox" class="w-10 h-10 mx-auto mb-2"></i>
            <p>暂无产品</p>
            <a href="/product_edit.php" class="btn btn-primary mt-4 inline-flex">
                <i data-lucide="plus" class="w-4 h-4 mr-1.5"></i>新增产品
            </a>
        </div>
    <?php else: ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>SKU</th>
                    <th>名称</th>
                    <th>分类</th>
                    <th>供应商</th>
                    <th class="text-right">进价</th>
                    <th class="text-right">售价</th>
                    <th class="text-right">毛利率</th>
                    <th>更新时间</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recent as $p):
                    $m = calcMargin((float)$p['cost_price'], (float)$p['guide_price']);
                ?>
                    <tr>
                        <td class="tabular-nums"><?= h($p['sku']) ?></td>
                        <td>
                            <a href="/product_edit.php?id=<?= $p['id'] ?>" class="text-blue-600 hover:underline">
                                <?= h(strLimit($p['name'], 40)) ?>
                            </a>
                        </td>
                        <td><?= h($p['category_name'] ?? '-') ?></td>
                        <td class="text-slate-600"><?= h($p['supplier_name'] ?? '-') ?></td>
                        <td class="text-right tabular-nums"><?= formatPrice($p['cost_price']) ?></td>
                        <td class="text-right tabular-nums"><?= formatPrice($p['guide_price']) ?></td>
                        <td class="text-right">
                            <span class="badge <?= marginClass($m) ?>">
                                <?= $m !== null ? number_format($m, 2) . '%' : '-' ?>
                            </span>
                        </td>
                        <td class="text-slate-500 text-xs"><?= h($p['updated_at']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/views/footer.php'; ?>
