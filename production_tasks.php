<?php
/**
 * 生产任务单列表
 */
define('PMS_ENTRY', true);
require_once __DIR__ . '/includes/bootstrap.php';
if (!PMS_INSTALLED) { header('Location: /install.php'); exit; }
requireLogin();
requireCostView();

$status = $_GET['status'] ?? '';
$tasks = ProductionTask::all([
    'status' => $status !== '' ? $status : null,
]);

$pageTitle = '生产任务';
$activeMenu = 'production_tasks';
require __DIR__ . '/includes/views/header.php';
?>

<div class="flex items-center justify-between mb-4">
    <div>
        <h2 class="text-lg font-medium text-slate-800">生产任务单</h2>
        <p class="text-sm text-slate-500 mt-0.5">共 <?= count($tasks) ?> 条任务</p>
    </div>
</div>

<!-- 筛选 -->
<div class="card p-4 mb-4">
    <form method="get" class="flex flex-wrap gap-3 items-end">
        <div class="min-w-[160px]">
            <label class="form-label">状态</label>
            <select name="status" class="form-select">
                <option value="">全部</option>
                <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>待确认</option>
                <option value="confirmed" <?= $status === 'confirmed' ? 'selected' : '' ?>>已确认</option>
            </select>
        </div>
        <div>
            <button type="submit" class="btn btn-secondary">
                <i data-lucide="search" class="w-4 h-4 mr-1.5"></i>筛选
            </button>
            <?php if ($status !== ''): ?>
            <a href="/production_tasks.php" class="btn btn-ghost text-sm ml-2">清除</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<?php if (empty($tasks)): ?>
<div class="card p-12 text-center">
    <i data-lucide="clipboard-check" class="w-12 h-12 mx-auto text-slate-300 mb-3"></i>
    <p class="text-slate-500">暂无生产任务</p>
    <p class="text-xs text-slate-400 mt-1">在项目里点「生成生产任务单」即可创建</p>
</div>
<?php else: ?>
<div class="card overflow-hidden">
    <table class="w-full">
        <thead>
            <tr class="border-b border-slate-200 bg-slate-50">
                <th class="text-left px-4 py-3 text-sm font-medium text-slate-600">任务单号</th>
                <th class="text-left px-4 py-3 text-sm font-medium text-slate-600">项目</th>
                <th class="text-left px-4 py-3 text-sm font-medium text-slate-600">产品</th>
                <th class="text-right px-4 py-3 text-sm font-medium text-slate-600">数量</th>
                <th class="text-center px-4 py-3 text-sm font-medium text-slate-600">状态</th>
                <th class="text-left px-4 py-3 text-sm font-medium text-slate-600">更新时间</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            <?php foreach ($tasks as $t): ?>
            <tr class="hover:bg-slate-50 transition-colors cursor-pointer" onclick="location.href='/production_task_edit.php?id=<?= $t['id'] ?>'">
                <td class="px-4 py-3 font-medium text-blue-600"><?= h($t['task_no']) ?></td>
                <td class="px-4 py-3 text-sm text-slate-600"><?= h($t['project_name'] ?: '—') ?></td>
                <td class="px-4 py-3 text-sm">
                    <?= h($t['product_name']) ?>
                    <?php if ($t['model_no']): ?><span class="text-xs text-slate-400 ml-1"><?= h($t['model_no']) ?></span><?php endif; ?>
                </td>
                <td class="px-4 py-3 text-right text-sm tabular-nums"><?= rtrim(rtrim(number_format($t['quantity'], 4), '0'), '.') ?> <?= h($t['unit']) ?></td>
                <td class="px-4 py-3 text-center">
                    <span class="inline-block px-2 py-0.5 text-xs rounded <?= $t['status'] === 'confirmed' ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' ?>">
                        <?= $t['status'] === 'confirmed' ? '已确认' : '待确认' ?>
                    </span>
                </td>
                <td class="px-4 py-3 text-sm text-slate-500"><?= h($t['updated_at']) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php require __DIR__ . '/includes/views/footer.php'; ?>
