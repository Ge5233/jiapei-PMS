<?php
/**
 * 产品列表
 */
define('PMS_ENTRY', true);
require_once __DIR__ . '/includes/bootstrap.php';
if (!PMS_INSTALLED) { header('Location: /install.php'); exit; }
requireLogin();
requireCostView();

$categories = Category::allGrouped();
$suppliers = Supplier::allActive();

$keyword = trim($_GET['q'] ?? '');
$categoryId = $_GET['category_id'] ?? '';
$supplierId = $_GET['supplier_id'] ?? '';
$status = $_GET['status'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$pageSize = 20;

$result = Product::list([
    'keyword' => $keyword,
    'category_id' => $categoryId !== '' ? (int)$categoryId : null,
    'supplier_id' => $supplierId !== '' ? (int)$supplierId : null,
    'status' => $status !== '' ? (int)$status : null,
    'page' => $page,
    'page_size' => $pageSize,
]);

$rows = $result['rows'];
$total = $result['total'];
$totalPages = max(1, (int)ceil($total / $pageSize));

// 构造分页 URL
function buildPageUrl(int $page): string {
    $params = $_GET;
    $params['page'] = $page;
    return '?' . http_build_query($params);
}

$pageTitle = '产品管理';
$activeMenu = 'products';
require __DIR__ . '/includes/views/header.php';
?>

<div class="flex items-center justify-between mb-4">
    <div>
        <h2 class="text-lg font-medium text-slate-800">产品列表</h2>
        <p class="text-sm text-slate-500 mt-0.5">共 <?= $total ?> 个产品</p>
    </div>
    <div class="flex gap-2">
        <?php if (isAdmin()): ?>
        <button type="button" id="batchGenerateSkuBtn" class="btn btn-secondary">
            <i data-lucide="refresh-cw" class="w-4 h-4 mr-1.5"></i>批量生成 SKU
        </button>
        <?php endif; ?>
        <a href="/export.php?<?= http_build_query(array_filter(['q' => $keyword, 'category_id' => $categoryId, 'supplier_id' => $supplierId, 'status' => $status])) ?>"
           class="btn btn-secondary">
            <i data-lucide="download" class="w-4 h-4 mr-1.5"></i>导出 Excel
        </a>
        <a href="/product_edit.php" class="btn btn-primary">
            <i data-lucide="plus" class="w-4 h-4 mr-1.5"></i>新增产品
        </a>
    </div>
</div>

<!-- 筛选 -->
<div class="card p-4 mb-4">
    <form method="get" class="flex flex-wrap gap-3 items-end">
        <div class="flex-1 min-w-[180px]">
            <label class="form-label">搜索</label>
            <input type="text" name="q" class="form-input" placeholder="产品名称 / SKU" value="<?= h($keyword) ?>">
        </div>
        <div class="min-w-[180px]">
            <label class="form-label">分类</label>
            <select name="category_id" class="form-select">
                <option value="">全部分类</option>
                <?php foreach ($categories as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= (int)$categoryId === (int)$c['id'] ? 'selected' : '' ?>>
                        <?= h($c['name']) ?>
                    </option>
                    <?php foreach ($c['children'] as $sub): ?>
                        <option value="<?= $sub['id'] ?>" <?= (int)$categoryId === (int)$sub['id'] ? 'selected' : '' ?>>
                            &nbsp;&nbsp;└ <?= h($sub['name']) ?>
                        </option>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="min-w-[180px]">
            <label class="form-label">供应商</label>
            <select name="supplier_id" class="form-select">
                <option value="">全部供应商</option>
                <?php foreach ($suppliers as $s): ?>
                    <option value="<?= $s['id'] ?>" <?= (int)$supplierId === (int)$s['id'] ? 'selected' : '' ?>>
                        <?= h($s['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="min-w-[120px]">
            <label class="form-label">状态</label>
            <select name="status" class="form-select">
                <option value="">全部</option>
                <option value="1" <?= $status === '1' ? 'selected' : '' ?>>上架</option>
                <option value="0" <?= $status === '0' ? 'selected' : '' ?>>下架</option>
            </select>
        </div>
        <div class="flex gap-2">
            <button type="submit" class="btn btn-primary">
                <i data-lucide="search" class="w-4 h-4 mr-1.5"></i>查询
            </button>
            <a href="/products.php" class="btn btn-secondary">重置</a>
        </div>
    </form>
</div>

<!-- 列表 -->
<div class="card">
    <?php if (empty($rows)): ?>
        <div class="card-body text-center text-slate-400 py-16">
            <i data-lucide="package-x" class="w-12 h-12 mx-auto mb-3"></i>
            <p>暂无产品</p>
            <a href="/product_edit.php" class="btn btn-primary mt-4 inline-flex">
                <i data-lucide="plus" class="w-4 h-4 mr-1.5"></i>新增产品
            </a>
        </div>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>SKU</th>
                        <th>名称 / 规格</th>
                        <th>分类</th>
                        <th>供应商</th>
                        <th>单位</th>
                        <th class="text-right">进价</th>
                        <th class="text-right">售价</th>
                        <th class="text-right">最低折扣</th>
                        <th class="text-right">毛利率</th>
                        <th class="text-center">状态</th>
                        <th class="text-right">操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $p):
                        $m = calcMargin((float)$p['cost_price'], (float)$p['guide_price']);
                    ?>
                        <tr>
                            <td class="tabular-nums text-slate-500"><?= h($p['sku']) ?></td>
                            <td>
                                <a href="/product_edit.php?id=<?= $p['id'] ?>" class="text-blue-600 hover:underline font-medium">
                                    <?= h(strLimit($p['name'], 40)) ?>
                                </a>
                                <?php if ($p['spec']): ?>
                                    <div class="text-xs text-slate-400"><?= h($p['spec']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td><?= h($p['category_name'] ?? '-') ?></td>
                            <td class="text-slate-600"><?= h($p['supplier_name'] ?? '-') ?></td>
                            <td><?= h($p['unit'] ?? '-') ?></td>
                            <td class="text-right tabular-nums"><?= formatPrice($p['cost_price']) ?></td>
                            <td class="text-right tabular-nums"><?= formatPrice($p['guide_price']) ?></td>
                            <td class="text-right tabular-nums text-slate-500"><?= number_format((float)$p['min_discount'] * 10, 1) ?>折</td>
                            <td class="text-right">
                                <span class="badge <?= marginClass($m) ?>">
                                    <?= $m !== null ? number_format($m, 2) . '%' : '-' ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <span class="badge <?= (int)$p['status'] === 1 ? 'badge-green' : 'badge-slate' ?>">
                                    <?= (int)$p['status'] === 1 ? '上架' : '下架' ?>
                                </span>
                            </td>
                            <td class="text-right whitespace-nowrap">
                                <a href="/product_edit.php?id=<?= $p['id'] ?>" class="btn btn-ghost btn-sm">
                                    <i data-lucide="edit-2" class="w-3.5 h-3.5"></i>
                                </a>
                                <button class="btn btn-ghost btn-sm" onclick="deleteProduct(<?= $p['id'] ?>, '<?= h(addslashes($p['name'])) ?>')">
                                    <i data-lucide="trash-2" class="w-3.5 h-3.5 text-red-500"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- 分页 -->
        <div class="px-4 py-3 border-t border-slate-200 flex items-center justify-between text-sm text-slate-500">
            <div>第 <?= $page ?> / <?= $totalPages ?> 页</div>
            <div class="flex gap-1">
                <?php if ($page > 1): ?>
                    <a href="<?= h(buildPageUrl($page - 1)) ?>" class="btn btn-secondary btn-sm">上一页</a>
                <?php endif; ?>
                <?php if ($page < $totalPages): ?>
                    <a href="<?= h(buildPageUrl($page + 1)) ?>" class="btn btn-secondary btn-sm">下一页</a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
function deleteProduct(id, name) {
    if (!confirm('确定要删除产品「' + name + '」吗？\n这将同时删除该产品的所有资料文件和价格历史。')) return;
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '/api/product_delete.php';
    const csrf = document.createElement('input');
    csrf.name = '_csrf';
    csrf.value = '<?= h(csrfToken()) ?>';
    form.appendChild(csrf);
    const idInput = document.createElement('input');
    idInput.name = 'id';
    idInput.value = id;
    form.appendChild(idInput);
    document.body.appendChild(form);
    form.submit();
}

// 批量生成 SKU
const batchBtn = document.getElementById('batchGenerateSkuBtn');
if (batchBtn) {
    batchBtn.addEventListener('click', async function() {
        const forceRegenerate = confirm('是否清除所有现有 SKU 并重新生成？\n\n点"确定"：清除所有 SKU 并重新生成\n点"取消"：只为 SKU 为空的产品生成');
        
        const confirmMsg = forceRegenerate 
            ? '确定要清除所有产品的 SKU 并重新生成吗？\n\n格式：父分类ID(2位) + 子分类ID(2位) + 序号(3位)'
            : '确定要为所有 SKU 为空的产品自动生成 SKU 吗？\n\n格式：父分类ID(2位) + 子分类ID(2位) + 序号(3位)\n\n注意：已有 SKU 的产品不会被覆盖。';
        
        if (!confirm(confirmMsg)) {
            return;
        }
        
        batchBtn.disabled = true;
        batchBtn.innerHTML = '<i data-lucide="loader" class="w-4 h-4 mr-1.5 animate-spin"></i>生成中...';
        
        try {
            const formData = new FormData();
            formData.append('csrf_token', '<?= h(csrfToken()) ?>');
            if (forceRegenerate) {
                formData.append('force', 'true');
            }
            
            const response = await fetch('/api/batch_generate_sku.php', {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            
            if (data.success) {
                if (data.updated > 0) {
                    alert(data.message + '\n\n页面将刷新以显示最新 SKU。');
                    window.location.reload();
                } else {
                    alert(data.message);
                    batchBtn.disabled = false;
                    batchBtn.innerHTML = '<i data-lucide="refresh-cw" class="w-4 h-4 mr-1.5"></i>批量生成 SKU';
                }
            } else {
                alert('生成失败: ' + (data.error || '未知错误'));
                batchBtn.disabled = false;
                batchBtn.innerHTML = '<i data-lucide="refresh-cw" class="w-4 h-4 mr-1.5"></i>批量生成 SKU';
            }
        } catch (error) {
            alert('请求失败: ' + error.message);
            batchBtn.disabled = false;
            batchBtn.innerHTML = '<i data-lucide="refresh-cw" class="w-4 h-4 mr-1.5"></i>批量生成 SKU';
        }
    });
}
</script>

<?php require __DIR__ . '/includes/views/footer.php'; ?>
