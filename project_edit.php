<?php
/**
 * 项目 新增/编辑
 */
define('PMS_ENTRY', true);
require_once __DIR__ . '/includes/bootstrap.php';
if (!PMS_INSTALLED) { header('Location: /install.php'); exit; }
requireLogin();
requireCostView();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$isEdit = $id > 0;
$project = null;
$items = [];

if ($isEdit) {
    $project = Project::find($id);
    if (!$project) {
        flash('error', '项目不存在');
        header('Location: /projects.php');
        exit;
    }
    $items = Project::products($id);
}

// 外采产品 + 自产产品（给项目加产品用）
$allProducts = Product::allForSelect();
$allSelfProducts = class_exists('SelfProduct') ? SelfProduct::allForSelect() : [];

// JSON for JS
$prodJson = json_encode(array_map(fn($p) => [
    'id' => $p['id'],
    'label' => $p['sku'] . ' ' . $p['name'],
    'spec' => $p['spec'] ?? '',
    'unit' => $p['unit'] ?? '',
    'price' => (float)($p['cost_price'] ?? 0),
], $allProducts), JSON_UNESCAPED_UNICODE);
$spJson = json_encode(array_map(fn($sp) => [
    'id' => $sp['id'],
    'label' => $sp['name'],
    'spec' => $sp['spec'] ?? '',
    'unit' => $sp['unit'] ?? '',
], $allSelfProducts), JSON_UNESCAPED_UNICODE);

$pageTitle = $isEdit ? '编辑项目' : '新建项目';
$activeMenu = 'projects';
require __DIR__ . '/includes/views/header.php';
?>

<div class="max-w-5xl mx-auto" x-data="projectForm(<?= h(json_encode([
    'id' => $id,
    'isEdit' => $isEdit,
    'project' => $project,
    'items' => $items,
], JSON_UNESCAPED_UNICODE)) ?>)">

    <div class="flex items-center justify-between mb-4">
        <h2 class="text-lg font-medium text-slate-800"><?= $isEdit ? '编辑项目' : '新建项目' ?></h2>
        <a href="/projects.php" class="btn btn-secondary text-sm">
            <i data-lucide="arrow-left" class="w-4 h-4 mr-1"></i>返回列表
        </a>
    </div>

    <form @submit.prevent="save" id="projectForm">
    <?= csrfField() ?>

    <!-- 项目基本信息 -->
    <div class="card p-6 mb-4">
        <h3 class="text-base font-medium text-slate-800 mb-4 pb-2 border-b border-slate-100">基本信息</h3>
        <div class="grid grid-cols-2 gap-4 mb-4">
            <div>
                <label class="form-label">项目名称 <span class="text-red-500">*</span></label>
                <input type="text" x-model="form.name" class="form-input" required maxlength="200" placeholder="例：无锡XX植物工厂项目">
            </div>
            <div>
                <label class="form-label">客户名称</label>
                <input type="text" x-model="form.customer_name" class="form-input" maxlength="100" placeholder="客户公司名称">
            </div>
        </div>
        <div class="mb-0">
            <label class="form-label">备注</label>
            <textarea x-model="form.remark" class="form-input" rows="2" maxlength="2000" placeholder="项目整体说明"></textarea>
        </div>
    </div>

    <!-- 项目产品 -->
    <div class="card p-6 mb-4">
        <div class="flex items-center justify-between mb-4 pb-2 border-b border-slate-100">
            <h3 class="text-base font-medium text-slate-800">项目产品</h3>
            <div class="flex gap-2">
                <button type="button" class="btn btn-secondary text-sm" @click="addItem('purchase')">
                    <i data-lucide="shopping-cart" class="w-3.5 h-3.5 mr-1"></i>加外采料
                </button>
                <button type="button" class="btn btn-secondary text-sm" @click="addItem('self_product')">
                    <i data-lucide="factory" class="w-3.5 h-3.5 mr-1"></i>加自产需求
                </button>
                <button type="button" class="btn btn-secondary text-sm" @click="addItem('adhoc')">
                    <i data-lucide="plus" class="w-3.5 h-3.5 mr-1"></i>加临时料
                </button>
            </div>
        </div>

        <!-- 空状态 -->
        <div x-show="items.length === 0" class="text-center py-8 text-slate-400 text-sm">
            <i data-lucide="clipboard-list" class="w-8 h-8 mx-auto mb-2 text-slate-300"></i>
            还没加产品。外采料直接采购，自产需求发给产品经理。
        </div>

        <!-- 产品列表 -->
        <template x-for="(it, idx) in items" :key="idx">
            <div class="border border-slate-200 rounded mb-3 p-3">
                <div class="flex items-center gap-3 mb-2">
                    <!-- 类型 -->
                    <select x-model="it.item_type" class="form-select text-xs py-1 px-2 w-32" @change="typeChanged(idx)">
                        <option value="purchase">外采料</option>
                        <option value="self_product">自产需求</option>
                        <option value="adhoc">临时料</option>
                    </select>

                    <!-- 外采料：搜产品 -->
                    <div x-show="it.item_type==='purchase'" class="relative flex-1">
                        <template x-if="!it.product_id">
                            <input type="text" @focus="it._open=true" @input="it._filter=$el.value" @keydown.escape="it._open=false"
                                   @click.away="it._open=false"
                                   x-model="it._show" class="form-input text-sm" placeholder="搜索外采产品..." autocomplete="off">
                        </template>
                        <template x-if="it.product_id">
                            <div class="ss-tag cursor-pointer min-w-[200px]" @click="it._open=true">
                                <span x-text="it._show"></span>
                                <span class="ss-tag-x" @click.stop="clearProduct(idx)">&times;</span>
                            </div>
                        </template>
                        <div x-show="it._open && filteredProducts(it._filter||'').length>0" class="ss-dropdown">
                            <template x-for="p in filteredProducts(it._filter||'')" :key="p.id">
                                <div @mousedown.prevent="pickProduct(idx, p)" :class="{sel:it.product_id==p.id}">
                                    <span x-text="p.label"></span>
                                    <span class="text-xs text-slate-400 ml-1" x-text="p.spec ? '【'+p.spec+'】' : ''"></span>
                                </div>
                            </template>
                        </div>
                    </div>

                    <!-- 自产需求：搜自产产品 -->
                    <div x-show="it.item_type==='self_product'" class="relative flex-1">
                        <template x-if="!it.self_product_id">
                            <input type="text" @focus="it._open=true" @input="it._filter=$el.value" @keydown.escape="it._open=false"
                                   @click.away="it._open=false"
                                   x-model="it._show" class="form-input text-sm" placeholder="搜索自产产品..." autocomplete="off">
                        </template>
                        <template x-if="it.self_product_id">
                            <div class="ss-tag cursor-pointer min-w-[200px]" @click="it._open=true">
                                <span x-text="it._show"></span>
                                <span class="ss-tag-x" @click.stop="clearProduct(idx)">&times;</span>
                            </div>
                        </template>
                        <div x-show="it._open && filteredSp(it._filter||'').length>0" class="ss-dropdown">
                            <template x-for="s in filteredSp(it._filter||'')" :key="s.id">
                                <div @mousedown.prevent="pickSp(idx, s)" :class="{sel:it.self_product_id==s.id}" x-text="s.label"></div>
                            </template>
                        </div>
                    </div>

                    <!-- 临时料：手填名称 -->
                    <div x-show="it.item_type==='adhoc'" class="flex-1">
                        <input type="text" x-model="it.item_name" class="form-input text-sm" placeholder="临时料名称">
                    </div>

                    <!-- 数量 -->
                    <div class="w-28">
                        <input type="number" x-model="it.quantity" step="0.0001" min="0" class="form-input text-sm text-right" placeholder="数量">
                    </div>
                    <!-- 单位 -->
                    <div class="w-20">
                        <input type="text" x-model="it.unit" class="form-input text-sm text-center" placeholder="单位">
                    </div>
                    <!-- 删除 -->
                    <button type="button" class="text-red-400 hover:text-red-600 text-sm" @click="items.splice(idx,1)" title="删除">&times;</button>
                </div>

                <!-- 需求说明（自产产品时显示） -->
                <div x-show="it.item_type==='self_product'" class="mt-2">
                    <textarea x-model="it.requirement" class="form-input text-sm" rows="2" placeholder="需求说明：这单要什么功能、什么特殊要求（写给产品经理）"></textarea>
                </div>
                <!-- 备注（其它类型） -->
                <div x-show="it.item_type!=='self_product'" class="mt-2">
                    <input type="text" x-model="it.remark" class="form-input text-sm" placeholder="备注（可选）">
                </div>
            </div>
        </template>
    </div>

    <!-- 操作按钮 -->
    <div class="flex items-center justify-between">
        <a href="/projects.php" class="btn btn-secondary">返回</a>
        <button type="submit" class="btn btn-primary" id="btnSaveProject">
            <i data-lucide="save" class="w-4 h-4 mr-1.5"></i>
            <span x-text="isEdit ? '保存修改' : '创建项目'"></span>
        </button>
    </div>
    </form>
</div>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('projectForm', (init) => {
        const PL = <?= $prodJson ?> || [];
        const SL = <?= $spJson ?> || [];

        function normItem(it) {
            const itemType = it.item_type === 'self_product' ? 'self_product'
                : (it.product_id ? 'purchase' : (it.item_name ? 'adhoc' : 'purchase'));
            return {
                item_type: itemType,
                product_id: it.product_id ? String(it.product_id) : '',
                self_product_id: it.self_product_id ? String(it.self_product_id) : '',
                item_name: it.item_name || '',
                spec: it.spec || '',
                unit: it.unit || '',
                quantity: it.quantity || 1,
                requirement: it.requirement || '',
                remark: it.remark || '',
                _open: false, _filter: '',
                _show: it.product_id ? (PL.find(x => x.id == it.product_id)?.label || '')
                     : (it.self_product_id ? (SL.find(x => x.id == it.self_product_id)?.label || '') : ''),
            };
        }

        const comp = {
            isEdit: init.isEdit,
            initId: init.id,
            form: {
                name: init.project?.name || '',
                customer_name: init.project?.customer_name || '',
                remark: init.project?.remark || '',
            },
            items: (init.items || []).map(normItem),
            submitted: false,

            addItem(type) {
                this.items.push({
                    item_type: type,
                    product_id: '',
                    self_product_id: '',
                    item_name: '',
                    spec: '',
                    unit: '',
                    quantity: 1,
                    requirement: '',
                    remark: '',
                    _open: false, _filter: '', _show: '',
                });
            },
            typeChanged(idx) {
                const it = this.items[idx];
                it.product_id = ''; it.self_product_id = ''; it.item_name = '';
                it._show = ''; it._filter = '';
                it.requirement = ''; it.remark = '';
            },
            filteredProducts(q) {
                q = (q || '').toLowerCase();
                return q ? PL.filter(p => p.label.toLowerCase().includes(q)) : PL;
            },
            filteredSp(q) {
                q = (q || '').toLowerCase();
                return q ? SL.filter(s => s.label.toLowerCase().includes(q)) : SL;
            },
            pickProduct(idx, p) {
                const it = this.items[idx];
                it.product_id = p.id;
                it._show = p.label;
                it.spec = p.spec || '';
                it.unit = p.unit || '';
                it._filter = ''; it._open = false;
            },
            pickSp(idx, s) {
                const it = this.items[idx];
                it.self_product_id = s.id;
                it._show = s.label;
                it.spec = s.spec || '';
                it.unit = s.unit || '';
                it._filter = ''; it._open = false;
            },
            clearProduct(idx) {
                const it = this.items[idx];
                it.product_id = ''; it.self_product_id = '';
                it._show = ''; it._open = false;
            },

            async save() {
                if (this.submitted) return;
                if (!this.form.name.trim()) { alert('请输入项目名称'); return; }
                this.submitted = true;

                const fd = new FormData();
                fd.append('id', init.id);
                fd.append('name', this.form.name);
                fd.append('customer_name', this.form.customer_name);
                fd.append('status', 'active');
                fd.append('remark', this.form.remark);

                fd.append('items', JSON.stringify(this.items.map((it, i) => ({
                    item_type: it.item_type === 'self_product' ? 'self_product'
                        : (it.product_id ? 'purchase' : 'adhoc'),
                    product_id: it.item_type === 'purchase' ? (it.product_id || null) : null,
                    self_product_id: it.item_type === 'self_product' ? (it.self_product_id || null) : null,
                    item_name: it.item_type === 'adhoc' ? (it.item_name || null) : null,
                    spec: it.spec || null,
                    unit: it.unit || null,
                    quantity: parseFloat(it.quantity) || 0,
                    requirement: it.item_type === 'self_product' ? (it.requirement || null) : null,
                    remark: it.item_type !== 'self_product' ? (it.remark || null) : null,
                }))));

                try {
                    const resp = await fetch('/api/project_save.php', {
                        method: 'POST',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-Token': document.querySelector('input[name="_csrf"]')?.value || '',
                        },
                        body: fd,
                    });
                    const text = await resp.text();
                    let data;
                    try { data = JSON.parse(text); } catch { throw new Error('服务器返回非JSON (' + resp.status + ')'); }
                    if (data.ok) {
                        window.location.href = '/projects.php';
                    } else {
                        alert(data.message || '保存失败');
                        this.submitted = false;
                    }
                } catch (e) {
                    alert('保存失败：' + e.message);
                    this.submitted = false;
                }
            },
        };
        return comp;
    });
});
</script>

<?php require __DIR__ . '/includes/views/footer.php'; ?>
