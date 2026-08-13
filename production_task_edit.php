<?php
/**
 * 生产任务单 详情/编辑（产品经理确认 + 改 BOM）
 */
define('PMS_ENTRY', true);
require_once __DIR__ . '/includes/bootstrap.php';
if (!PMS_INSTALLED) { header('Location: /install.php'); exit; }
requireLogin();
requireCostView();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) { header('Location: /production_tasks.php'); exit; }

$task = ProductionTask::find($id);
if (!$task) {
    flash('error', '任务单不存在');
    header('Location: /production_tasks.php');
    exit;
}
$modules = ProductionTask::fullBom($id);
$project = Project::find((int)$task['project_id']);

$allProducts = Product::allForSelect();
$allSelfProducts = class_exists('SelfProduct') ? SelfProduct::allForSelect() : [];

$prodJson = json_encode(array_map(fn($p) => [
    'id' => $p['id'],
    'sku' => $p['sku'] ?? '',
    'label' => $p['sku'] . ' ' . $p['name'],
    'spec' => $p['spec'] ?? '',
    'price' => (float)($p['cost_price'] ?? 0),
    'unit' => $p['unit'] ?? '',
], $allProducts), JSON_UNESCAPED_UNICODE);
$spJson = json_encode(array_map(fn($sp) => [
    'id' => $sp['id'],
    'sku' => $sp['sku'] ?? '',
    'label' => $sp['name'],
    'price' => (float)($sp['total_cost'] ?? 0),
    'unit' => $sp['unit'] ?? '',
], $allSelfProducts), JSON_UNESCAPED_UNICODE);

$pageTitle = '生产任务单 ' . $task['task_no'];
$activeMenu = 'production_tasks';
require __DIR__ . '/includes/views/header.php';
?>

<div class="max-w-7xl mx-auto" x-data="taskForm(<?= h(json_encode([
    'id' => $id,
    'task' => $task,
    'modules' => $modules,
], JSON_UNESCAPED_UNICODE)) ?>)">

    <div class="flex items-center justify-between mb-4">
        <div>
            <h2 class="text-lg font-medium text-slate-800">生产任务单 <span class="text-blue-600" x-text="form.task_no"></span></h2>
            <p class="text-sm text-slate-500 mt-0.5" x-text="'项目：' + form.project_name"></p>
        </div>
        <a href="/production_tasks.php" class="btn btn-secondary text-sm">
            <i data-lucide="arrow-left" class="w-4 h-4 mr-1"></i>返回列表
        </a>
    </div>

    <form @submit.prevent="save" id="taskForm">
    <?= csrfField() ?>

    <!-- 产品信息 -->
    <div class="card p-6 mb-4">
        <div class="flex items-center justify-between mb-4 pb-2 border-b border-slate-100">
            <h3 class="text-base font-medium text-slate-800">产品信息</h3>
            <span class="inline-block px-2 py-0.5 text-xs rounded"
                  :class="statusBadge.class"
                  x-text="statusBadge.text"></span>
        </div>
        <div class="grid grid-cols-2 gap-4 mb-4">
            <div>
                <label class="form-label">产品名称</label>
                <div class="form-input bg-slate-50 text-slate-600" x-text="form.product_name || '—'"></div>
            </div>
            <div>
                <label class="form-label">型号</label>
                <div class="form-input bg-slate-50 text-slate-600" x-text="form.model_no || '—'"></div>
            </div>
            <div>
                <label class="form-label">规格</label>
                <div class="form-input bg-slate-50 text-slate-600" x-text="form.spec || '—'"></div>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="form-label">数量</label>
                    <div class="form-input bg-slate-50 text-slate-600 tabular-nums" x-text="form.quantity"></div>
                </div>
                <div>
                    <label class="form-label">单位</label>
                    <div class="form-input bg-slate-50 text-slate-600" x-text="form.unit || '—'"></div>
                </div>
            </div>
        </div>
        <div>
            <label class="form-label">需求说明（项目经理提的特殊要求）</label>
            <textarea x-model="form.requirement" class="form-input" rows="2" placeholder="如：这单要用进口控制器、尺寸需定制..."></textarea>
        </div>
    </div>

    <!-- BOM 明细 -->
    <div class="card p-6 mb-4">
        <div class="flex items-center justify-between mb-4 pb-2 border-b border-slate-100">
            <h3 class="text-base font-medium text-slate-800">BOM 物料清单</h3>
            <div class="flex gap-2 items-center">
                <button type="button" class="btn btn-secondary text-sm" :class="{ 'ring-2 ring-blue-300': view==='module' }" @click="view='module'">按模块</button>
                <button type="button" class="btn btn-secondary text-sm" :class="{ 'ring-2 ring-blue-300': view==='summary' }" @click="view='summary'">物料汇总</button>
            </div>
        </div>

        <!-- 模块视图 -->
        <div x-show="view==='module'">
            <template x-for="(mod, mi) in modules" :key="mi">
                <div class="mb-3 border rounded">
                    <div class="flex items-center gap-2 px-3 py-2 bg-slate-50 border-b">
                        <button type="button" class="flex-shrink-0 w-5 h-5 flex items-center justify-center text-slate-500 hover:text-slate-700 hover:bg-slate-200 rounded" @click="mod._open=!mod._open">
                            <span x-text="mod._open===false?'▶':'▼'" class="text-xs leading-none"></span>
                        </button>
                        <input x-model="mod.name" class="font-medium text-sm bg-transparent border-b border-transparent focus:border-blue-500 focus:outline-none" style="min-width:80px;max-width:200px" placeholder="模块名称">
                        <div class="flex items-center gap-6 ml-auto">
                            <span x-show="moduleItemSum(mi)>0" class="text-xs text-slate-500">主材 <span class="font-medium text-slate-700" x-text="'¥'+fmt(moduleItemSum(mi))"></span></span>
                            <span x-show="moduleSubSum(mi)>0" class="text-xs text-slate-500">配件 <span class="font-medium text-slate-700" x-text="'¥'+fmt(moduleSubSum(mi))"></span></span>
                            <span class="font-medium text-sm text-slate-800" x-text="'¥'+fmt(moduleSum(mi))"></span>
                            <div class="flex items-center gap-1.5 border-l border-slate-200 pl-4">
                                <button type="button" class="text-blue-500 text-xs" @click.stop="addItem(mi)">+主材</button>
                                <button type="button" class="text-xs text-slate-400 hover:text-slate-600" @click.stop="moveMod(mi,-1)">↑</button>
                                <button type="button" class="text-xs text-slate-400 hover:text-slate-600" @click.stop="moveMod(mi,1)">↓</button>
                                <button type="button" class="text-xs text-red-400" @click.stop="confirm('确认删除该模块？') && modules.splice(mi,1)">×</button>
                            </div>
                        </div>
                    </div>
                    <div x-show="mod._open!==false">
                        <div x-show="!(mod.items||[]).length" class="text-center text-xs text-slate-400 py-3">还没有主材，点上方 +主材 添加</div>
                        <div class="grid grid-cols-[40px_56px_210px_160px_56px_72px_96px_100px_80px] items-center gap-1 px-3 py-1.5 text-xs text-slate-400 border-b border-slate-100"
                             x-show="(mod.items||[]).length>0">
                            <span>#</span><span>类型</span><span>物料名称</span><span>规格</span><span>单位</span><span>数量</span><span>单价</span><span>小计</span><span></span>
                        </div>
                        <template x-for="(it, ii) in (mod.items||[])" :key="ii">
                            <div class="border-b border-slate-100">
                                <div style="border-left:4px solid #60a5fa;border-bottom:2px solid #cbd5e1" class="grid grid-cols-[40px_56px_210px_160px_56px_72px_96px_100px_80px] items-center gap-1 px-3 py-2 text-sm border-t border-slate-200 font-semibold text-slate-800">
                                    <div class="flex items-center gap-1 min-w-[24px]">
                                        <button type="button" class="text-xs text-slate-400 leading-none w-3" :class="(it.subs||[]).length>0 ? '' : 'invisible'" @click="it._collapsed=!it._collapsed">
                                            <span x-text="it._collapsed?'▶':'▼'"></span>
                                        </button>
                                        <span x-text="ii+1"></span>
                                    </div>
                                    <select x-model="it.src" class="text-xs border rounded py-0.5 w-full bg-white" @change="srcChanged(it)">
                                        <option value="p">外采</option><option value="s">自产</option><option value="a">临时</option>
                                    </select>

                                    <div x-show="it.src==='p'" class="relative">
                                        <template x-if="!it.pid">
                                            <input type="text" @click="it._prodOpen=true" @focus="it._prodOpen=true" @input="it._prodFilter=$el.value" @keydown.escape="it._prodOpen=false"
                                                   @click.away="it._prodOpen=false"
                                                   x-model="it._prodShow" class="text-sm border rounded px-1 w-full bg-white" placeholder="搜索..." autocomplete="off">
                                        </template>
                                        <template x-if="it.pid">
                                            <div class="ss-tag cursor-pointer min-w-[160px]" @click="it._prodOpen=true">
                                                <span x-text="it._prodShow"></span>
                                                <span class="ss-tag-x" @click.stop="clearItem(it,'p')">&times;</span>
                                            </div>
                                        </template>
                                        <div x-show="it._prodOpen && filteredProducts(it._prodFilter||'').length>0" class="ss-dropdown">
                                            <template x-for="p in filteredProducts(it._prodFilter||'')" :key="p.id">
                                                <div @mousedown.prevent="pickProduct(it,p)" :class="{sel:it.pid==p.id}">
                                                    <span x-text="p.label"></span>
                                                    <span class="text-xs text-slate-400 ml-1" x-text="p.spec ? '【'+p.spec+'】' : ''"></span>
                                                </div>
                                            </template>
                                        </div>
                                    </div>

                                    <div x-show="it.src==='s'" class="relative">
                                        <template x-if="!it.sid">
                                            <input type="text" @click="it._spOpen=true" @focus="it._spOpen=true" @input="it._spFilter=$el.value" @keydown.escape="it._spOpen=false"
                                                   @click.away="it._spOpen=false"
                                                   x-model="it._spShow" class="text-sm border rounded px-1 w-full bg-white" placeholder="搜索..." autocomplete="off">
                                        </template>
                                        <template x-if="it.sid">
                                            <div class="ss-tag cursor-pointer min-w-[160px]" @click="it._spOpen=true">
                                                <span x-text="it._spShow"></span>
                                                <span class="ss-tag-x" @click.stop="clearItem(it,'s')">&times;</span>
                                            </div>
                                        </template>
                                        <div x-show="it._spOpen && filteredSp(it._spFilter||'').length>0" class="ss-dropdown">
                                            <template x-for="s in filteredSp(it._spFilter||'')" :key="s.id">
                                                <div @mousedown.prevent="pickSp(it,s)" :class="{sel:it.sid==s.id}" x-text="s.label"></div>
                                            </template>
                                        </div>
                                    </div>

                                    <input x-show="it.src==='a'" x-model="it.name" class="text-sm border rounded px-1 w-full bg-white" placeholder="名称">
                                    <input x-model="it.spec" :readonly="it.src!=='a'" class="text-sm border rounded px-1 w-full bg-white" placeholder="规格">
                                    <input x-model="it.unit" :readonly="it.src!=='a'" class="text-sm border rounded px-1 w-full text-center bg-white" placeholder="单位">
                                    <input x-model="it.qty" class="text-sm border rounded px-1 w-full text-right bg-white" placeholder="数量">
                                    <input x-model="it.price" :readonly="it.src!=='a'" class="text-sm border rounded px-1 w-full text-right bg-white" placeholder="单价">
                                    <span class="text-right text-xs tabular-nums" x-text="'¥'+fmt(it.qty*it.price)"></span>
                                    <div class="flex items-center gap-1.5 justify-end">
                                        <button type="button" class="text-xs text-blue-400 whitespace-nowrap" @click="addSub(it)">+配件</button>
                                        <button type="button" class="text-xs text-red-400" @click="confirm('确认删除该行？') && mod.items.splice(ii,1)">×</button>
                                    </div>
                                </div>
                                <template x-if="(it.subs||[]).length>0">
                                    <div class="bg-white" x-show="!it._collapsed">
                                        <template x-for="(s, si) in (it.subs||[])" :key="si">
                                            <div style="border-bottom:1px solid #cbd5e1" class="grid grid-cols-[40px_56px_210px_160px_56px_72px_96px_100px_80px] items-center gap-1 px-3 py-1 text-xs">
                                                <span class="text-xs" x-text="ii+1+'.'+(si+1)"></span>
                                                <select x-model="s.src" class="text-xs border rounded py-0.5 w-full" @change="srcChanged(s)">
                                                    <option value="p">外采</option><option value="s">自产</option><option value="a">临时</option>
                                                </select>
                                                <div x-show="s.src==='p'" class="relative">
                                                    <template x-if="!s.pid">
                                                        <input type="text" @click="s._prodOpen=true" @focus="s._prodOpen=true" @input="s._prodFilter=$el.value" @keydown.escape="s._prodOpen=false"
                                                               @click.away="s._prodOpen=false"
                                                               x-model="s._prodShow" class="text-xs border rounded px-1 w-full" placeholder="搜索..." autocomplete="off">
                                                    </template>
                                                    <template x-if="s.pid">
                                                        <div class="ss-tag cursor-pointer min-w-[160px]" @click="s._prodOpen=true">
                                                            <span x-text="s._prodShow"></span>
                                                            <span class="ss-tag-x" @click.stop="clearItem(s,'p')">&times;</span>
                                                        </div>
                                                    </template>
                                                    <div x-show="s._prodOpen && filteredProducts(s._prodFilter||'').length>0" class="ss-dropdown">
                                                        <template x-for="p in filteredProducts(s._prodFilter||'')" :key="p.id">
                                                            <div @mousedown.prevent="pickProduct(s,p)" :class="{sel:s.pid==p.id}">
                                                                <span x-text="p.label"></span>
                                                                <span class="text-xs text-slate-400 ml-1" x-text="p.spec ? '【'+p.spec+'】' : ''"></span>
                                                            </div>
                                                        </template>
                                                    </div>
                                                </div>
                                                <div x-show="s.src==='s'" class="relative">
                                                    <template x-if="!s.sid">
                                                        <input type="text" @click="s._spOpen=true" @focus="s._spOpen=true" @input="s._spFilter=$el.value" @keydown.escape="s._spOpen=false"
                                                               @click.away="s._spOpen=false"
                                                               x-model="s._spShow" class="text-xs border rounded px-1 w-full" placeholder="搜索..." autocomplete="off">
                                                    </template>
                                                    <template x-if="s.sid">
                                                        <div class="ss-tag cursor-pointer min-w-[160px]" @click="s._spOpen=true">
                                                            <span x-text="s._spShow"></span>
                                                            <span class="ss-tag-x" @click.stop="clearItem(s,'s')">&times;</span>
                                                        </div>
                                                    </template>
                                                    <div x-show="s._spOpen && filteredSp(s._spFilter||'').length>0" class="ss-dropdown">
                                                        <template x-for="sp in filteredSp(s._spFilter||'')" :key="sp.id">
                                                            <div @mousedown.prevent="pickSp(s,sp)" :class="{sel:s.sid==sp.id}" x-text="sp.label"></div>
                                                        </template>
                                                    </div>
                                                </div>
                                                <input x-show="s.src==='a'" x-model="s.name" class="text-xs border rounded px-1 w-full" placeholder="名称">
                                                <input x-model="s.spec" :readonly="s.src!=='a'" class="text-xs border rounded px-1 w-full" placeholder="规格">
                                                <input x-model="s.unit" :readonly="s.src!=='a'" class="text-xs border rounded px-1 w-full text-center">
                                                <input x-model="s.qty" class="text-xs border rounded px-1 w-full text-right">
                                                <input x-model="s.price" :readonly="s.src!=='a'" class="text-xs border rounded px-1 w-full text-right">
                                                <span class="text-right tabular-nums" x-text="'¥'+fmt(s.qty*s.price)"></span>
                                                <div class="flex justify-end">
                                                    <button type="button" class="text-xs text-red-400" @click="confirm('确认删除该配件？') && it.subs.splice(si,1)">×</button>
                                                </div>
                                            </div>
                                        </template>
                                    </div>
                                </template>
                            </div>
                        </template>
                    </div>
                </div>
            </template>

            <div x-show="modules.length>0" class="text-right font-medium text-sm mt-3 pr-2">
                材料成本合计：
                <span class="text-xs text-slate-500 mr-2" x-show="totalItems>0">主材 <span x-text="'¥'+fmt(totalItems)"></span></span>
                <span class="text-xs text-slate-500 mr-2" x-show="totalSubs>0">配件 <span x-text="'¥'+fmt(totalSubs)"></span></span>
                <span class="text-blue-600 text-lg" x-text="'¥'+fmt(totalAll)"></span>
            </div>
            <button type="button" class="btn btn-secondary text-sm w-full mt-2" @click="addMod">+ 添加模块</button>
        </div>

        <!-- 汇总视图 -->
        <div x-show="view==='summary'" class="overflow-x-auto">
            <table class="data-table text-sm w-full">
                <thead><tr><th>SKU</th><th>物料</th><th>规格</th><th class="text-right">数量</th><th>单位</th><th class="text-right">单价</th><th class="text-right">金额</th><th>来源模块</th></tr></thead>
                <tbody>
                    <template x-for="r in summary" :key="r.key">
                        <tr><td x-text="r.sku" class="text-slate-500"></td><td x-text="r.n"></td><td x-text="r.spec" class="text-slate-500"></td><td class="text-right" x-text="r.q"></td><td x-text="r.u"></td><td class="text-right" x-text="'¥'+fmt(r.p)"></td><td class="text-right font-medium" x-text="'¥'+fmt(r.t)"></td><td class="text-xs text-slate-400" x-text="r.srcs"></td></tr>
                    </template>
                </tbody>
            </table>
            <div class="text-right mt-2 pr-4 font-medium">合计：<span class="text-blue-600" x-text="'¥'+fmt(summaryTotal)"></span></div>
        </div>
    </div>

    <!-- 操作按钮 -->
    <div class="flex items-center justify-between">
        <a href="/production_tasks.php" class="btn btn-secondary">返回</a>
        <div class="flex gap-3">
            <button type="button" class="btn btn-primary" id="btnSaveTask" @click="save">
                <i data-lucide="save" class="w-4 h-4 mr-1.5"></i>保存
            </button>
            <button type="button" class="btn btn-success" x-show="form.status==='pending'" @click="confirmRequirement">
                <i data-lucide="check-circle" class="w-4 h-4 mr-1.5"></i>确认需求
            </button>
            <button type="button" class="btn btn-success" x-show="form.status==='requirement_confirmed'" @click="confirmProduction">
                <i data-lucide="check-circle" class="w-4 h-4 mr-1.5"></i>确认生产
            </button>
            <button type="button" class="btn btn-success" x-show="form.status==='confirmed'" @click="startProduction">
                <i data-lucide="play-circle" class="w-4 h-4 mr-1.5"></i>开始生产
            </button>
            <button type="button" class="btn btn-success" x-show="form.status==='in_production'" @click="finishProduction">
                <i data-lucide="check-check" class="w-4 h-4 mr-1.5"></i>生产完成
            </button>
        </div>
    </div>
    </form>
</div>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('taskForm', (init) => {
        const PL = <?= $prodJson ?> || [];
        const SL = <?= $spJson ?> || [];

        function normModules(raw) {
            if (!raw || !raw.length) return [{ name: '', _open: true, items: [] }];
            return raw.map(m => ({
                name: m.name || '',
                _open: true,
                items: (m.items || []).map(it => {
                    const src = it.self_product_id ? 's' : (it.product_id ? 'p' : (it.item_name ? 'a' : 'p'));
                    return {
                        src, pid: it.product_id || '', sid: it.self_product_id || '',
                        name: it.item_name || '', spec: it.spec || '', unit: it.unit || '',
                        qty: parseFloat(it.quantity) || 0, price: parseFloat(it.unit_price) || 0,
                        _prodOpen: false, _prodFilter: '', _prodShow: it.product_id ? (PL.find(x => x.id == it.product_id)?.label || '') : (it.item_name || ''),
                        _spOpen: false, _spFilter: '', _spShow: it.self_product_id ? (SL.find(x => x.id == it.self_product_id)?.label || '') : '',
                        _collapsed: true,
                        subs: (it.subs || []).map(s => {
                            const ssrc = s.self_product_id ? 's' : (s.product_id ? 'p' : 'a');
                            return {
                                src: ssrc, pid: s.product_id || '', sid: s.self_product_id || '',
                                name: s.item_name || '', spec: s.spec || '', unit: s.unit || '',
                                qty: parseFloat(s.quantity) || 0, price: parseFloat(s.unit_price) || 0,
                                _prodOpen: false, _prodFilter: '', _prodShow: s.product_id ? (PL.find(x => x.id == s.product_id)?.label || '') : (s.item_name || ''),
                                _spOpen: false, _spFilter: '', _spShow: s.self_product_id ? (SL.find(x => x.id == s.self_product_id)?.label || '') : '',
                            };
                        }),
                    };
                }),
            }));
        }

        const comp = {
            id: init.id,
            view: 'module',
            form: {
                task_no: init.task?.task_no || '',
                project_name: init.task?.project_name || '',
                product_name: init.task?.product_name || '',
                model_no: init.task?.model_no || '',
                spec: init.task?.spec || '',
                unit: init.task?.unit || '',
                quantity: init.task?.quantity || 1,
                requirement: init.task?.requirement || '',
                status: init.task?.status || 'pending',
            },
            modules: normModules(init.modules || []),
            submitted: false,

            get statusBadge() {
                const map = {
                    'pending': { text: '待确认', class: 'bg-amber-50 text-amber-700' },
                    'requirement_confirmed': { text: '需求已确认', class: 'bg-blue-50 text-blue-700' },
                    'confirmed': { text: '已确认', class: 'bg-emerald-50 text-emerald-700' },
                    'in_production': { text: '生产中', class: 'bg-indigo-50 text-indigo-700' },
                    'done': { text: '生产完成', class: 'bg-slate-100 text-slate-600' },
                };
                return map[this.form.status] || map.pending;
            },

            addMod() { this.modules.push({ name: '', _open: true, items: [] }); },
            moveMod(i, d) { const t = i + d; if (t < 0 || t >= this.modules.length) return; [this.modules[i], this.modules[t]] = [this.modules[t], this.modules[i]]; },
            addItem(mi) {
                this.modules[mi].items = [...this.modules[mi].items, {
                    src: 'p', pid: '', sid: '', name: '', spec: '', unit: '', qty: 0, price: 0,
                    _prodOpen: false, _prodFilter: '', _prodShow: '', _spOpen: false, _spFilter: '', _spShow: '', _collapsed: true, subs: [],
                }];
                this.modules[mi]._open = true;
            },
            addSub(it) {
                it.subs = [...it.subs, { src: 'p', pid: '', sid: '', name: '', spec: '', unit: '', qty: 0, price: 0, _prodOpen: false, _prodFilter: '', _prodShow: '', _spOpen: false, _spFilter: '', _spShow: '' }];
                it._collapsed = false;
            },
            filteredProducts(q) { q = (q || '').toLowerCase(); return q ? PL.filter(p => p.label.toLowerCase().includes(q)) : PL; },
            filteredSp(q) { q = (q || '').toLowerCase(); return q ? SL.filter(s => s.label.toLowerCase().includes(q)) : SL; },
            pickProduct(it, p) { it.pid = p.id; it._prodShow = p.label; it._prodFilter = ''; it._prodOpen = false; it.price = p.price; it.unit = p.unit; it.spec = p.spec || ''; it.name = ''; },
            pickSp(it, s) { it.sid = s.id; it._spShow = s.label; it._spFilter = ''; it._spOpen = false; it.price = s.price; it.unit = s.unit; it.name = ''; },
            clearItem(it, src) { if (src === 'p') { it.pid = ''; it._prodShow = ''; it.price = 0; it.spec = ''; it.unit = ''; } else { it.sid = ''; it._spShow = ''; it.price = 0; it.unit = ''; } },
            srcChanged(it) { ['pid', 'sid', 'name', 'price', '_prodShow', '_spShow', 'spec', 'unit'].forEach(k => it[k] = ''); },

            moduleSum(i) { const mod = this.modules[i]; let t = 0; (mod.items || []).forEach(it => { t += (it.qty || 0) * (it.price || 0); (it.subs || []).forEach(s => t += (s.qty || 0) * (s.price || 0)); }); return t; },
            moduleItemSum(i) { const mod = this.modules[i]; let t = 0; (mod.items || []).forEach(it => { t += (it.qty || 0) * (it.price || 0); }); return t; },
            moduleSubSum(i) { const mod = this.modules[i]; let t = 0; (mod.items || []).forEach(it => { (it.subs || []).forEach(s => { t += (s.qty || 0) * (s.price || 0); }); }); return t; },
            get totalAll() { let t = 0; this.modules.forEach((m, i) => t += this.moduleSum(i)); return t; },
            get totalItems() { let t = 0; this.modules.forEach((m, i) => t += this.moduleItemSum(i)); return t; },
            get totalSubs() { let t = 0; this.modules.forEach((m, i) => t += this.moduleSubSum(i)); return t; },

            get summary() {
                const m = {};
                // add 用唯一 key 合并，同时记录排序字段 sortKey（自产=0/外采=1/临时=2）和 sku
                const add = (key, n, sku, spec, u, q, p, src, sortKey) => {
                    if (!m[key]) m[key] = { key, n, sku, spec, u, q: 0, p, t: 0, srcs: new Set, sortKey };
                    m[key].q = +(m[key].q + parseFloat(q)).toFixed(2);
                    m[key].t = +(m[key].t + parseFloat(q) * parseFloat(p)).toFixed(2);
                    m[key].srcs.add(src);
                };
                this.modules.forEach(mod => {
                    (mod.items || []).forEach(it => {
                        let key, n, sku, sortKey;
                        if (it.src === 'p' && it.pid) {
                            const pl = PL.find(x => String(x.id) == String(it.pid));
                            sku = pl?.sku || ''; n = pl?.label || ''; key = 'p' + it.pid; sortKey = 1;
                        } else if (it.src === 's' && it.sid) {
                            const sl = SL.find(x => String(x.id) == String(it.sid));
                            sku = sl?.sku || ''; n = sl?.label || ''; key = 's' + it.sid; sortKey = 0;
                        } else {
                            sku = ''; n = it.name || '?'; key = 'a' + (it.name || ''); sortKey = 2;
                        }
                        add(key, n, sku, it.spec, it.unit, it.qty || 0, it.price || 0, mod.name, sortKey);
                        (it.subs || []).forEach(s => {
                            let sk2, n2, sku2, sortKey2;
                            if (s.src === 'p' && s.pid) {
                                const pl = PL.find(x => String(x.id) == String(s.pid));
                                sku2 = pl?.sku || ''; n2 = pl?.label || ''; sk2 = 'p' + s.pid; sortKey2 = 1;
                            } else if (s.src === 's' && s.sid) {
                                const sl = SL.find(x => String(x.id) == String(s.sid));
                                sku2 = sl?.sku || ''; n2 = sl?.label || ''; sk2 = 's' + s.sid; sortKey2 = 0;
                            } else {
                                sku2 = ''; n2 = s.name || '?'; sk2 = 'a' + (s.name || ''); sortKey2 = 2;
                            }
                            add(sk2, n2, sku2, s.spec, s.unit, s.qty || 0, s.price || 0, mod.name, sortKey2);
                        });
                    });
                });
                return Object.values(m)
                    .map(r => ({ ...r, srcs: [...r.srcs].join(', ') }))
                    .sort((a, b) => {
                        if (a.sortKey !== b.sortKey) return a.sortKey - b.sortKey;
                        return (a.sku || '').localeCompare(b.sku || '');
                    });
            },
            get summaryTotal() { return this.summary.reduce((t, r) => t + r.t, 0); },
            fmt(v) { return (parseFloat(v) || 0).toFixed(2); },

            serializeBom() {
                const ser = it => {
                    let source = 'adhoc';
                    if (it.src === 'p' && it.pid) source = 'product';
                    else if (it.src === 's' && it.sid) source = 'self_product';
                    return {
                        source_type: source,
                        product_id: source === 'product' ? parseInt(it.pid) : null,
                        self_product_id: source === 'self_product' ? parseInt(it.sid) : null,
                        item_name: source === 'adhoc' ? ((it.name || '').trim() || null) : null,
                        spec: it.spec || '',
                        unit: it.unit || '',
                        quantity: parseFloat(it.qty) || 0,
                        unit_price: parseFloat(it.price) || 0,
                        sub_items: (it.subs || []).map(ser),
                    };
                };
                return this.modules.map(m => ({ name: m.name, items: (m.items || []).map(ser) }));
            },

            async save() {
                if (this.submitted) return;
                this.submitted = true;
                const fd = new FormData();
                fd.append('id', init.id);
                fd.append('requirement', this.form.requirement);
                fd.append('bom', JSON.stringify(this.serializeBom()));
                try {
                    const resp = await fetch('/api/production_task_save.php', {
                        method: 'POST',
                        headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': document.querySelector('input[name="_csrf"]')?.value || '' },
                        body: fd,
                    });
                    const data = await resp.json();
                    if (data.ok) { alert('保存成功'); location.reload(); }
                    else { alert(data.message || '保存失败'); this.submitted = false; }
                } catch (e) { alert('保存失败：' + e.message); this.submitted = false; }
            },
            async confirmRequirement() {
                if (!confirm('确认需求？确认后任务将交给产品经理调整 BOM。')) return;
                this.submitted = true;
                const fd = new FormData();
                fd.append('id', init.id);
                fd.append('requirement', this.form.requirement);
                fd.append('bom', JSON.stringify(this.serializeBom()));
                fd.append('confirm', 'confirm_requirement');
                try {
                    const resp = await fetch('/api/production_task_save.php', {
                        method: 'POST',
                        headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': document.querySelector('input[name="_csrf"]')?.value || '' },
                        body: fd,
                    });
                    const data = await resp.json();
                    if (data.ok) { alert('需求已确认'); location.reload(); }
                    else { alert(data.message || '确认失败'); this.submitted = false; }
                } catch (e) { alert('确认失败：' + e.message); this.submitted = false; }
            },
            async confirmProduction() {
                if (!confirm('确认生产？确认后车间将按此 BOM 生产。')) return;
                this.submitted = true;
                const fd = new FormData();
                fd.append('id', init.id);
                fd.append('requirement', this.form.requirement);
                fd.append('bom', JSON.stringify(this.serializeBom()));
                fd.append('confirm', 'confirm_production');
                try {
                    const resp = await fetch('/api/production_task_save.php', {
                        method: 'POST',
                        headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': document.querySelector('input[name="_csrf"]')?.value || '' },
                        body: fd,
                    });
                    const data = await resp.json();
                    if (data.ok) { alert('已确认生产'); location.reload(); }
                    else { alert(data.message || '确认失败'); this.submitted = false; }
                } catch (e) { alert('确认失败：' + e.message); this.submitted = false; }
            },
            async startProduction() {
                if (!confirm('开始生产？')) return;
                await this.doConfirm('start_production', '已开始生产');
            },
            async finishProduction() {
                if (!confirm('确认生产完成？')) return;
                await this.doConfirm('finish_production', '生产完成');
            },
            async doConfirm(action, okMsg) {
                this.submitted = true;
                const fd = new FormData();
                fd.append('id', init.id);
                fd.append('requirement', this.form.requirement);
                fd.append('bom', JSON.stringify(this.serializeBom()));
                fd.append('confirm', action);
                try {
                    const resp = await fetch('/api/production_task_save.php', {
                        method: 'POST',
                        headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': document.querySelector('input[name="_csrf"]')?.value || '' },
                        body: fd,
                    });
                    const data = await resp.json();
                    if (data.ok) { alert(okMsg); location.reload(); }
                    else { alert(data.message || '操作失败'); this.submitted = false; }
                } catch (e) { alert('操作失败：' + e.message); this.submitted = false; }
            },
        };
        return comp;
    });
});
</script>

<?php require __DIR__ . '/includes/views/footer.php'; ?>
