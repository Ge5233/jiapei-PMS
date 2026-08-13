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
    'price' => (float)($sp['total_cost'] ?? 0),
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

        <!-- 列头 -->
        <div x-show="items.length > 0" class="grid grid-cols-[90px_1fr_100px_80px_90px_90px_40px] gap-2 px-3 py-1.5 text-xs text-slate-400 border-b border-slate-100">
            <span>类型</span><span>物料（含规格）</span><span>数量</span><span>单位</span><span>单价</span><span>小计</span><span></span>
        </div>

        <!-- 产品列表 -->
        <template x-for="(it, idx) in items" :key="idx">
            <div class="border-b border-slate-100 py-2">
                <div class="grid grid-cols-[90px_1fr_100px_80px_90px_90px_40px] gap-2 items-center">
                    <!-- 类型 -->
                    <select x-model="it.item_type" class="form-select text-xs py-1 px-1 w-full" @change="typeChanged(idx)">
                        <option value="purchase">外采料</option>
                        <option value="self_product">自产需求</option>
                        <option value="adhoc">临时料</option>
                    </select>

                    <!-- 外采料：搜产品 -->
                    <div x-show="it.item_type==='purchase'" class="relative">
                        <template x-if="!it.product_id">
                            <input type="text" @focus="it._open=true" @input="it._filter=$el.value" @keydown.escape="it._open=false"
                                   @click.away="it._open=false"
                                   x-model="it._show" class="form-input text-sm" placeholder="搜索外采产品..." autocomplete="off">
                        </template>
                        <template x-if="it.product_id">
                            <div class="ss-tag cursor-pointer min-w-[160px]" @click="it._open=true">
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
                    <div x-show="it.item_type==='self_product'" class="relative">
                        <template x-if="!it.self_product_id">
                            <input type="text" @focus="it._open=true" @input="it._filter=$el.value" @keydown.escape="it._open=false"
                                   @click.away="it._open=false"
                                   x-model="it._show" class="form-input text-sm" placeholder="搜索自产产品..." autocomplete="off">
                        </template>
                        <template x-if="it.self_product_id">
                            <div class="ss-tag cursor-pointer min-w-[160px]" @click="it._open=true">
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
                    <div x-show="it.item_type==='adhoc'">
                        <input type="text" x-model="it.item_name" class="form-input text-sm" placeholder="临时料名称">
                    </div>

                    <!-- 数量 -->
                    <input type="number" x-model="it.quantity" step="0.0001" min="0" class="form-input text-sm text-right" placeholder="数量">

                    <!-- 单位 -->
                    <input type="text" x-model="it.unit" class="form-input text-sm text-center" placeholder="单位">

                    <!-- 单价 -->
                    <div class="text-right text-sm tabular-nums text-slate-600">
                        <template x-if="it.item_type==='purchase' || it.item_type==='self_product'">
                            <span x-text="'¥' + fmt(it._price || 0)"></span>
                        </template>
                        <template x-if="it.item_type==='adhoc'">
                            <input type="number" x-model="it._price" step="0.01" min="0" class="form-input text-sm text-right" placeholder="单价">
                        </template>
                    </div>

                    <!-- 小计 -->
                    <div class="text-right text-sm tabular-nums font-medium" x-text="'¥' + fmt((it.quantity||0) * (it._price||0))"></div>

                    <!-- 删除 -->
                    <button type="button" class="text-red-400 hover:text-red-600 text-sm justify-self-center" @click="items.splice(idx,1)" title="删除">&times;</button>
                </div>
            </div>
        </template>

        <!-- 三档合计 -->
        <div x-show="items.length > 0" class="flex justify-end items-center gap-6 pt-3 mt-2 border-t border-slate-200">
            <span class="text-xs text-slate-500">自产小计 <span class="font-medium text-slate-700" x-text="'¥' + fmt(selfTotal)"></span></span>
            <span class="text-xs text-slate-500">外采小计 <span class="font-medium text-slate-700" x-text="'¥' + fmt(purchaseTotal)"></span></span>
            <span class="font-medium text-sm text-slate-800">总计 <span class="text-blue-600" x-text="'¥' + fmt(grandTotal)"></span></span>
        </div>
    </div>

    <!-- 操作按钮 -->
    <div class="flex items-center justify-between">
        <a href="/projects.php" class="btn btn-secondary">返回</a>
        <div class="flex gap-3">
            <button type="button" class="btn btn-secondary" x-show="isEdit" @click="generateTasks">
                <i data-lucide="clipboard-check" class="w-4 h-4 mr-1.5"></i>生成生产任务单
            </button>
            <button type="submit" class="btn btn-primary" id="btnSaveProject">
                <i data-lucide="save" class="w-4 h-4 mr-1.5"></i>
                <span x-text="isEdit ? '保存修改' : '创建项目'"></span>
            </button>
        </div>
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
            const isAdhoc = itemType === 'adhoc';
            return {
                item_type: itemType,
                product_id: it.product_id ? String(it.product_id) : '',
                self_product_id: it.self_product_id ? String(it.self_product_id) : '',
                item_name: it.item_name || '',
                spec: it.spec || '',
                unit: it.unit || '',
                quantity: it.quantity || 1,
                remark: it.remark || '',
                _open: false, _filter: '',
                _show: it.product_id ? (PL.find(x => x.id == it.product_id)?.label || '')
                     : (it.self_product_id ? (SL.find(x => x.id == it.self_product_id)?.label || '') : ''),
                _price: it.product_id ? (PL.find(x => x.id == it.product_id)?.price || 0)
                     : (it.self_product_id ? (SL.find(x => x.id == it.self_product_id)?.price || 0) : 0),
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
                    remark: '',
                    _open: false, _filter: '', _show: '',
                    _price: 0,
                });
            },
            typeChanged(idx) {
                const it = this.items[idx];
                it.product_id = ''; it.self_product_id = ''; it.item_name = '';
                it._show = ''; it._filter = '';
                it._price = 0; it.remark = '';
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
                it._price = p.price || 0;
                it._filter = ''; it._open = false;
            },
            pickSp(idx, s) {
                const it = this.items[idx];
                it.self_product_id = s.id;
                it._show = s.label;
                it.spec = s.spec || '';
                it.unit = s.unit || '';
                it._price = s.price || 0;
                it._filter = ''; it._open = false;
            },
            clearProduct(idx) {
                const it = this.items[idx];
                it.product_id = ''; it.self_product_id = '';
                it._show = ''; it._open = false;
                it._price = 0;
            },
            fmt(v) { return (parseFloat(v) || 0).toFixed(2); },

            // 三档合计
            get selfTotal() {
                return this.items.filter(it => it.item_type === 'self_product')
                    .reduce((t, it) => t + (it.quantity || 0) * (it._price || 0), 0);
            },
            get purchaseTotal() {
                return this.items.filter(it => it.item_type !== 'self_product')
                    .reduce((t, it) => t + (it.quantity || 0) * (it._price || 0), 0);
            },
            get grandTotal() {
                return this.selfTotal + this.purchaseTotal;
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
                    remark: it.remark || null,
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

            async generateTasks() {
                if (!this.isEdit) { alert('请先保存项目再生成生产任务'); return; }
                const selfItems = this.items.filter(it => it.item_type === 'self_product' && it.self_product_id);
                if (selfItems.length === 0) { alert('该项目没有自产产品，无法生成生产任务'); return; }
                if (!confirm('将为 ' + selfItems.length + ' 个自产产品生成生产任务单（待确认状态），确认？')) return;

                const fd = new FormData();
                fd.append('project_id', this.initId);
                fd.append('_csrf', document.querySelector('input[name="_csrf"]')?.value || '');
                try {
                    const resp = await fetch('/api/production_task_generate.php', {
                        method: 'POST',
                        headers: { 'X-Requested-With': 'XMLHttpRequest' },
                        body: fd,
                    });
                    const data = await resp.json();
                    if (data.ok) {
                        alert('已生成 ' + data.count + ' 条生产任务');
                        window.location.href = '/production_tasks.php';
                    } else {
                        alert(data.message || '生成失败');
                    }
                } catch (e) {
                    alert('生成失败：' + e.message);
                }
            },
        };
        return comp;
    });
});
</script>

<?php require __DIR__ . '/includes/views/footer.php'; ?>
