<?php
/**
 * 报价单列表
 */
define('PMS_ENTRY', true);
require_once __DIR__ . '/includes/bootstrap.php';
if (!PMS_INSTALLED) { header('Location: /install.php'); exit; }
requireLogin();

$keyword = trim($_GET['q'] ?? '');
$status = $_GET['status'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));

$result = Quote::list([
    'keyword' => $keyword,
    'status' => $status ?: null,
    'page' => $page,
    'page_size' => 20,
]);
$rows = $result['rows'];
$total = $result['total'];
$totalPages = max(1, (int)ceil($total / 20));

$statusLabels = ['draft'=>'草稿','sent'=>'已发出','accepted'=>'已接受','rejected'=>'已拒绝'];
$statusColors = ['draft'=>'bg-slate-100 text-slate-600','sent'=>'bg-blue-50 text-blue-700','accepted'=>'bg-emerald-50 text-emerald-700','rejected'=>'bg-red-50 text-red-700'];

$pageTitle = '报价管理';
$activeMenu = 'quotes';
require __DIR__ . '/includes/views/header.php';
?>

<div class="flex items-center justify-between mb-4">
    <div>
        <h2 class="text-lg font-medium text-slate-800">报价单列表</h2>
        <p class="text-sm text-slate-500 mt-0.5">共 <?= $total ?> 份报价单</p>
    </div>
    <a href="/quote_edit.php" class="btn btn-primary">
        <i data-lucide="plus" class="w-4 h-4 mr-1.5"></i>新建报价单
    </a>
</div>

<input type="hidden" name="_csrf" value="<?= h(csrfToken()) ?>">

<div class="card p-4 mb-4">
    <form method="get" class="flex flex-wrap gap-3 items-end">
        <div class="flex-1 min-w-[200px]">
            <label class="form-label">搜索</label>
            <input type="text" name="q" class="form-input" placeholder="项目名 / 客户 / 编号" value="<?= h($keyword) ?>">
        </div>
        <div class="min-w-[120px]">
            <label class="form-label">状态</label>
            <select name="status" class="form-select">
                <option value="">全部</option>
                <option value="draft" <?= $status==='draft'?'selected':'' ?>>草稿</option>
                <option value="sent" <?= $status==='sent'?'selected':'' ?>>已发出</option>
                <option value="accepted" <?= $status==='accepted'?'selected':'' ?>>已接受</option>
                <option value="rejected" <?= $status==='rejected'?'selected':'' ?>>已拒绝</option>
            </select>
        </div>
        <div>
            <button type="submit" class="btn btn-secondary"><i data-lucide="search" class="w-4 h-4 mr-1.5"></i>查询</button>
            <?php if ($keyword||$status): ?><a href="/quotes.php" class="btn btn-ghost text-sm ml-2">清除</a><?php endif; ?>
        </div>
    </form>
</div>

<?php if (empty($rows)): ?>
<div class="card p-12 text-center">
    <i data-lucide="file-text" class="w-12 h-12 mx-auto text-slate-300 mb-3"></i>
    <p class="text-slate-500">暂无报价单</p>
    <a href="/quote_edit.php" class="btn btn-primary mt-4">新建第一份报价单</a>
</div>
<?php else: ?>
<div class="card overflow-hidden">
    <table class="w-full">
        <thead>
            <tr class="border-b border-slate-200 bg-slate-50">
                <th class="text-left px-4 py-3 text-sm font-medium text-slate-600">编号</th>
                <th class="text-left px-4 py-3 text-sm font-medium text-slate-600">项目名称</th>
                <th class="text-left px-4 py-3 text-sm font-medium text-slate-600">客户</th>
                <th class="text-right px-4 py-3 text-sm font-medium text-slate-600">合计</th>
                <th class="text-center px-4 py-3 text-sm font-medium text-slate-600">状态</th>
                <th class="text-left px-4 py-3 text-sm font-medium text-slate-600">日期</th>
                <th class="text-center px-4 py-3 text-sm font-medium text-slate-600 w-28">操作</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            <?php foreach ($rows as $q): ?>
            <tr class="hover:bg-slate-50">
                <td class="px-4 py-3 tabular-nums text-sm font-medium"><?= h($q['quote_no']) ?></td>
                <td class="px-4 py-3">
                    <a href="/quote_edit.php?id=<?= $q['id'] ?>" class="text-blue-600 hover:underline font-medium"><?= h($q['project_name']) ?></a>
                </td>
                <td class="px-4 py-3 text-sm"><?= h($q['customer_name'] ?: '-') ?></td>
                <td class="px-4 py-3 text-right text-sm tabular-nums font-medium">¥<?= number_format($q['total_amount'], 2) ?></td>
                <td class="px-4 py-3 text-center">
                    <span class="inline-block px-2 py-0.5 text-xs rounded <?= $statusColors[$q['status']] ?? '' ?>"><?= $statusLabels[$q['status']] ?? $q['status'] ?></span>
                </td>
                <td class="px-4 py-3 text-sm text-slate-500"><?= date('m-d H:i', strtotime($q['created_at'])) ?></td>
                <td class="px-4 py-3 text-center">
                    <div class="flex items-center justify-center gap-1">
                        <a href="/quote_edit.php?id=<?= $q['id'] ?>" class="btn-ghost-xs" title="编辑"><i data-lucide="pencil" class="w-4 h-4"></i></a>
                        <a href="/quote_print.php?id=<?= $q['id'] ?>" class="btn-ghost-xs" title="打印" target="_blank"><i data-lucide="printer" class="w-4 h-4"></i></a>
                        <a href="/export_quote.php?id=<?= $q['id'] ?>" class="btn-ghost-xs" title="导出Excel"><i data-lucide="download" class="w-4 h-4"></i></a>
                        <button class="btn-ghost-xs text-red-500" title="删除" onclick="delQuote(<?= $q['id'] ?>,'<?= h(addslashes($q['quote_no'])) ?>')"><i data-lucide="trash-2" class="w-4 h-4"></i></button>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php if ($totalPages>1): ?>
<div class="flex items-center justify-center gap-2 mt-4 text-sm">
    <?php if($page>1): ?><a href="?page=<?=$page-1?>&q=<?=urlencode($keyword)?>&status=<?=urlencode($status)?>" class="px-3 py-1.5 rounded border border-slate-200 hover:bg-slate-50">上一页</a><?php endif; ?>
    <span class="text-slate-500"><?=$page?>/<?=$totalPages?></span>
    <?php if($page<$totalPages): ?><a href="?page=<?=$page+1?>&q=<?=urlencode($keyword)?>&status=<?=urlencode($status)?>" class="px-3 py-1.5 rounded border border-slate-200 hover:bg-slate-50">下一页</a><?php endif; ?>
</div>
<?php endif; endif; ?>

<script>
async function delQuote(id,no){if(!confirm('确定删除报价单 '+no+' 吗？'))return;
try{let r=await fetch('/api/quote_delete.php',{method:'POST',headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest','X-CSRF-Token':document.querySelector('input[name="_csrf"]')?.value||''},body:JSON.stringify({id})});let d=await r.json();if(d.ok)location.reload();else alert(d.message)}catch(e){alert('网络错误')}}
</script>

<?php require __DIR__ . '/includes/views/footer.php'; ?>
