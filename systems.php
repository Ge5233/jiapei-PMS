<?php
/**
 * 大型系统 BOM 管理
 */
define('PMS_ENTRY', true);
require_once __DIR__ . '/includes/bootstrap.php';
if (!PMS_INSTALLED) { header('Location: /install.php'); exit; }
requireLogin();
requireCostView();

$projects = SystemProject::all();
$allProducts = Product::allForSelect();
$allSelfProducts = class_exists('SelfProduct') ? SelfProduct::allForSelect() : [];

// 构建产品查找 Map（给 JS 用）
$productMap = [];
foreach ($allProducts as $p) { $productMap[$p['id']] = $p; }
$spMap = [];
foreach ($allSelfProducts as $sp) { $spMap[$sp['id']] = $sp; }

$pageTitle = '大型系统 BOM';
$activeMenu = 'systems';
require __DIR__ . '/includes/views/header.php';
?>

<div class="flex h-[calc(100vh-140px)] gap-4" x-data="systemPage()">

<!-- 左侧：项目列表 -->
<div class="w-64 flex-shrink-0 flex flex-col">
    <div class="flex items-center justify-between mb-3">
        <h3 class="text-sm font-medium text-slate-600">系统项目</h3>
        <button class="btn-ghost-xs" @click="newProject()" title="新增">
            <i data-lucide="plus" class="w-4 h-4"></i>
        </button>
    </div>
    <div class="flex-1 overflow-y-auto space-y-1 pr-1">
        <template x-for="p in projects" :key="p.id">
            <div class="flex items-center gap-2 px-3 py-2 rounded cursor-pointer text-sm"
                 :class="selectedId === p.id ? 'bg-blue-50 text-blue-700 font-medium' : 'hover:bg-slate-50 text-slate-700'"
                 @click="selectProject(p.id)">
                <i data-lucide="cpu" class="w-4 h-4 flex-shrink-0" :class="selectedId === p.id ? 'text-blue-500' : 'text-slate-400'"></i>
                <span class="truncate flex-1" x-text="p.name"></span>
                <span class="text-xs flex-shrink-0" :class="p.status == 1 ? 'text-emerald-500' : 'text-slate-400'"
                      x-text="p.status == 1 ? '在建' : '完工'"></span>
            </div>
        </template>
        <div x-show="projects.length === 0" class="text-center text-slate-400 text-sm py-8">
            暂无项目，点 + 新建
        </div>
    </div>
</div>

<!-- 右侧：详情 -->
<div class="flex-1 flex flex-col min-w-0" x-show="selectedId" x-cloak>
    <!-- 标题栏 -->
    <div class="flex items-center justify-between mb-3 flex-shrink-0">
        <div class="flex items-center gap-3">
            <input type="text" x-model="form.name" class="text-lg font-medium bg-transparent border-b-2 border-transparent hover:border-slate-300 focus:border-blue-500 focus:outline-none px-1" placeholder="系统名称">
            <select x-model="form.status" class="text-xs border rounded px-2 py-0.5">
                <option value="1">在建</option>
                <option value="0">完工</option>
            </select>
        </div>
        <div class="flex gap-2">
            <button class="btn btn-secondary text-sm" :class="viewMode === 'module' ? 'ring-2 ring-blue-300' : ''"
                    @click="viewMode='module'">按模块</button>
            <button class="btn btn-secondary text-sm" :class="viewMode === 'summary' ? 'ring-2 ring-blue-300' : ''"
                    @click="viewMode='summary'">物料汇总</button>
            <button class="btn btn-primary text-sm" @click="save()">保存</button>
            <button class="btn btn-ghost text-sm text-red-500" @click="deleteProject()">删除</button>
        </div>
    </div>

    <!-- 备注 -->
    <div class="mb-3 flex-shrink-0">
        <textarea x-model="form.description" class="form-input text-sm w-full" rows="1" placeholder="备注说明（可选）"></textarea>
    </div>

    <!-- 模块视图 -->
    <div class="flex-1 overflow-y-auto" x-show="viewMode === 'module'">
        <template x-for="(mod, mi) in modules" :key="mi">
            <div class="mb-4 card">
                <!-- 模块标题 -->
                <div class="flex items-center gap-3 px-4 py-2.5 bg-slate-50 rounded-t border-b cursor-pointer" @click="mod._collapsed = !mod._collapsed">
                    <i data-lucide="chevron-right" class="w-4 h-4 text-slate-400 transition-transform"
                       :class="mod._collapsed ? '' : 'rotate-90'"></i>
                    <input type="text" x-model="mod.name" class="font-medium text-sm bg-transparent border-b border-transparent hover:border-slate-300 focus:border-blue-500 focus:outline-none flex-1" placeholder="模块名称" @click.stop="">
                    <span class="text-xs text-slate-500 tabular-nums" x-text="'¥' + formatMoney(moduleTotal(mi))"></span>
                    <button class="btn-ghost-xs" @click.stop="addItem(mi)" title="+主材"><i data-lucide="plus" class="w-3 h-3"></i></button>
                    <button class="btn-ghost-xs" @click.stop="removeModule(mi)" title="删"><i data-lucide="x" class="w-3 h-3 text-red-400"></i></button>
                    <button class="btn-ghost-xs" @click.stop="moveModule(mi, -1)" title="上移"><i data-lucide="arrow-up" class="w-3 h-3"></i></button>
                    <button class="btn-ghost-xs" @click.stop="moveModule(mi, 1)" title="下移"><i data-lucide="arrow-down" class="w-3 h-3"></i></button>
                </div>

                <!-- 主材列表 -->
                <div class="divide-y" x-show="!mod._collapsed">
                    <template x-for="(item, ii) in mod.items" :key="ii">
                        <div>
                            <!-- 主材行 -->
                            <div class="flex items-center gap-2 px-4 py-2 text-sm hover:bg-slate-50">
                                <i data-lucide="chevron-right" class="w-3 h-3 text-slate-300 cursor-pointer transition-transform"
                                   :class="item._collapsed ? '' : 'rotate-90'" @click="item._collapsed = !item._collapsed"></i>
                                <select x-model="item.source_type" class="text-xs border rounded px-1 py-0.5 w-16" @change="onSourceChange(item)">
                                    <option value="product">外采</option>
                                    <option value="self_product">自产</option>
                                    <option value="adhoc">临时</option>
                                </select>
                                <!-- 外采/自产: 下拉选 -->
                                <template x-if="item.source_type === 'product'">
                                    <select class="text-sm border rounded flex-1 min-w-0" @change="linkProduct(item, $event.target.value)">
                                        <option value="">-- 选择 --</option>
                                        <template x-for="p in allProducts" :key="p.id">
                                            <option :value="p.id" x-text="p.sku+' '+p.name" :selected="item.product_id == p.id"></option>
                                        </template>
                                    </select>
                                </template>
                                <template x-if="item.source_type === 'self_product'">
                                    <select class="text-sm border rounded flex-1 min-w-0" @change="linkSp(item, $event.target.value)">
                                        <option value="">-- 选择 --</option>
                                        <template x-for="sp in allSelfProducts" :key="sp.id">
                                            <option :value="sp.id" x-text="sp.name" :selected="item.self_product_id == sp.id"></option>
                                        </template>
                                    </select>
                                </template>
                                <template x-if="item.source_type === 'adhoc'">
                                    <input type="text" x-model="item.item_name" class="text-sm border rounded px-1 flex-1 min-w-0" placeholder="物料名">
                                </template>
                                <input type="text" x-model="item.spec" class="text-sm border rounded px-1 w-24" placeholder="规格">
                                <input type="text" x-model="item.unit" class="text-sm border rounded px-1 w-12 text-center" placeholder="单位">
                                <input type="number" x-model="item.quantity" step="0.01" min="0" class="text-sm border rounded px-1 w-16 text-right" @input="calcItem(item)">
                                <div class="w-20 text-right tabular-nums text-xs" x-text="'¥' + formatMoney(item.unit_price)"></div>
                                <div class="w-20 text-right tabular-nums text-xs font-medium" x-text="'¥' + formatMoney(itemTotal(item))"></div>
                                <button class="btn-ghost-xs" @click="addSubItem(item)" title="+配件"><i data-lucide="corner-down-right" class="w-3 h-3"></i></button>
                                <button class="btn-ghost-xs" @click="removeItem(mod, ii)" title="删"><i data-lucide="x" class="w-3 h-3 text-red-400"></i></button>
                            </div>

                            <!-- 紧固件子行 -->
                            <template x-if="!item._collapsed && item.sub_items && item.sub_items.length > 0">
                                <div class="bg-slate-50/50 border-l-2 border-blue-200 ml-8">
                                    <template x-for="(sub, si) in item.sub_items" :key="si">
                                        <div class="flex items-center gap-2 px-4 py-1.5 text-xs">
                                            <select x-model="sub.source_type" class="text-xs border rounded px-1 py-0.5 w-14" @change="onSubSourceChange(sub)">
                                                <option value="product">外采</option>
                                                <option value="self_product">自产</option>
                                                <option value="adhoc">临时</option>
                                            </select>
                                            <template x-if="sub.source_type === 'product'">
                                                <select class="text-xs border rounded flex-1 min-w-0" @change="linkSubProduct(sub, $event.target.value)">
                                                    <option value="">-- 选择 --</option>
                                                    <template x-for="p in allProducts" :key="p.id">
                                                        <option :value="p.id" x-text="p.sku+' '+p.name" :selected="sub.product_id == p.id"></option>
                                                    </template>
                                                </select>
                                            </template>
                                            <template x-if="sub.source_type === 'self_product'">
                                                <select class="text-xs border rounded flex-1 min-w-0" @change="linkSubSp(sub, $event.target.value)">
                                                    <option value="">-- 选择 --</option>
                                                    <template x-for="sp in allSelfProducts" :key="sp.id">
                                                        <option :value="sp.id" x-text="sp.name" :selected="sub.self_product_id == sp.id"></option>
                                                    </template>
                                                </select>
                                            </template>
                                            <template x-if="sub.source_type === 'adhoc'">
                                                <input type="text" x-model="sub.item_name" class="text-xs border rounded px-1 flex-1 min-w-0" placeholder="名称">
                                            </template>
                                            <input type="text" x-model="sub.spec" class="text-xs border rounded px-1 w-20" placeholder="规格">
                                            <input type="text" x-model="sub.unit" class="text-xs border rounded px-1 w-10 text-center" placeholder="单位">
                                            <input type="number" x-model="sub.quantity" step="0.01" min="0" class="text-xs border rounded px-1 w-14 text-right">
                                            <div class="w-16 text-right tabular-nums" x-text="'¥' + formatMoney(sub.unit_price)"></div>
                                            <div class="w-16 text-right tabular-nums font-medium" x-text="'¥' + formatMoney(sub.quantity * sub.unit_price)"></div>
                                            <button class="btn-ghost-xs" @click="removeSubItem(item, si)" title="删"><i data-lucide="x" class="w-3 h-3 text-red-400"></i></button>
                                        </div>
                                    </template>
                                </div>
                            </template>
                        </div>
                    </template>
                    <div x-show="mod.items.length === 0" class="px-4 py-3 text-xs text-slate-400 text-center">暂无主材，点 + 添加</div>
                </div>
            </div>
        </template>

        <button class="btn btn-secondary text-sm w-full mt-2" @click="addModule()">
            <i data-lucide="plus" class="w-3.5 h-3.5 mr-1"></i>添加模块
        </button>
    </div>

    <!-- 物料汇总视图 -->
    <div class="flex-1 overflow-y-auto" x-show="viewMode === 'summary'">
        <table class="data-table text-sm w-full">
            <thead>
                <tr>
                    <th>物料名称</th>
                    <th>规格</th>
                    <th class="text-right">数量</th>
                    <th>单位</th>
                    <th class="text-right">单价</th>
                    <th class="text-right">金额</th>
                    <th>来源</th>
                </tr>
            </thead>
            <tbody>
                <template x-for="row in summaryRows" :key="row.key">
                    <tr>
                        <td x-text="row.name"></td>
                        <td x-text="row.spec" class="text-slate-500"></td>
                        <td class="text-right tabular-nums" x-text="row.total_qty"></td>
                        <td x-text="row.unit"></td>
                        <td class="text-right tabular-nums" x-text="'¥' + formatMoney(row.unit_price)"></td>
                        <td class="text-right tabular-nums font-medium" x-text="'¥' + formatMoney(row.total_price)"></td>
                        <td class="text-xs text-slate-400" x-text="row.sources"></td>
                    </tr>
                </template>
            </tbody>
        </table>
        <div class="text-right font-medium mt-2 pr-4 text-sm">
            合计：<span class="text-blue-600" x-text="'¥' + formatMoney(summaryTotal)"></span>
        </div>
    </div>
</div>

<!-- 空状态：未选中项目 -->
<div class="flex-1 flex items-center justify-center" x-show="!selectedId">
    <div class="text-center text-slate-400">
        <i data-lucide="cpu" class="w-16 h-16 mx-auto mb-3 opacity-30"></i>
        <p>选择左侧项目查看 BOM</p>
    </div>
</div>

</div>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('systemPage', () => ({
        projects: <?= json_encode($projects, JSON_UNESCAPED_UNICODE) ?>,
        allProducts: <?= json_encode($allProducts, JSON_UNESCAPED_UNICODE) ?>,
        allSelfProducts: <?= json_encode($allSelfProducts, JSON_UNESCAPED_UNICODE) ?>,
        selectedId: 0,
        form: { name: '', description: '', status: 1 },
        modules: [],
        viewMode: 'module',
        CSRF: '<?= csrfToken() ?>',

        selectProject(id) {
            this.selectedId = id;
            const p = this.projects.find(x => x.id === id);
            if (!p) return;
            this.form = { name: p.name, description: p.description || '', status: parseInt(p.status) };
            this.loadBom(id);
        },

        async loadBom(id) {
            try {
                const r = await fetch('/api/system_bom.php?project_id=' + id);
                const d = await r.json();
                this.modules = (d.modules || []).map(m => ({
                    ...m, _collapsed: false,
                    items: (m.items || []).map(it => ({
                        ...it, _collapsed: true,
                        product_id: it.product_id ? String(it.product_id) : '',
                        self_product_id: it.self_product_id ? String(it.self_product_id) : '',
                        sub_items: it.sub_items || [],
                        quantity: parseFloat(it.quantity) || 0,
                        unit_price: parseFloat(it.unit_price) || 0,
                    }))
                }));
                this.$nextTick(() => { if (window.lucide) lucide.createIcons(); });
            } catch(e) { alert('加载失败：' + e.message); }
        },

        newProject() {
            this.selectedId = 0;
            this.form = { name: '新系统项目', description: '', status: 1 };
            this.modules = [];
        },

        async save() {
            const fd = new FormData();
            if (this.selectedId) fd.append('id', this.selectedId);
            fd.append('name', this.form.name);
            fd.append('description', this.form.description || '');
            fd.append('status', this.form.status);
            fd.append('bom', JSON.stringify(this.modules.map(m => ({
                name: m.name, module_no: m.module_no || '',
                items: (m.items || []).map(it => ({
                    source_type: it.source_type,
                    product_id: it.source_type === 'product' ? (it.product_id || null) : null,
                    self_product_id: it.source_type === 'self_product' ? (it.self_product_id || null) : null,
                    item_name: it.source_type === 'adhoc' ? it.item_name : null,
                    spec: it.spec || '',
                    unit: it.unit || '',
                    quantity: parseFloat(it.quantity) || 0,
                    unit_price: parseFloat(it.unit_price) || 0,
                    sub_items: (it.sub_items || []).map(sub => ({
                        source_type: sub.source_type,
                        product_id: sub.source_type === 'product' ? (sub.product_id || null) : null,
                        self_product_id: sub.source_type === 'self_product' ? (sub.self_product_id || null) : null,
                        item_name: sub.source_type === 'adhoc' ? sub.item_name : null,
                        spec: sub.spec || '',
                        unit: sub.unit || '',
                        quantity: parseFloat(sub.quantity) || 0,
                        unit_price: parseFloat(sub.unit_price) || 0,
                    })),
                })),
            }))));
            fd.append('_csrf', this.CSRF);

            try {
                const r = await fetch('/api/system_save.php', { method: 'POST', body: fd });
                const d = await r.json();
                if (d.ok) {
                    if (!this.selectedId) { this.selectedId = d.id; this.form.name && this.projects.unshift({ id: d.id, name: this.form.name, status: this.form.status }); }
                    else {
                        const p = this.projects.find(x => x.id === this.selectedId);
                        if (p) { p.name = this.form.name; p.status = this.form.status; }
                    }
                } else alert(d.message);
            } catch(e) { alert('保存失败：' + e.message); }
        },

        async deleteProject() {
            if (!confirm('删除「' + this.form.name + '」？\n所有模块和物料将被删除！')) return;
            const fd = new FormData();
            fd.append('action', 'delete');
            fd.append('id', this.selectedId);
            fd.append('_csrf', this.CSRF);
            await fetch('/api/system_save.php', { method: 'POST', body: fd });
            this.projects = this.projects.filter(x => x.id !== this.selectedId);
            this.selectedId = 0;
            this.modules = [];
        },

        addModule() { this.modules.push({ name: '', module_no: '', _collapsed: false, items: [] }); },
        removeModule(idx) { this.modules.splice(idx, 1); },
        moveModule(idx, dir) {
            const t = idx + dir;
            if (t < 0 || t >= this.modules.length) return;
            [this.modules[idx], this.modules[t]] = [this.modules[t], this.modules[idx]];
        },

        addItem(mi) {
            this.modules[mi].items.push({
                source_type: 'product', product_id: '', self_product_id: '', item_name: '',
                spec: '', unit: '', quantity: 0, unit_price: 0, _collapsed: true, sub_items: [],
            });
        },
        removeItem(mod, ii) { mod.items.splice(ii, 1); },

        addSubItem(item) {
            item._collapsed = false;
            item.sub_items.push({ source_type: 'adhoc', product_id: '', self_product_id: '', item_name: '', spec: '', unit: '', quantity: 0, unit_price: 0 });
        },
        removeSubItem(item, si) { item.sub_items.splice(si, 1); },

        linkProduct(item, pid) {
            item.product_id = pid;
            if (pid && this.productMap[pid]) {
                const p = this.productMap[pid];
                item.unit_price = parseFloat(p.cost_price) || 0;
                item.unit = p.unit || '';
                item.item_name = '';
            }
        },
        linkSp(item, sid) {
            item.self_product_id = sid;
            if (sid && this.spMap[sid]) {
                const sp = this.spMap[sid];
                item.unit_price = parseFloat(sp.total_cost) || 0;
                item.unit = sp.unit || '';
                item.item_name = '';
            }
        },
        linkSubProduct(sub, pid) {
            sub.product_id = pid;
            if (pid && this.productMap[pid]) {
                const p = this.productMap[pid];
                sub.unit_price = parseFloat(p.cost_price) || 0;
                sub.unit = p.unit || '';
                sub.item_name = '';
            }
        },
        linkSubSp(sub, sid) {
            sub.self_product_id = sid;
            if (sid && this.spMap[sid]) {
                const sp = this.spMap[sid];
                sub.unit_price = parseFloat(sp.total_cost) || 0;
                sub.unit = sp.unit || '';
                sub.item_name = '';
            }
        },
        onSourceChange(item) { item.product_id = ''; item.self_product_id = ''; item.item_name = ''; item.unit_price = 0; },
        onSubSourceChange(sub) { sub.product_id = ''; sub.self_product_id = ''; sub.item_name = ''; sub.unit_price = 0; },

        calcItem(item) { /* 单价在关联产品时已自动填入 */ },
        itemTotal(item) { return (parseFloat(item.quantity) || 0) * (parseFloat(item.unit_price) || 0); },
        moduleTotal(mi) {
            const mod = this.modules[mi];
            let total = 0;
            (mod.items || []).forEach(it => {
                total += this.itemTotal(it);
                (it.sub_items || []).forEach(sub => {
                    total += (parseFloat(sub.quantity) || 0) * (parseFloat(sub.unit_price) || 0);
                });
            });
            return total;
        },

        // Product map for quick lookup
        get productMap() {
            const m = {};
            this.allProducts.forEach(p => { m[p.id] = p; });
            return m;
        },
        get spMap() {
            const m = {};
            this.allSelfProducts.forEach(sp => { m[sp.id] = sp; });
            return m;
        },

        // Summary view
        get summaryRows() {
            const map = {};
            const addToMap = (key, name, spec, unit, qty, price, sourceName) => {
                if (!map[key]) map[key] = { key, name, spec, unit, total_qty: 0, unit_price: price, total_price: 0, sources: new Set() };
                map[key].total_qty = +(map[key].total_qty + qty).toFixed(4);
                map[key].total_price = +(map[key].total_price + qty * price).toFixed(2);
                map[key].sources.add(sourceName);
            };
            this.modules.forEach(mod => {
                (mod.items || []).forEach(it => {
                    const nm = it.source_type === 'adhoc' ? it.item_name : (it.product_id ? this.productMap[it.product_id]?.sku + ' ' + this.productMap[it.product_id]?.name : this.spMap[it.self_product_id]?.name);
                    addToMap(nm || '(未命名)', it.spec, it.unit, parseFloat(it.quantity) || 0, parseFloat(it.unit_price) || 0, mod.name);
                    (it.sub_items || []).forEach(sub => {
                        const sn = sub.source_type === 'adhoc' ? sub.item_name : (sub.product_id ? this.productMap[sub.product_id]?.sku + ' ' + this.productMap[sub.product_id]?.name : this.spMap[sub.self_product_id]?.name);
                        addToMap(sn || '(未命名)', sub.spec, sub.unit, parseFloat(sub.quantity) || 0, parseFloat(sub.unit_price) || 0, mod.name);
                    });
                });
            });
            return Object.values(map).map(r => ({ ...r, sources: [...r.sources].join(', ') }));
        },
        get summaryTotal() {
            return this.summaryRows.reduce((s, r) => s + r.total_price, 0);
        },

        formatMoney(v) { return (parseFloat(v) || 0).toFixed(2); },
    }));
});
</script>

<?php require __DIR__ . '/includes/views/footer.php'; ?>
