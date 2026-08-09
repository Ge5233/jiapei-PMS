<?php
/**
 * 分类管理（A+B：折叠 + ▲▼排序 + 显示系数）
 */
define('PMS_ENTRY', true);
require_once __DIR__ . '/includes/bootstrap.php';
if (!PMS_INSTALLED) { header('Location: /install.php'); exit; }
requireLogin();
requireCostView();

$canManage = isAdmin();

$categories = Category::allGrouped();

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
        <p class="text-sm text-slate-500 mt-0.5">支持两级分类 · 点击标题栏收起/展开</p>
    </div>
    <?php if ($canManage): ?>
    <button class="btn btn-primary" onclick="openLevel1Modal()">
        <i data-lucide="plus" class="w-4 h-4 mr-1.5"></i>新增一级分类
    </button>
    <?php endif; ?>
</div>

<?php if (empty($categories)): ?>
    <div class="card p-12 text-center text-slate-400">
        <i data-lucide="folder-open" class="w-12 h-12 mx-auto mb-3"></i>
        <p>暂无分类</p>
        <?php if ($canManage): ?>
            <button class="btn btn-primary mt-4 inline-flex" onclick="openLevel1Modal()">
                <i data-lucide="plus" class="w-4 h-4 mr-1.5"></i>创建第一个分类
            </button>
        <?php endif; ?>
    </div>
<?php else: ?>
    <div class="space-y-3" id="level1Container">
        <?php foreach ($categories as $c): ?>
            <?php
            $gm = (float)($c['guide_margin_rate'] ?? 30.00);
            $mm = (float)($c['min_margin_rate'] ?? 15.00);
            ?>
            <div class="card cat-card" data-id="<?= $c['id'] ?>" data-level="1">
                <!-- 一级分类标题栏 -->
                <div class="card-header flex items-center justify-between bg-slate-50 cursor-pointer select-none"
                     onclick="toggleCollapse(this)">
                    <div class="flex items-center gap-2 min-w-0" onclick="event.stopPropagation()">
                        <i data-lucide="chevron-right" class="w-4 h-4 text-slate-400 collapse-icon -rotate-90 inline-block transition-transform"></i>
                        <i data-lucide="folder" class="w-4 h-4 text-blue-500 flex-shrink-0"></i>
                        <span class="font-medium truncate"><?= h($c['name']) ?></span>
                        <span class="text-xs text-slate-400 flex-shrink-0">(编号: <?= $c['parent_sort_id'] ?>)</span>
                        <span class="badge badge-slate text-xs flex-shrink-0">一级分类</span>
                        <!-- 定价系数 -->
                        <span class="text-xs text-slate-400 hidden sm:inline flex-shrink-0">
                            售价毛利率 <b class="text-slate-600"><?= number_format($gm, 1) ?>%</b>
                            最低毛利率 <b class="text-slate-600"><?= number_format($mm, 1) ?>%</b>
                        </span>
                    </div>

                    <div class="flex items-center gap-1 flex-shrink-0">
                        <?php if ($canManage): ?>
                        <!-- ▲▼排序 -->
                        <button class="btn-ghost-xs sort-up" onclick="sortUp(this.closest('.cat-card'))" title="上移">
                            <i data-lucide="arrow-up" class="w-3.5 h-3.5"></i>
                        </button>
                        <button class="btn-ghost-xs sort-down" onclick="sortDown(this.closest('.cat-card'))" title="下移">
                            <i data-lucide="arrow-down" class="w-3.5 h-3.5"></i>
                        </button>
                        <?php endif; ?>
                        <button class="btn-ghost-xs" onclick="event.stopPropagation();openLevel2Modal(<?= $c['id'] ?>, '<?= h(addslashes($c['name'])) ?>')" title="新增子类">
                            <i data-lucide="plus" class="w-3.5 h-3.5"></i>
                        </button>
                        <button class="btn-ghost-xs" onclick="event.stopPropagation();openEditModal(<?= $c['id'] ?>, '<?= h(addslashes($c['name'])) ?>', 0, <?= $gm ?>, <?= $mm ?>)" title="编辑">
                            <i data-lucide="edit-2" class="w-3.5 h-3.5"></i>
                        </button>
                        <button class="btn-ghost-xs" onclick="event.stopPropagation();deleteCategory(<?= $c['id'] ?>, '<?= h(addslashes($c['name'])) ?>', true)" title="删除">
                            <i data-lucide="trash-2" class="w-3.5 h-3.5 text-red-500"></i>
                        </button>
                    </div>
                </div>

                <!-- 二级分类列表（可折叠） -->
                <div class="card-body p-0 level2-body hidden" data-parent="<?= $c['id'] ?>">
                    <?php if (empty($c['children'])): ?>
                        <div class="px-5 py-4 text-sm text-slate-400 text-center">暂无二级分类</div>
                    <?php else: ?>
                        <ul class="divide-y divide-slate-100 level2-list">
                            <?php foreach ($c['children'] as $sub): ?>
                                <li class="px-5 py-3 flex items-center justify-between hover:bg-slate-50 cat-item"
                                    data-id="<?= $sub['id'] ?>" data-level="2" data-parent="<?= $c['id'] ?>">
                                    <div class="flex items-center gap-2 flex-1 min-w-0">
                                        <i data-lucide="file" class="w-3.5 h-3.5 text-slate-400 flex-shrink-0"></i>
                                        <span class="text-sm truncate"><?= h($sub['name']) ?></span>
                                        <span class="text-xs text-slate-400 flex-shrink-0">(编号: <?= $sub['sub_id'] ?>)</span>
                                        <span class="text-xs text-slate-400 flex-shrink-0">· <?= getCount($sub['id'], $productCounts) ?> 个产品</span>
                                    </div>
                                    <div class="flex items-center gap-1 flex-shrink-0">
                                        <?php if ($canManage): ?>
                                        <button class="btn-ghost-xs sort-up" onclick="sortUp(this.closest('.cat-item'))" title="上移">
                                            <i data-lucide="arrow-up" class="w-3 h-3"></i>
                                        </button>
                                        <button class="btn-ghost-xs sort-down" onclick="sortDown(this.closest('.cat-item'))" title="下移">
                                            <i data-lucide="arrow-down" class="w-3 h-3"></i>
                                        </button>
                                        <?php endif; ?>
                                        <button class="btn btn-ghost btn-sm" onclick="openEditModal(<?= $sub['id'] ?>, '<?= h(addslashes($sub['name'])) ?>', <?= $c['id'] ?>)">
                                            <i data-lucide="edit-2" class="w-3.5 h-3.5"></i>
                                        </button>
                                        <button class="btn btn-ghost btn-sm" onclick="deleteCategory(<?= $sub['id'] ?>, '<?= h(addslashes($sub['name'])) ?>', false)">
                                            <i data-lucide="trash-2" class="w-3.5 h-3.5 text-red-500"></i>
                                        </button>
                                    </div>
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
                <div id="level1Fields" class="hidden">
                    <div class="grid grid-cols-2 gap-3 mb-4">
                        <div>
                            <label class="form-label">默认指导毛利率</label>
                            <div class="relative">
                                <input type="number" step="0.01" min="0" max="99" name="guide_margin_rate" id="cat_gm_rate" class="form-input pr-8" value="30.00">
                                <span class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm">%</span>
                            </div>
                        </div>
                        <div>
                            <label class="form-label">默认最低毛利率</label>
                            <div class="relative">
                                <input type="number" step="0.01" min="0" max="99" name="min_margin_rate" id="cat_mm_rate" class="form-input pr-8" value="15.00">
                                <span class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm">%</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="flex justify-end gap-2">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">取消</button>
                    <button type="submit" class="btn btn-primary">保存</button>
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
    document.getElementById('cat_gm_rate').value = '30.00';
    document.getElementById('cat_mm_rate').value = '15.00';
    document.getElementById('level1Fields').classList.remove('hidden');
    showModal();
}
function openLevel2Modal(parentId, parentName) {
    document.getElementById('modalTitle').textContent = '在「' + parentName + '」下新增二级分类';
    document.getElementById('cat_id').value = '';
    document.getElementById('cat_parent_id').value = parentId;
    document.getElementById('cat_name').value = '';
    document.getElementById('level1Fields').classList.add('hidden');
    showModal();
}
function openEditModal(id, name, parentId, gpCoef, mpCoef) {
    document.getElementById('modalTitle').textContent = parentId === 0 ? '编辑一级分类' : '编辑二级分类';
    document.getElementById('cat_id').value = id;
    document.getElementById('cat_parent_id').value = parentId;
    document.getElementById('cat_name').value = name;
    if (parentId === 0) {
        document.getElementById('cat_gm_rate').value = gpCoef || 30.00;
        document.getElementById('cat_mm_rate').value = mpCoef || 15.00;
        document.getElementById('level1Fields').classList.remove('hidden');
    } else {
        document.getElementById('level1Fields').classList.add('hidden');
    }
    showModal();
}
function showModal() { document.getElementById('modal').classList.remove('hidden'); }
function closeModal() { document.getElementById('modal').classList.add('hidden'); }

function deleteCategory(id, name, isLevel1) {
    const msg = isLevel1
        ? '确定要删除一级分类「' + name + '」吗？\n该分类下的所有二级分类也会被删除！'
        : '确定要删除二级分类「' + name + '」吗？\n该分类下的产品将被移到「未分类」。';
    if (!confirm(msg)) return;
    const f = document.createElement('form');
    f.method = 'POST'; f.action = '/api/category_delete.php';
    f.innerHTML = '<input name="_csrf" value="' + CSRF + '"><input name="id" value="' + id + '">';
    document.body.appendChild(f); f.submit();
}

// 折叠
function toggleCollapse(header) {
    const body = header.nextElementSibling;
    const icon = header.querySelector('.collapse-icon');
    if (!body || !icon) return;
    body.classList.toggle('hidden');
    icon.classList.toggle('-rotate-90');
}

// ▲▼ 排序
function sortUp(el) {
    const prev = el.previousElementSibling;
    if (!prev) return;
    el.parentNode.insertBefore(el, prev);
    submitSort(el);
}
function sortDown(el) {
    const next = el.nextElementSibling;
    if (!next) return;
    el.parentNode.insertBefore(next, el);
    submitSort(el);
}

async function submitSort(el) {
    const parentEl = el.closest('.cat-card[data-level="1"]');
    let parentId = 0;
    let selector;
    if (el.dataset.level === '1') {
        // 一级分类重排
        selector = '.cat-card[data-level="1"]';
        parentId = 0;
    } else {
        // 二级分类重排
        const parentCard = el.closest('.cat-card');
        parentId = parentCard ? parseInt(parentCard.dataset.id) : 0;
        const list = el.closest('.level2-list');
        selector = '.cat-item';
    }
    const container = el.dataset.level === '1' ? document.getElementById('level1Container') : el.closest('.level2-list');
    if (!container) return;
    const items = container.querySelectorAll(selector);
    const ids = [];
    items.forEach(item => { ids.push(parseInt(item.dataset.id)); });

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
</script>

<?php require __DIR__ . '/includes/views/footer.php'; ?>
