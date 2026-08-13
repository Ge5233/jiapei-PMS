<?php
/**
 * 项目列表
 */
define('PMS_ENTRY', true);
require_once __DIR__ . '/includes/bootstrap.php';
if (!PMS_INSTALLED) { header('Location: /install.php'); exit; }
requireLogin();
requireCostView();

$projects = Project::all();

$pageTitle = '项目管理';
$activeMenu = 'projects';
require __DIR__ . '/includes/views/header.php';
?>

<div class="flex items-center justify-between mb-4">
    <div>
        <h2 class="text-lg font-medium text-slate-800">项目列表</h2>
        <p class="text-sm text-slate-500 mt-0.5">共 <?= count($projects) ?> 个项目</p>
    </div>
    <div>
        <a href="/project_edit.php" class="btn btn-primary">
            <i data-lucide="plus" class="w-4 h-4 mr-1.5"></i>新建项目
        </a>
    </div>
</div>

<input type="hidden" name="_csrf" value="<?= h(csrfToken()) ?>">

<?php if (empty($projects)): ?>
<div class="card p-12 text-center">
    <i data-lucide="folder" class="w-12 h-12 mx-auto text-slate-300 mb-3"></i>
    <p class="text-slate-500">还没有项目</p>
    <a href="/project_edit.php" class="btn btn-primary mt-4">新建第一个项目</a>
</div>
<?php else: ?>
<div class="card overflow-hidden">
    <table class="w-full">
        <thead>
            <tr class="border-b border-slate-200 bg-slate-50">
                <th class="text-left px-4 py-3 text-sm font-medium text-slate-600">项目名称</th>
                <th class="text-left px-4 py-3 text-sm font-medium text-slate-600">客户</th>
                <th class="text-center px-4 py-3 text-sm font-medium text-slate-600">状态</th>
                <th class="text-left px-4 py-3 text-sm font-medium text-slate-600">更新时间</th>
                <th class="text-center px-4 py-3 text-sm font-medium text-slate-600 w-24">操作</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            <?php foreach ($projects as $p):
                $statusMap = [
                    'active' => ['进行中', 'bg-emerald-50 text-emerald-700'],
                    'done' => ['已完成', 'bg-blue-50 text-blue-700'],
                    'cancelled' => ['已取消', 'bg-slate-100 text-slate-500'],
                ];
                $st = $statusMap[$p['status']] ?? $statusMap['active'];
            ?>
            <tr class="hover:bg-slate-50 transition-colors">
                <td class="px-4 py-3">
                    <a href="/project_edit.php?id=<?= $p['id'] ?>" class="font-medium text-blue-600 hover:underline"><?= h($p['name']) ?></a>
                </td>
                <td class="px-4 py-3 text-sm text-slate-600"><?= h($p['customer_name'] ?: '—') ?></td>
                <td class="px-4 py-3 text-center">
                    <span class="inline-block px-2 py-0.5 text-xs rounded <?= $st[1] ?>"><?= $st[0] ?></span>
                </td>
                <td class="px-4 py-3 text-sm text-slate-500"><?= h($p['updated_at']) ?></td>
                <td class="px-4 py-3 text-center">
                    <div class="flex items-center justify-center gap-1">
                        <a href="/project_edit.php?id=<?= $p['id'] ?>" class="btn-ghost-xs" title="编辑">
                            <i data-lucide="pencil" class="w-4 h-4"></i>
                        </a>
                        <button type="button" class="btn-ghost-xs text-red-500 hover:text-red-700"
                                onclick="deleteProject(<?= $p['id'] ?>, '<?= h(addslashes($p['name'])) ?>')" title="删除">
                            <i data-lucide="trash-2" class="w-4 h-4"></i>
                        </button>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<script>
async function deleteProject(id, name) {
    if (!confirm('确定删除项目"' + name + '"吗？其下所有产品记录也会删除！')) return;
    const fd = new FormData();
    fd.append('action', 'delete');
    fd.append('id', id);
    fd.append('_csrf', document.querySelector('input[name="_csrf"]')?.value || '');
    try {
        const resp = await fetch('/api/project_save.php', { method: 'POST', body: fd });
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
