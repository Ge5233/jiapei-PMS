<?php
/**
 * 自产产品列表
 */
define('PMS_ENTRY', true);
require_once __DIR__ . '/includes/bootstrap.php';
if (!PMS_INSTALLED) { header('Location: /install.php'); exit; }
requireLogin();

$keyword = trim($_GET['q'] ?? '');
$status = $_GET['status'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$pageSize = 20;

$result = SelfProduct::list([
    'keyword' => $keyword,
    'status' => $status !== '' ? (int)$status : null,
    'page' => $page,
    'page_size' => $pageSize,
]);

$rows = $result['rows'];
$total = $result['total'];
$totalPages = max(1, (int)ceil($total / $pageSize));

function buildSelfPageUrl(int $p): string {
    $params = $_GET;
    $params['page'] = $p;
    return '?' . http_build_query($params);
}

$pageTitle = '自产产品管理';
$activeMenu = 'self_products';
require __DIR__ . '/includes/views/header.php';
?>

<div class="flex items-center justify-between mb-4">
    <div>
        <h2 class="text-lg font-medium text-slate-800">自产产品列表</h2>
        <p class="text-sm text-slate-500 mt-0.5">共 <?= $total ?> 个产品</p>
    </div>
    <div>
        <a href="/self_product_edit.php" class="btn btn-primary">
            <i data-lucide="plus" class="w-4 h-4 mr-1.5"></i>新增自产产品
        </a>
    </div>
</div>

<!-- 筛选 -->
<div class="card p-4 mb-4">
    <form method="get" class="flex flex-wrap gap-3 items-end">
        <div class="flex-1 min-w-[200px]">
            <label class="form-label">搜索</label>
            <input type="text" name="q" class="form-input" placeholder="产品名称 / 型号" value="<?= h($keyword) ?>">
        </div>
        <div class="min-w-[140px]">
            <label class="form-label">状态</label>
            <select name="status" class="form-select">
                <option value="">全部</option>
                <option value="1" <?= $status === '1' ? 'selected' : '' ?>>在生产</option>
                <option value="0" <?= $status === '0' ? 'selected' : '' ?>>已停产</option>
            </select>
        </div>
        <div>
            <button type="submit" class="btn btn-secondary">
                <i data-lucide="search" class="w-4 h-4 mr-1.5"></i>查询
            </button>
            <?php if ($keyword !== '' || $status !== ''): ?>
            <a href="/self_products.php" class="btn btn-ghost text-sm ml-2">清除</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- 列表 -->
<?php if (empty($rows)): ?>
<div class="card p-12 text-center">
    <i data-lucide="package-open" class="w-12 h-12 mx-auto text-slate-300 mb-3"></i>
    <p class="text-slate-500">暂无自产产品</p>
    <a href="/self_product_edit.php" class="btn btn-primary mt-4">新增第一个产品</a>
</div>
<?php else: ?>
<div class="card overflow-hidden">
    <table class="w-full">
        <thead>
            <tr class="border-b border-slate-200 bg-slate-50">
                <th class="text-left px-4 py-3 text-sm font-medium text-slate-600 w-12">主图</th>
                <th class="text-left px-4 py-3 text-sm font-medium text-slate-600">名称 / 型号</th>
                <th class="text-right px-4 py-3 text-sm font-medium text-slate-600">材料成本</th>
                <th class="text-right px-4 py-3 text-sm font-medium text-slate-600">人工成本</th>
                <th class="text-right px-4 py-3 text-sm font-medium text-slate-600">总成本</th>
                <th class="text-right px-4 py-3 text-sm font-medium text-slate-600">售价</th>
                <th class="text-center px-4 py-3 text-sm font-medium text-slate-600">毛利率</th>
                <th class="text-center px-4 py-3 text-sm font-medium text-slate-600">状态</th>
                <th class="text-center px-4 py-3 text-sm font-medium text-slate-600 w-24">操作</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            <?php foreach ($rows as $row):
                $margin = $row['guide_price'] > 0
                    ? round(($row['guide_price'] - $row['total_cost']) / $row['guide_price'] * 100, 1)
                    : 0;
                $marginColor = $margin >= 15 ? 'text-emerald-600' : ($margin >= 5 ? 'text-amber-600' : 'text-red-600');
                $marginBg = $margin >= 15 ? 'bg-emerald-50' : ($margin >= 5 ? 'bg-amber-50' : 'bg-red-50');
            ?>
            <tr class="hover:bg-slate-50 transition-colors">
                <td class="px-4 py-3">
                    <?php if ($row['image']): ?>
                    <img src="/uploads/<?= h($row['image']) ?>" alt="<?= h($row['name']) ?>"
                         class="w-10 h-10 rounded object-cover border border-slate-200">
                    <?php else: ?>
                    <div class="w-10 h-10 rounded bg-slate-100 border border-slate-200 flex items-center justify-center">
                        <i data-lucide="box" class="w-5 h-5 text-slate-400"></i>
                    </div>
                    <?php endif; ?>
                </td>
                <td class="px-4 py-3">
                    <div class="font-medium text-slate-800"><?= h($row['name']) ?></div>
                    <?php if ($row['model_no']): ?>
                    <div class="text-xs text-slate-400 mt-0.5"><?= h($row['model_no']) ?></div>
                    <?php endif; ?>
                </td>
                <td class="px-4 py-3 text-right text-sm tabular-nums">¥<?= number_format($row['material_cost'], 2) ?></td>
                <td class="px-4 py-3 text-right text-sm tabular-nums">¥<?= number_format($row['labor_cost'], 2) ?></td>
                <td class="px-4 py-3 text-right text-sm font-medium tabular-nums">¥<?= number_format($row['total_cost'], 2) ?></td>
                <td class="px-4 py-3 text-right text-sm tabular-nums">¥<?= number_format($row['guide_price'], 2) ?></td>
                <td class="px-4 py-3 text-center">
                    <span class="inline-block px-2 py-0.5 text-xs font-medium rounded <?= $marginBg ?> <?= $marginColor ?>">
                        <?= $margin ?>%
                    </span>
                </td>
                <td class="px-4 py-3 text-center">
                    <span class="inline-block px-2 py-0.5 text-xs rounded <?= $row['status'] == 1 ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500' ?>">
                        <?= $row['status'] == 1 ? '在生产' : '已停产' ?>
                    </span>
                </td>
                <td class="px-4 py-3 text-center">
                    <div class="flex items-center justify-center gap-1">
                        <a href="/self_product_edit.php?id=<?= $row['id'] ?>" class="btn-ghost-xs" title="编辑">
                            <i data-lucide="pencil" class="w-4 h-4"></i>
                        </a>
                        <button type="button" class="btn-ghost-xs text-red-500 hover:text-red-700"
                                onclick="deleteSelfProduct(<?= $row['id'] ?>, '<?= h(addslashes($row['name'])) ?>')" title="删除">
                            <i data-lucide="trash-2" class="w-4 h-4"></i>
                        </button>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- 分页 -->
<?php if ($totalPages > 1): ?>
<div class="flex items-center justify-center gap-2 mt-4 text-sm">
    <?php if ($page > 1): ?>
    <a href="<?= buildSelfPageUrl($page - 1) ?>" class="px-3 py-1.5 rounded border border-slate-200 hover:bg-slate-50">上一页</a>
    <?php endif; ?>
    <span class="text-slate-500">第 <?= $page ?> / <?= $totalPages ?> 页</span>
    <?php if ($page < $totalPages): ?>
    <a href="<?= buildSelfPageUrl($page + 1) ?>" class="px-3 py-1.5 rounded border border-slate-200 hover:bg-slate-50">下一页</a>
    <?php endif; ?>
</div>
<?php endif; ?>
<?php endif; ?>

<script>
async function deleteSelfProduct(id, name) {
    if (!confirm('确定删除"' + name + '"及所有BOM物料吗？此操作不可恢复！')) return;
    try {
        const resp = await fetch('/api/self_product_delete.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-Token': document.querySelector('input[name="_csrf"]')?.value || '',
            },
            body: JSON.stringify({ id: id })
        });
        const data = await resp.json();
        if (data.ok) {
            location.reload();
        } else {
            alert(data.message || '删除失败');
        }
    } catch (e) {
        alert('网络错误，请重试');
    }
}
</script>

<?php require __DIR__ . '/includes/views/footer.php'; ?>
