<?php
/**
 * 大型系统 BOM 管理 — v2 简化版
 */
define('PMS_ENTRY', true);
require_once __DIR__ . '/includes/bootstrap.php';
if (!PMS_INSTALLED) { header('Location: /install.php'); exit; }
requireLogin();
requireCostView();

$projects = SystemProject::all();
$allProducts = Product::allForSelect();
$allSelfProducts = class_exists('SelfProduct') ? SelfProduct::allForSelect() : [];

$pageTitle = '大型系统 BOM';
$activeMenu = 'systems';
require __DIR__ . '/includes/views/header.php';
?>
<style>
.collapse-icon { transition: transform 0.2s; }
.collapse-icon.open { transform: rotate(90deg); }
</style>

<div class="flex gap-4" style="height: calc(100vh - 150px)" x-data="systemPage()">

<!-- 左侧列表 -->
<div class="w-56 flex-shrink-0 flex flex-col">
    <div class="flex items-center justify-between mb-2">
        <span class="text-sm font-medium text-slate-600">系统项目</span>
        <button class="text-blue-600 hover:text-blue-800 text-sm" @click="newProject">
            <i data-lucide="plus" class="w-4 h-4 inline"></i>
        </button>
    </div>
    <div class="flex-1 overflow-y-auto space-y-1">
        <template x-for="p in projects" :key="p.id">
            <div class="px-3 py-2 rounded cursor-pointer text-sm truncate"
                 :class="activeId === p.id ? 'bg-blue-50 text-blue-700' : 'hover:bg-slate-50'"
                 @click="select(p.id)" x-text="p.name"></div>
        </template>
        <div x-show="!projects.length" class="text-slate-400 text-sm text-center py-4">暂无项目</div>
    </div>
</div>

<!-- 右侧 -->
<div class="flex-1 flex flex-col min-w-0" x-show="activeId !== null">
    <div class="flex items-center gap-3 mb-3">
        <input x-model="form.name" :readonly="!editMode" class="text-lg font-medium bg-transparent border-b border-slate-200 focus:border-blue-500 focus:outline-none px-1" placeholder="系统名称">
        <select x-model="form.status" class="text-xs border rounded px-2 py-0.5" :disabled="!editMode">
            <option value="1">在建</option>
            <option value="0">完工</option>
        </select>
        <div class="flex gap-2 ml-auto">
            <button class="btn btn-secondary text-sm" @click="editMode = !editMode" x-text="editMode ? '🔒 查看' : '✏️ 编辑'"></button>
            <button class="btn btn-secondary text-sm" :class="{ 'ring-2 ring-blue-300': view === 'module' }" @click="view='module'">按模块</button>
            <button class="btn btn-secondary text-sm" :class="{ 'ring-2 ring-blue-300': view === 'summary' }" @click="view='summary'">物料汇总</button>
            <button class="btn btn-primary text-sm" @click="save" x-show="editMode">保存</button>
            <button class="btn btn-ghost text-sm text-red-500" @click="doDelete" x-show="editMode">删除</button>
        </div>
    </div>
    <textarea x-model="form.desc" :readonly="!editMode" class="form-input text-sm w-full mb-3" rows="2" placeholder="备注"></textarea>

    <!-- 模块视图 -->
    <div class="flex-1 overflow-y-auto" x-show="view === 'module'">
        <template x-for="(mod, mi) in modules" :key="mi">
            <div class="mb-3 border rounded">
                <div class="flex items-center gap-2 px-3 py-2 bg-slate-50 border-b cursor-pointer" @click="mod._open = !mod._open">
                    <i data-lucide="chevron-right" class="w-3.5 h-3.5 collapse-icon" :class="{ open: mod._open }"></i>
                    <input x-model="mod.name" class="font-medium text-sm bg-transparent border-b border-transparent focus:border-blue-500 focus:outline-none flex-1" placeholder="模块名称" @click.stop="">
                    <span class="text-xs text-slate-500" x-text="'¥'+fmt(moduleSum(mi))"></span>
                    <button class="text-blue-500 text-xs" @click.stop="addItem(mi)" x-show="editMode">+主材</button>
                    <button class="text-xs" @click.stop="moveMod(mi,-1)" x-show="editMode">↑</button>
                    <button class="text-xs" @click.stop="moveMod(mi,1)" x-show="editMode">↓</button>
                    <button class="text-xs text-red-400" @click.stop="modules.splice(mi,1)" x-show="editMode">×</button>
                </div>
                <div x-show="mod._open !== false">
                    <div x-show="!(mod.items||[]).length" class="text-center text-xs text-slate-400 py-3">
                        还没有主材，点上方 +主材 添加
                    </div>
                    <template x-for="(it, ii) in (mod.items||[])" :key="ii">
                        <div>
                            <div class="flex items-center gap-1 px-3 py-1.5 text-sm border-b border-slate-50">
                                <select x-model="it.src" class="text-xs border rounded py-0.5 w-14" @change="srcChanged(it)" :disabled="!editMode">
                                    <option value="p">外采</option>
                                    <option value="s">自产</option>
                                    <option value="a">临时</option>
                                </select>
                                <template x-if="it.src === 'p'">
                                    <select x-model="it.pid" class="text-sm border rounded flex-1 min-w-0" @change="linkP(it)" :disabled="!editMode">
                                        <option value="">--选产品--</option>
                                        <template x-for="p in allProducts" :key="p.id">
                                            <option :value="p.id" x-text="p.sku+' '+p.name"></option>
                                        </template>
                                    </select>
                                </template>
                                <template x-if="it.src === 's'">
                                    <select x-model="it.sid" class="text-sm border rounded flex-1 min-w-0" @change="linkS(it)" :disabled="!editMode">
                                        <option value="">--选自产--</option>
                                        <template x-for="sp in allSelf" :key="sp.id">
                                            <option :value="sp.id" x-text="sp.name"></option>
                                        </template>
                                    </select>
                                </template>
                                <template x-if="it.src === 'a'">
                                    <input x-model="it.name" :readonly="!editMode" class="text-sm border rounded px-1 flex-1 min-w-0" placeholder="名称">
                                </template>
                                <input x-model="it.spec" :readonly="!editMode" class="text-sm border rounded px-1 w-20" placeholder="规格">
                                <input x-model="it.unit" :readonly="!editMode" class="text-sm border rounded px-1 w-12 text-center" placeholder="单位">
                                <input x-model.number="it.qty" :readonly="!editMode" class="text-sm border rounded px-1 w-16 text-right" placeholder="数量">
                                <input x-model.number="it.price" :readonly="!editMode" class="text-sm border rounded px-1 w-20 text-right" placeholder="单价">
                                <span class="w-20 text-right text-xs" x-text="'¥'+fmt(it.qty * it.price)"></span>
                                <button class="text-xs text-blue-400" @click="addSub(it)" x-show="editMode">+配件</button>
                                <button class="text-xs text-red-400" @click="mod.items.splice(ii,1)" x-show="editMode">×</button>
                            </div>
                            <!-- 配件 -->
                            <template x-if="(it.subs||[]).length > 0">
                                <div class="bg-slate-50/50 pl-8 border-l-2 border-blue-200">
                                    <template x-for="(s, si) in (it.subs||[])" :key="si">
                                        <div class="flex items-center gap-1 px-3 py-1 text-xs border-b border-slate-100">
                                            <select x-model="s.src" class="text-xs border rounded py-0.5 w-12" @change="srcChanged(s)" :disabled="!editMode">
                                                <option value="p">外采</option>
                                                <option value="s">自产</option>
                                                <option value="a">临时</option>
                                            </select>
                                            <template x-if="s.src === 'p'">
                                                <select x-model="s.pid" class="text-xs border rounded flex-1 min-w-0" @change="linkP(s)" :disabled="!editMode">
                                                    <option value="">--选--</option>
                                                    <template x-for="p in allProducts" :key="p.id">
                                                        <option :value="p.id" x-text="p.sku+' '+p.name"></option>
                                                    </template>
                                                </select>
                                            </template>
                                            <template x-if="s.src === 's'">
                                                <select x-model="s.sid" class="text-xs border rounded flex-1 min-w-0" @change="linkS(s)" :disabled="!editMode">
                                                    <option value="">--选--</option>
                                                    <template x-for="sp in allSelf" :key="sp.id">
                                                        <option :value="sp.id" x-text="sp.name"></option>
                                                    </template>
                                                </select>
                                            </template>
                                            <template x-if="s.src === 'a'">
                                                <input x-model="s.name" :readonly="!editMode" class="text-xs border rounded px-1 flex-1 min-w-0" placeholder="名称">
                                            </template>
                                            <input x-model="s.spec" :readonly="!editMode" class="text-xs border rounded px-1 w-16" placeholder="规格">
                                            <input x-model="s.unit" :readonly="!editMode" class="text-xs border rounded px-1 w-10 text-center">
                                            <input x-model.number="s.qty" :readonly="!editMode" class="text-xs border rounded px-1 w-14 text-right">
                                            <input x-model.number="s.price" :readonly="!editMode" class="text-xs border rounded px-1 w-16 text-right">
                                            <span class="w-16 text-right" x-text="'¥'+fmt(s.qty*s.price)"></span>
                                            <button class="text-xs text-red-400" @click="it.subs.splice(si,1)" x-show="editMode">×</button>
                                        </div>
                                    </template>
                                </div>
                            </template>
                        </div>
                    </template>
                </div>
            </div>
        </template>
        <button class="btn btn-secondary text-sm w-full" @click="addMod" x-show="editMode">+ 添加模块</button>
    </div>

    <!-- 汇总视图 -->
    <div class="flex-1 overflow-y-auto" x-show="view === 'summary'">
        <table class="data-table text-sm w-full">
            <thead><tr><th>物料</th><th>规格</th><th class="text-right">数量</th><th>单位</th><th class="text-right">单价</th><th class="text-right">金额</th><th>来源模块</th></tr></thead>
            <tbody>
                <template x-for="r in summary" :key="r.k">
                    <tr>
                        <td x-text="r.n"></td><td x-text="r.spec" class="text-slate-500"></td>
                        <td class="text-right" x-text="r.q"></td><td x-text="r.u"></td>
                        <td class="text-right" x-text="'¥'+fmt(r.p)"></td>
                        <td class="text-right font-medium" x-text="'¥'+fmt(r.t)"></td>
                        <td class="text-xs text-slate-400" x-text="r.srcs"></td>
                    </tr>
                </template>
            </tbody>
        </table>
        <div class="text-right mt-2 pr-4 font-medium">合计：<span class="text-blue-600" x-text="'¥'+fmt(summaryTotal)"></span></div>
    </div>
</div>

<div class="flex-1 flex items-center justify-center" x-show="activeId === null">
    <div class="text-center text-slate-400"><i data-lucide="cpu" class="w-16 h-16 mx-auto opacity-20"></i><p class="mt-2">选择或新建项目</p></div>
</div>

</div>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('systemPage', () => {
        const ap = <?= json_encode($allProducts, JSON_UNESCAPED_UNICODE) ?>;
        const as = <?= json_encode($allSelfProducts, JSON_UNESCAPED_UNICODE) ?>;
        const pm = {}; ap.forEach(p => pm[p.id] = p);
        const sm = {}; as.forEach(s => sm[s.id] = s);

        return {
            projects: <?= json_encode($projects, JSON_UNESCAPED_UNICODE) ?>,
            allProducts: ap, allSelf: as,
            activeId: null, form: { name: '', desc: '', status: 1 },
            modules: [], view: 'module', editMode: true, CSRF: '<?= csrfToken() ?>',

            select(id) {
                this.activeId = id;
                const p = this.projects.find(x => x.id === id); if (!p) return;
                Object.assign(this.form, { name: p.name, desc: p.description || '', status: parseInt(p.status) });
                this.load(id);
            },
            async load(id) {
                const r = await fetch('/api/system_bom.php?project_id=' + id);
                const d = await r.json();
                this.normModules(d.modules || []);
            },
            normModules(arr) {
                this.modules = arr.map(m => ({
                    name: m.name||'', _open: true,
                    items: (m.items||[]).map(it => ({
                        src: it.self_product_id ? 's' : (it.product_id ? 'p' : (it.item_name ? 'a' : 'p')),
                        pid: it.product_id||'', sid: it.self_product_id||'', name: it.item_name||'',
                        spec: it.spec||'', unit: it.unit||'', qty: parseFloat(it.quantity)||0,
                        price: parseFloat(it.unit_price)||0,
                        subs: (it.sub_items||[]).map(s => ({
                            src: s.self_product_id?'s':(s.product_id?'p':(s.item_name?'a':'a')),
                            pid: s.product_id||'', sid: s.self_product_id||'', name: s.item_name||'',
                            spec: s.spec||'', unit: s.unit||'',
                            qty: parseFloat(s.quantity)||0, price: parseFloat(s.unit_price)||0,
                        })),
                    })),
                }));
                if (!this.modules.length) this.addMod();
            },
            newProject() {
                this.activeId = 'new';
                this.form = { name: '新系统项目', desc: '', status: 1 };
                this.modules = [];
                this.addMod();
            },
            addMod() { this.modules.push({ name: '', _open: true, items: [] }); },
            moveMod(i, d) {
                const t = i + d; if (t < 0 || t >= this.modules.length) return;
                [this.modules[i], this.modules[t]] = [this.modules[t], this.modules[i]];
            },
            addItem(mi) {
                const newItem = { src: 'p', pid: '', sid: '', name: '', spec: '', unit: '', qty: 0, price: 0, subs: [] };
                this.modules[mi].items = [...this.modules[mi].items, newItem];
                this.modules[mi]._open = true;
            },
            addSub(it) { it.subs = [...it.subs, { src: 'a', pid: '', sid: '', name: '', spec: '', unit: '', qty: 0, price: 0 }]; },
            moduleSum(i) {
                const mod = this.modules[i]; let t = 0;
                (mod.items||[]).forEach(it => { t += (it.qty||0)*(it.price||0); (it.subs||[]).forEach(s => t += (s.qty||0)*(s.price||0)); });
                return t;
            },
            linkP(it) { if (it.pid && pm[it.pid]) { const p = pm[it.pid]; it.price = parseFloat(p.cost_price)||0; it.unit = p.unit||''; it.name = ''; } },
            linkS(it) { if (it.sid && sm[it.sid]) { const s = sm[it.sid]; it.price = parseFloat(s.total_cost)||0; it.unit = s.unit||''; it.name = ''; } },
            srcChanged(it) { ['pid','sid','name','price'].forEach(k => it[k] = ''); },
            async save() {
                const serItem = it => ({
                    source_type: it.src==='p'?'product':(it.src==='s'?'self_product':'adhoc'),
                    product_id: it.src==='p'&&it.pid?it.pid:null,
                    self_product_id: it.src==='s'&&it.sid?it.sid:null,
                    item_name: it.src==='a'&&it.name?it.name:null,
                    spec: it.spec||'', unit: it.unit||'',
                    quantity: it.qty||0, unit_price: it.price||0,
                    sub_items: (it.subs||[]).map(serItem),
                });
                const fd = new FormData();
                if (this.activeId && this.activeId !== 'new') fd.append('id', this.activeId);
                fd.append('name', this.form.name);
                fd.append('description', this.form.desc||'');
                fd.append('status', this.form.status);
                fd.append('bom', JSON.stringify(this.modules.map(m => ({
                    name: m.name, items: (m.items||[]).map(serItem),
                }))));
                fd.append('_csrf', this.CSRF);
                try {
                    const r = await fetch('/api/system_save.php', { method: 'POST', body: fd });
                    const d = await r.json();
                    if (d.ok) {
                        if (this.activeId === 'new') { this.activeId = d.id; this.projects.unshift({ id: d.id, name: this.form.name, status: this.form.status }); }
                        else { const p = this.projects.find(x => x.id === this.activeId); if (p) { p.name = this.form.name; p.status = this.form.status; } }
                        alert('保存成功');
                    } else alert(d.message);
                } catch(e) { alert('出错：'+e.message); }
            },
            async doDelete() {
                if (!confirm('删除「'+this.form.name+'」？')) return;
                const fd = new FormData(); fd.append('action','delete'); fd.append('id',this.activeId); fd.append('_csrf',this.CSRF);
                await fetch('/api/system_save.php',{method:'POST',body:fd});
                this.projects = this.projects.filter(x => x.id !== this.activeId);
                this.activeId = null;
            },
            get summary() {
                const map = {};
                const add = (key, n, spec, u, q, p, src) => {
                    if (!map[key]) map[key] = { k: key, n, spec, u, q:0, p, t:0, srcs: new Set };
                    map[key].q = +(map[key].q + q).toFixed(2);
                    map[key].t = +(map[key].t + q * p).toFixed(2);
                    map[key].srcs.add(src);
                };
                this.modules.forEach(m => {
                    (m.items||[]).forEach(it => {
                        const nm = it.src==='a'?it.name:(it.pid?pm[it.pid]?.sku+' '+pm[it.pid]?.name:sm[it.sid]?.name);
                        add(nm||'?', nm||'?', it.spec, it.unit, it.qty||0, it.price||0, m.name);
                        (it.subs||[]).forEach(s => {
                            add(s.src==='a'?s.name:(s.pid?pm[s.pid]?.sku+' '+pm[s.pid]?.name:sm[s.sid]?.name)||'?',
                               '', s.spec, s.unit, s.qty||0, s.price||0, m.name);
                        });
                    });
                });
                return Object.values(map).map(r => ({ ...r, srcs: [...r.srcs].join(', ') }));
            },
            get summaryTotal() { return this.summary.reduce((t, r) => t + r.t, 0); },
            fmt(v) { return (parseFloat(v)||0).toFixed(2); },
        };
    });
});
</script>

<?php require __DIR__ . '/includes/views/footer.php'; ?>
