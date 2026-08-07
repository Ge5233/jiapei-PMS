<?php
/**
 * 分类管理（A+B：换父级 + 拖拽排序）
 */
define('PMS_ENTRY', true);
require_once __DIR__ . '/includes/bootstrap.php';
if (!PMS_INSTALLED) { header('Location: /install.php'); exit; }
requireLogin();

$canManage = isAdmin(); // 只有管理员能增删改

$categories = Category::allGrouped();

// 统计每个分类下的产品数
$productCounts = [];
$stmt = Database::getInstance()->query("SELECT category_id, COUNT(*) AS c FROM products GROUP BY category_id");
foreach ($stmt->fetchAll() as $r) {
    $productCounts[(int)$r['category_id']] = (int)$r['c'];
}
function getCount($id, $productCounts) {
    return $productCounts[$id] ?? 0;
}

$pageTitle = '分类管理';
$activeMenu = 'categories';
require __DIR__ . '/includes/views/header.php';
?>

<div class="flex items-center justify-between mb-4">
    <div>
        <h2 class="text-lg font-medium text-slate-800">分类管理</h2>
        <p class="text-sm text-slate-500 mt-0.5">支持两级分类（最多 2 级）</p>
    </div>
    <?php if ($canManage): ?>
    <button class="btn btn-primary" onclick="openLevel1Modal()">
        <i data-lucide="plus" class="w-4 h-4 mr-1.5"></i>新增一级分类
    </button>
    <?php endif; ?>
</div>

<div class="text-xs text-slate-500 mb-3 flex items-center gap-4 bg-blue-50 border border-blue-200 rounded-md px-3 py-2">
    <i data-lucide="info" class="w-4 h-4 text-blue-500"></i>
    <span>拖拽分类项可调整顺序；点 <b>「移动」</b> 可把二级分类改到其他一级下</span>
</div>

<?php if (empty($categories)): ?>
    <div class="card">
        <div class="card-body text-center text-slate-400 py-16">
            <i data-lucide="folder-open" class="w-12 h-12 mx-auto mb-3"></i>
            <p>暂无分类</p>
            <?php if ($canManage): ?>
                <button class="btn btn-primary mt-4 inline-flex" onclick="openLevel1Modal()">
                    <i data-lucide="plus" class="w-4 h-4 mr-1.5"></i>创建第一个分类
                </button>
            <?php endif; ?>
        </div>
    </div>
<?php else: ?>
    <div class="space-y-3" id="level1Container">
        <?php foreach ($categories as $c): ?>
            <div class="card" data-id="<?= $c['id'] ?>" data-level="1">
                <div class="card-header flex items-center justify-between bg-slate-50">
                    <div class="flex items-center gap-2 cursor-move">
                        <i data-lucide="grip-vertical" class="w-4 h-4 text-slate-400"></i>
                        <i data-lucide="folder" class="w-4 h-4 text-blue-500"></i>
                        <span class="font-medium"><?= h($c['name']) ?></span>
                        <span class="text-xs text-slate-400">(编号: <?= $c['parent_sort_id'] ?>)</span>
                        <span class="badge badge-slate">一级分类</span>
                    </div>
                    <?php if ($canManage): ?>
                    <div class="flex gap-1">
                        <button class="btn btn-ghost btn-sm" onclick="openLevel2Modal(<?= $c['id'] ?>, '<?= h(addslashes($c['name'])) ?>')">
                            <i data-lucide="plus" class="w-3.5 h-3.5"></i>
                        </button>
                        <button class="btn btn-ghost btn-sm" onclick="openEditModal(<?= $c['id'] ?>, '<?= h(addslashes($c['name'])) ?>', 0)">
                            <i data-lucide="edit-2" class="w-3.5 h-3.5"></i>
                        </button>
                        <button class="btn btn-ghost btn-sm" onclick="deleteCategory(<?= $c['id'] ?>, '<?= h(addslashes($c['name'])) ?>', true)">
                            <i data-lucide="trash-2" class="w-3.5 h-3.5 text-red-500"></i>
                        </button>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="card-body p-0 level2-list" data-parent="<?= $c['id'] ?>">
                    <?php if (empty($c['children'])): ?>
                        <div class="px-5 py-4 text-sm text-slate-400 text-center">暂无二级分类</div>
                    <?php else: ?>
                        <ul class="divide-y divide-slate-100">
                            <?php foreach ($c['children'] as $sub): ?>
                                <li class="px-5 py-3 flex items-center justify-between hover:bg-slate-50" data-id="<?= $sub['id'] ?>" draggable="true">
                                    <div class="flex items-center gap-2 cursor-move flex-1 min-w-0">
                                        <i data-lucide="grip-vertical" class="w-3.5 h-3.5 text-slate-300"></i>
                                        <i data-lucide="file" class="w-3.5 h-3.5 text-slate-400"></i>
                                        <span class="text-sm"><?= h($sub['name']) ?></span>
                                        <span class="text-xs text-slate-400">(编号: <?= $sub['sub_id'] ?>)</span>
                                        <span class="text-xs text-slate-400">·</span>
                                        <span class="text-xs text-slate-400"><?= getCount($sub['id'], $productCounts) ?> 个产品</span>
                                    </div>
                                    <?php if ($canManage): ?>
                                    <div class="flex gap-1">
                                        <button class="btn btn-ghost btn-sm" onclick="openEditModal(<?= $sub['id'] ?>, '<?= h(addslashes($sub['name'])) ?>', <?= $c['id'] ?>)">
                                            <i data-lucide="edit-2" class="w-3.5 h-3.5"></i>
                                        </button>
                                        <button class="btn btn-ghost btn-sm" onclick="deleteCategory(<?= $sub['id'] ?>, '<?= h(addslashes($sub['name'])) ?>', false)">
                                            <i data-lucide="trash-2" class="w-3.5 h-3.5 text-red-500"></i>
                                        </button>
                                    </div>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- 新增/编辑弹窗 -->
<div id="modal" class="hidden">
    <div class="modal-backdrop" onclick="closeModal()">
        <div class="modal-content w-full max-w-md p-6" onclick="event.stopPropagation()">
            <h3 class="text-base font-medium text-slate-800 mb-4" id="modalTitle">新增分类</h3>
            <form id="categoryForm" method="post" action="/api/category_save.php">
                <?= csrfField() ?>
                <input type="hidden" name="id" id="cat_id">
                <input type="hidden" name="parent_id" id="cat_parent_id" value="0">
                <div class="mb-4">
                    <label class="form-label">分类名称 <span class="text-red-500">*</span></label>
                    <input type="text" name="name" id="cat_name" class="form-input" required maxlength="50">
                </div>
                <div class="flex justify-end gap-2">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">取消</button>
                    <button type="submit" class="btn btn-primary">保存</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- 移动弹窗 -->
<div id="moveModal" class="hidden">
    <div class="modal-backdrop" onclick="closeMoveModal()">
        <div class="modal-content w-full max-w-md p-6" onclick="event.stopPropagation()">
            <h3 class="text-base font-medium text-slate-800 mb-1">移动分类</h3>
            <p class="text-sm text-slate-500 mb-4" id="moveTargetName">把此分类移动到：</p>
            <form id="moveForm" method="post" action="/api/category_move.php">
                <?= csrfField() ?>
                <input type="hidden" name="id" id="move_id">
                <div class="mb-4">
                    <label class="form-label">目标一级分类 <span class="text-red-500">*</span></label>
                    <select name="new_parent_id" id="move_target" class="form-select" required>
                        <?php foreach ($categories as $c): ?>
                            <option value="<?= $c['id'] ?>"><?= h($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="flex justify-end gap-2">
                    <button type="button" class="btn btn-secondary" onclick="closeMoveModal()">取消</button>
                    <button type="submit" class="btn btn-primary">移动</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
const CSRF = '<?= h(csrfToken()) ?>';
const CAN_MANAGE = <?= $canManage ? 'true' : 'false' ?>;

// 弹窗
function openLevel1Modal() {
    document.getElementById('modalTitle').textContent = '新增一级分类';
    document.getElementById('cat_id').value = '';
    document.getElementById('cat_parent_id').value = '0';
    document.getElementById('cat_name').value = '';
    showModal();
}
function openLevel2Modal(parentId, parentName) {
    document.getElementById('modalTitle').textContent = '在「' + parentName + '」下新增二级分类';
    document.getElementById('cat_id').value = '';
    document.getElementById('cat_parent_id').value = parentId;
    document.getElementById('cat_name').value = '';
    showModal();
}
function openEditModal(id, name, parentId) {
    document.getElementById('modalTitle').textContent = parentId === 0 ? '编辑一级分类' : '编辑二级分类';
    document.getElementById('cat_id').value = id;
    document.getElementById('cat_parent_id').value = parentId;
    document.getElementById('cat_name').value = name;
    showModal();
}
function showModal() { document.getElementById('modal').classList.remove('hidden'); }
function closeModal() { document.getElementById('modal').classList.add('hidden'); }

function openMoveModal(id, name, currentParentId) {
    document.getElementById('moveTargetName').textContent = '把「' + name + '」移动到：';
    document.getElementById('move_id').value = id;
    const sel = document.getElementById('move_target');
    for (const opt of sel.options) opt.selected = (parseInt(opt.value) === currentParentId);
    document.getElementById('moveModal').classList.remove('hidden');
}
function closeMoveModal() { document.getElementById('moveModal').classList.add('hidden'); }

function deleteCategory(id, name, isLevel1) {
    const msg = isLevel1
        ? '确定要删除一级分类「' + name + '」吗？\n该分类下的所有二级分类也会被删除！\n\n注意：删除后产品 SKU 会保留原值，如需更新请手动重新生成。'
        : '确定要删除二级分类「' + name + '」吗？\n该分类下的产品将被移到「未分类」。\n\n注意：删除后产品 SKU 会保留原值，如需更新请手动重新生成。';
    if (!confirm(msg)) return;
    const f = document.createElement('form');
    f.method = 'POST'; f.action = '/api/category_delete.php';
    f.innerHTML = '<input name="_csrf" value="' + CSRF + '"><input name="id" value="' + id + '">';
    document.body.appendChild(f); f.submit();
}

// 拖拽排序
<?php if ($canManage): ?>
document.addEventListener('DOMContentLoaded', () => {
    // 一级分类拖拽
    const level1Container = document.getElementById('level1Container');
    if (level1Container) {
        initDragSort(level1Container, '[data-level="1"]', async (ids) => {
            await reorderRequest(ids, 0);
        });
    }
    // 每个一级下的二级分类拖拽
    document.querySelectorAll('.level2-list').forEach(container => {
        const parentId = container.dataset.parent;
        initDragSort(container, 'li[data-id]', async (ids) => {
            await reorderRequest(ids, parseInt(parentId));
        });
    });
});

async function reorderRequest(ids, parentId) {
    try {
        const fd = new FormData();
        fd.append('_csrf', CSRF);
        fd.append('parent_id', parentId);
        ids.forEach(id => fd.append('ids[]', id));
        const r = await fetch('/api/category_reorder.php', { method: 'POST', body: fd });
        const d = await r.json();
        if (!d.ok) alert('排序失败：' + d.message);
    } catch (e) {
        alert('排序失败：' + e.message);
    }
}
<?php endif; ?>
</script>

<?php require __DIR__ . '/includes/views/footer.php'; ?>
