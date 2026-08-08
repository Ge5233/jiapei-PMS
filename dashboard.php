<?php
/**
 * 首页 - 仪表盘（v4.1 零成本信息）
 */
define('PMS_ENTRY', true);
require_once __DIR__ . '/includes/bootstrap.php';

if (!PMS_INSTALLED) {
    header('Location: /install.php');
    exit;
}
requireLogin();

$db = Database::getInstance();

// 统计
$prodStats = Product::stats();
$selfStats = class_exists('SelfProduct') ? SelfProduct::stats() : ['total' => 0, 'active' => 0, 'inactive' => 0];
$supplierCount = Supplier::count();
$quoteCount = $db->query("SELECT COUNT(*) FROM quotes")->fetchColumn();
$catCount = $db->query("SELECT COUNT(*) FROM categories")->fetchColumn();

// 今日新建（外采 + 自产）
$todayNew = $db->query("
    SELECT (SELECT COUNT(*) FROM products WHERE DATE(created_at) = CURDATE()) +
           (SELECT COUNT(*) FROM self_products WHERE DATE(created_at) = CURDATE())
")->fetchColumn();

// 最近外采
$recentProducts = Product::recentUpdated(5);

// 最近自产
$recentSelfProducts = class_exists('SelfProduct')
    ? $db->query("SELECT id, name, model_no, status, updated_at FROM self_products ORDER BY updated_at DESC LIMIT 5")->fetchAll()
    : [];

$pageTitle = '首页';
$activeMenu = 'dashboard';
require __DIR__ . '/includes/views/header.php';
?>

<!-- 快捷操作 -->
<div class="flex flex-wrap gap-2 mb-4">
    <?php if (canViewCost()): ?>
    <a href="/quote_edit.php" class="btn btn-primary">
        <i data-lucide="file-plus" class="w-4 h-4 mr-1.5"></i>新增报价
    </a>
    <a href="/product_edit.php" class="btn btn-secondary">
        <i data-lucide="plus" class="w-4 h-4 mr-1.5"></i>外采产品
    </a>
    <a href="/self_product_edit.php" class="btn btn-secondary">
        <i data-lucide="plus" class="w-4 h-4 mr-1.5"></i>自产产品
    </a>
    <?php endif; ?>
    <a href="/products.php" class="btn btn-ghost">
        <i data-lucide="search" class="w-4 h-4 mr-1.5"></i>产品目录
    </a>
    <a href="/quotes.php" class="btn btn-ghost">
        <i data-lucide="file-text" class="w-4 h-4 mr-1.5"></i>报价列表
    </a>
</div>

<!-- 统计卡片 2×3 -->
<div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4 mb-6">

    <!-- 外采产品 -->
    <a href="/products.php" class="card p-5 hover:border-blue-300 transition-colors">
        <div class="flex items-center justify-between">
            <div>
                <div class="text-sm text-slate-500">外采产品</div>
                <div class="text-2xl font-semibold text-blue-600 mt-1 tabular-nums"><?= $prodStats['total'] ?></div>
                <div class="text-xs text-slate-400 mt-0.5">上架 <?= $prodStats['on_sale'] ?> · 下架 <?= $prodStats['off_sale'] ?></div>
            </div>
            <div class="w-11 h-11 bg-blue-50 rounded-xl flex items-center justify-center flex-shrink-0">
                <i data-lucide="package" class="w-6 h-6 text-blue-500"></i>
            </div>
        </div>
    </a>

    <!-- 自产产品 -->
    <a href="/self_products.php" class="card p-5 hover:border-emerald-300 transition-colors">
        <div class="flex items-center justify-between">
            <div>
                <div class="text-sm text-slate-500">自产产品</div>
                <div class="text-2xl font-semibold text-emerald-600 mt-1 tabular-nums"><?= $selfStats['total'] ?></div>
                <div class="text-xs text-slate-400 mt-0.5">生产 <?= $selfStats['active'] ?> · 停产 <?= $selfStats['inactive'] ?></div>
            </div>
            <div class="w-11 h-11 bg-emerald-50 rounded-xl flex items-center justify-center flex-shrink-0">
                <i data-lucide="factory" class="w-6 h-6 text-emerald-500"></i>
            </div>
        </div>
    </a>

    <!-- 分类 -->
    <?php if (canViewCost()): ?>
    <a href="/categories.php" class="card p-5 hover:border-purple-300 transition-colors">
    <?php else: ?>
    <div class="card p-5">
    <?php endif; ?>
        <div class="flex items-center justify-between">
            <div>
                <div class="text-sm text-slate-500">产品分类</div>
                <div class="text-2xl font-semibold text-purple-600 mt-1 tabular-nums"><?= $catCount ?></div>
                <div class="text-xs text-slate-400 mt-0.5">个分类</div>
            </div>
            <div class="w-11 h-11 bg-purple-50 rounded-xl flex items-center justify-center flex-shrink-0">
                <i data-lucide="folders" class="w-6 h-6 text-purple-500"></i>
            </div>
        </div>
    <?= canViewCost() ? '</a>' : '</div>' ?>

    <!-- 供应商 -->
    <?php if (canViewCost()): ?>
    <a href="/supplier.php" class="card p-5 hover:border-amber-300 transition-colors">
    <?php else: ?>
    <div class="card p-5">
    <?php endif; ?>
        <div class="flex items-center justify-between">
            <div>
                <div class="text-sm text-slate-500">供应商</div>
                <div class="text-2xl font-semibold text-amber-600 mt-1 tabular-nums"><?= $supplierCount ?></div>
                <div class="text-xs text-slate-400 mt-0.5">家</div>
            </div>
            <div class="w-11 h-11 bg-amber-50 rounded-xl flex items-center justify-center flex-shrink-0">
                <i data-lucide="truck" class="w-6 h-6 text-amber-500"></i>
            </div>
        </div>
    <?= canViewCost() ? '</a>' : '</div>' ?>

    <!-- 报价单 -->
    <a href="/quotes.php" class="card p-5 hover:border-rose-300 transition-colors">
        <div class="flex items-center justify-between">
            <div>
                <div class="text-sm text-slate-500">报价单</div>
                <div class="text-2xl font-semibold text-rose-600 mt-1 tabular-nums"><?= $quoteCount ?></div>
                <div class="text-xs text-slate-400 mt-0.5">份</div>
            </div>
            <div class="w-11 h-11 bg-rose-50 rounded-xl flex items-center justify-center flex-shrink-0">
                <i data-lucide="file-text" class="w-6 h-6 text-rose-500"></i>
            </div>
        </div>
    </a>

    <!-- 今日新建 -->
    <div class="card p-5">
        <div class="flex items-center justify-between">
            <div>
                <div class="text-sm text-slate-500">今日新建</div>
                <div class="text-2xl font-semibold text-sky-600 mt-1 tabular-nums"><?= $todayNew ?></div>
                <div class="text-xs text-slate-400 mt-0.5">个产品</div>
            </div>
            <div class="w-11 h-11 bg-sky-50 rounded-xl flex items-center justify-center flex-shrink-0">
                <i data-lucide="sparkles" class="w-6 h-6 text-sky-500"></i>
            </div>
        </div>
    </div>

</div>

<!-- 最近修改：双列 -->
<div class="grid grid-cols-1 md:grid-cols-2 gap-4">

    <!-- 左：外采 -->
    <div class="card">
        <div class="card-header flex items-center justify-between">
            <span>最近外采产品</span>
            <a href="/products.php" class="text-sm text-blue-600 hover:underline">全部 →</a>
        </div>
        <?php if (empty($recentProducts)): ?>
            <div class="card-body text-center text-slate-400 py-10">暂无产品</div>
        <?php else: ?>
            <div class="divide-y divide-slate-100">
                <?php foreach ($recentProducts as $p): ?>
                    <a href="/product_edit.php?id=<?= $p['id'] ?>" class="block px-5 py-3 hover:bg-slate-50 transition-colors">
                        <div class="flex items-center justify-between gap-2">
                            <div class="min-w-0">
                                <span class="text-xs text-slate-400 tabular-nums mr-2"><?= h($p['sku']) ?></span>
                                <span class="text-sm text-slate-800"><?= h(strLimit($p['name'], 30)) ?></span>
                            </div>
                            <span class="text-xs text-slate-400 flex-shrink-0"><?= h($p['updated_at']) ?></span>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- 右：自产 -->
    <div class="card">
        <div class="card-header flex items-center justify-between">
            <span>最近自产产品</span>
            <a href="/self_products.php" class="text-sm text-emerald-600 hover:underline">全部 →</a>
        </div>
        <?php if (empty($recentSelfProducts)): ?>
            <div class="card-body text-center text-slate-400 py-10">暂无产品</div>
        <?php else: ?>
            <div class="divide-y divide-slate-100">
                <?php foreach ($recentSelfProducts as $sp): ?>
                    <a href="/self_product_edit.php?id=<?= $sp['id'] ?>" class="block px-5 py-3 hover:bg-slate-50 transition-colors">
                        <div class="flex items-center justify-between gap-2">
                            <div class="min-w-0">
                                <?php if ($sp['model_no']): ?>
                                <span class="text-xs text-slate-400 mr-2"><?= h($sp['model_no']) ?></span>
                                <?php endif; ?>
                                <span class="text-sm text-slate-800"><?= h(strLimit($sp['name'], 30)) ?></span>
                            </div>
                            <span class="badge <?= $sp['status'] == 1 ? 'badge-green' : 'badge-slate' ?> text-xs flex-shrink-0">
                                <?= $sp['status'] == 1 ? '生产' : '停产' ?>
                            </span>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

</div>

<?php require __DIR__ . '/includes/views/footer.php'; ?>
