<?php
if (!defined('PMS_ENTRY')) {
    http_response_code(403);
    exit('Forbidden');
}

/**
 * 公共布局顶部
 * @var string|null $pageTitle
 * @var string|null $activeMenu dashboard/products/categories/suppliers/quote/users/profile
 */

$user = currentUser();
$pageTitle = $pageTitle ?? '产品管理系统';
$activeMenu = $activeMenu ?? '';
$bodyClass = $bodyClass ?? '';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($pageTitle) ?> - 产品管理系统</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="/assets/app.css">
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script defer src="/assets/search-select.js"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="bg-slate-50 text-slate-800 antialiased <?= h($bodyClass) ?>">

<div class="flex min-h-screen">
    <!-- 左侧菜单 -->
    <aside class="w-[220px] bg-slate-900 text-slate-200 flex-shrink-0 flex flex-col">
        <div class="h-14 flex items-center px-5 border-b border-slate-800">
            <i data-lucide="package" class="w-5 h-5 mr-2 text-blue-400"></i>
            <span class="font-semibold text-white">PMS 产品管理</span>
        </div>

        <nav class="flex-1 py-4 px-3 space-y-6 overflow-y-auto">
            <div>
                <div class="px-2 mb-2 text-xs text-slate-500 uppercase tracking-wider">业务</div>
                <ul class="space-y-1">
                    <li>
                        <a href="/dashboard.php" class="nav-link <?= $activeMenu === 'dashboard' ? 'active' : '' ?>">
                            <i data-lucide="layout-dashboard" class="w-4 h-4 mr-2"></i>首页
                        </a>
                    </li>
                    <li>
                        <a href="/products.php" class="nav-link <?= $activeMenu === 'products' ? 'active' : '' ?>">
                            <i data-lucide="package" class="w-4 h-4 mr-2"></i>外采产品管理
                        </a>
                    </li>
                    <li>
                        <a href="/self_products.php" class="nav-link <?= $activeMenu === 'self_products' ? 'active' : '' ?>">
                            <i data-lucide="factory" class="w-4 h-4 mr-2"></i>自产产品
                        </a>
                    </li>
                    <?php if (canViewCost()): ?>
                    <li>
                        <a href="/systems.php" class="nav-link <?= $activeMenu === 'systems' ? 'active' : '' ?>">
                            <i data-lucide="cpu" class="w-4 h-4 mr-2"></i>大型系统
                        </a>
                    </li>
                    <?php endif; ?>
                    <?php if (canViewCost()): ?>
                    <li>
                        <a href="/categories.php" class="nav-link <?= $activeMenu === 'categories' ? 'active' : '' ?>">
                            <i data-lucide="folder-tree" class="w-4 h-4 mr-2"></i>分类管理
                        </a>
                    </li>
                    <li>
                        <a href="/supplier.php" class="nav-link <?= $activeMenu === 'suppliers' ? 'active' : '' ?>">
                            <i data-lucide="truck" class="w-4 h-4 mr-2"></i>供应商管理
                        </a>
                    </li>
                    <?php endif; ?>
                    <li>
                        <a href="/quotes.php" class="nav-link <?= $activeMenu === 'quotes' ? 'active' : '' ?>">
                            <i data-lucide="file-text" class="w-4 h-4 mr-2"></i>报价管理
                        </a>
                    </li>
                    <?php if (canViewCost()): ?>
                    <li>
                        <a href="/projects.php" class="nav-link <?= $activeMenu === 'projects' ? 'active' : '' ?>">
                            <i data-lucide="folder" class="w-4 h-4 mr-2"></i>项目管理
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
            </div>

            <div>
                <div class="px-2 mb-2 text-xs text-slate-500 uppercase tracking-wider">系统</div>
                <ul class="space-y-1">
                    <?php if (isAdmin()): ?>
                    <li>
                        <a href="/users.php" class="nav-link <?= $activeMenu === 'users' ? 'active' : '' ?>">
                            <i data-lucide="users" class="w-4 h-4 mr-2"></i>用户管理
                        </a>
                    </li>
                    <?php endif; ?>
                    <li>
                        <a href="/profile.php" class="nav-link <?= $activeMenu === 'profile' ? 'active' : '' ?>">
                            <i data-lucide="user-circle" class="w-4 h-4 mr-2"></i>我的资料
                        </a>
                    </li>
                </ul>
            </div>
        </nav>

        <div class="px-4 py-3 border-t border-slate-800 text-xs text-slate-500">
            v1.0 · 内部使用
        </div>
    </aside>

    <!-- 主区域 -->
    <div class="flex-1 flex flex-col min-w-0">
        <!-- 顶部条 -->
        <header class="h-14 bg-white border-b border-slate-200 flex items-center justify-between px-6">
            <h1 class="text-base font-medium text-slate-700"><?= h($pageTitle) ?></h1>
            <div class="flex items-center gap-3">
                <span class="text-sm text-slate-600">
                    <?= h($user['name']) ?>
                    <span class="ml-1 inline-block px-1.5 py-0.5 text-xs rounded <?= $user['role'] === 'admin' ? 'bg-purple-100 text-purple-700' : 'bg-blue-100 text-blue-700' ?>">
                        <?= $user['role'] === 'admin' ? '管理员' : '员工' ?>
                    </span>
                </span>
                <a href="/logout.php" class="text-sm text-slate-500 hover:text-red-600 flex items-center">
                    <i data-lucide="log-out" class="w-4 h-4 mr-1"></i>退出
                </a>
            </div>
        </header>

        <!-- 内容区 -->
        <main class="flex-1 p-6 overflow-auto">
            <?= renderFlash() ?>
