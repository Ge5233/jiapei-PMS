<?php
/**
 * 项目 新增/编辑（多清单 + 模块层级）
 */
define('PMS_ENTRY', true);
require_once __DIR__ . '/includes/bootstrap.php';
if (!PMS_INSTALLED) { header('Location: /install.php'); exit; }
requireLogin();
requireCostView();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$isEdit = $id > 0;
$project = null;
$lists = [];

if ($isEdit) {
    $project = Project::find($id);
    if (!$project) {
        flash('error', '项目不存在');
        header('Location: /projects.php');
        exit;
    }
    $lists = Project::lists($id);
}

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

$pageTitle = $isEdit ? '编辑项目' : '新建项目';
$activeMenu = 'projects';
require __DIR__ . '/includes/views/header.php';
?>

<div class="max-w-7xl mx-auto" x-data="projectForm(<?= h(json_encode([
    'id' => $id,
    'isEdit' => $isEdit,
    'project' => $project,
    'lists' => $lists,
], JSON_UNESCAPED_UNICODE)) ?>)">

    <div class="flex items-center justify-between mb-4">
        <h2 class="text-lg font-medium text-slate-800"><?= $isEdit ? '编辑项目' : '新建项目' ?></h2>
        <a href="/projects.php" class="btn btn-secondary text-sm">
            <i data-lucide="arrow-left" class="w-4 h-4 mr-1"></i>返回列表
        </a>
    </div>

    <form @submit.prevent="save" id="projectForm">
    <?= csrfField() ?>

    <!-- 基本信息 -->
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
        <div>
            <label class="form-label">备注</label>
            <textarea x-model="form.remark" class="form-input" rows="2" maxlength="2000" placeholder="项目整体说明"></textarea>
        </div>
    </div>

    <!-- 清单 Tab -->
    <div class="card p-6 mb-4">
        <div class="flex items-center justify-between mb-4 pb-2 border-b border-slate-100">
            <div class="flex items-center gap-1 flex-wrap">
                <template x-for="(list, li) in lists" :key="li">
                    <div class="flex items-center">
                        <button type="button" class="px-4 py-1.5 text-sm rounded-t-lg border-b-2 transition-colors whitespace-nowrap"
                                :class="activeList===li ? 'text-blue-600 border-blue-600 font-medium' : 'text-slate-500 border-transparent hover:text-slate-700'"
                                @click="activeList=li" x-text="list.name || ('清单'+(li+1))"></button>
                        <button type="button" class="text-xs text-slate-400 hover:text-red-500 ml-1" @click="removeList(li)" title="删除清单">&times;</button>
                    </div>
                </template>
                <button type="button" class="text-xs text-blue-500 hover:text-blue-700 ml-2" @click="addList">+ 加清单</button>
            </div>
            <div class="flex gap-2 items-center">
                <button type="button" class="btn btn-secondary text-sm" x-show="isEdit" @click="generateTasks">
                    <i data-lucide="clipboard-check" class="w-4 h-4 mr-1"></i>生成生产任务单
                </button>
            </div>
        </div>

        <!-- 当前清单名称 -->
        <div class="mb-3" x-show="lists.length > 0">
            <input type="text" x-model="lists[activeList].name" class="form-input text-sm font-medium" placeholder="清单名称（如：灌溉系统）" maxlength="200">
        </div>

        <!-- 空状态 -->
        <div x-show="lists.length === 0" class="text-center py-8 text-slate-400 text-sm">
            <i data-lucide="clipboard-list" class="w-8 h-8 mx-auto mb-2 text-slate-300"></i>
            还没清单，点上方「+ 加清单」开始。一个项目可有多张清单（对应报价单分区）。
        </div>

        <!-- 清单内容（模块 → 主材 → 配件） -->
        <div x-show="lists.length > 0">
            <template x-for="(mod, mi) in (lists[activeList].modules || [])" :key="mi">
                <div class="mb-3 border rounded">
                    <div class="flex items-center gap-2 px-3 py-2 bg-slate-50 border-b">
                        <button type="button" class="flex-shrink-0 w-5 h-5 flex items-center justify-center text-slate-500 hover:text-slate-700 hover:bg-slate-200 rounded" @click="mod._open=!mod._open">
                            <span x-text="mod._open===false?'▶':'▼'" class="text-xs leading-none"></span>
                        </button>
                        <input x-model="mod.name" class="font-medium text-sm bg-transparent border-b border-transparent focus:border-blue-500 focus:outline-none" style="min-width:80px;max-width:200px" placeholder="模块名称">
                        <div class="flex items-center gap-6 ml-auto">
                            <span x-show="moduleItemSum(activeList,mi)>0" class="text-xs text-slate-500">主材 <span class="font-medium text-slate-700" x-text="'¥'+fmt(moduleItemSum(activeList,mi))"></span></span>
                            <span x-show="moduleSubSum(activeList,mi)>0" class="text-xs text-slate-500">配件 <span class="font-medium text-slate-700" x-text="'¥'+fmt(moduleSubSum(activeList,mi))"></span></span>
                            <span class="font-medium text-sm text-slate-800" x-text="'¥'+fmt(moduleSum(activeList,mi))"></span>
                            <div class="flex items-center gap-1.5 border-l border-slate-200 pl-4">
                                <button type="button" class="text-blue-500 text-xs" @click.stop="addItem(activeList,mi)">+主材</button>
                                <button type="button" class="text-xs text-slate-400 hover:text-slate-600" @click.stop="moveMod(activeList,mi,-1)">↑</button>
                                <button type="button" class="text-xs text-slate-400 hover:text-slate-600" @click.stop="moveMod(activeList,mi,1)">↓</button>
                                <button type="button" class="text-xs text-red-400" @click.stop="confirm('确认删除该模块？') && lists[activeList].modules.splice(mi,1)">×</button>
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

            <button type="button" class="btn btn-secondary text-sm w-full mt-2" @click="addMod(activeList)">+ 添加模块</button>

            <!-- 当前清单合计（三档） -->
            <div x-show="(lists[activeList].modules||[]).length>0" class="flex justify-end items-center gap-6 mt-3 pt-3 pr-2 border-t border-slate-200">
                <span class="text-xs text-slate-500">自产小计 <span class="font-medium text-slate-700" x-text="'¥'+fmt(listSelfTotal(activeList))"></span></span>
                <span class="text-xs text-slate-500">外采小计 <span class="font-medium text-slate-700" x-text="'¥'+fmt(listPurchaseTotal(activeList))"></span></span>
                <span class="font-medium text-sm text-slate-800">合计 <span class="text-blue-600 text-lg" x-text="'¥'+fmt(listTotal(activeList))"></span></span>
            </div>
        </div>
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
        }

        function normModule(m) {
            return {
                name: m.name || '',
                _open: true,
                items: (m.items || []).map(normItem),
            };
        }

        function normList(l) {
            return {
                name: l.name || '',
                modules: (l.modules || []).map(normModule),
            };
        }

        const comp = {
            isEdit: init.isEdit,
            initId: init.id,
            activeList: 0,
            form: {
                name: init.project?.name || '',
                customer_name: init.project?.customer_name || '',
                remark: init.project?.remark || '',
            },
            lists: (init.lists || []).map(normList),
            submitted: false,

            addList() {
                this.lists.push({ name: '', modules: [{ name: '', _open: true, items: [] }] });
                this.activeList = this.lists.length - 1;
            },
            removeList(li) {
                if (!confirm('确认删除该清单？')) return;
                this.lists.splice(li, 1);
                if (this.lists.length === 0) this.activeList = 0;
                else if (this.activeList >= this.lists.length) this.activeList = this.lists.length - 1;
            },
            addMod(li) {
                this.lists[li].modules = [...this.lists[li].modules, { name: '', _open: true, items: [] }];
            },
            moveMod(li, mi, d) {
                const mods = this.lists[li].modules;
                const t = mi + d;
                if (t < 0 || t >= mods.length) return;
                [mods[mi], mods[t]] = [mods[t], mods[mi]];
            },
            addItem(li, mi) {
                const mod = this.lists[li].modules[mi];
                mod.items = [...mod.items, {
                    src: 'p', pid: '', sid: '', name: '', spec: '', unit: '', qty: 0, price: 0,
                    _prodOpen: false, _prodFilter: '', _prodShow: '', _spOpen: false, _spFilter: '', _spShow: '', _collapsed: true, subs: [],
                }];
                mod._open = true;
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

            moduleSum(li, mi) { const mod = this.lists[li].modules[mi]; let t = 0; (mod.items || []).forEach(it => { t += (it.qty || 0) * (it.price || 0); (it.subs || []).forEach(s => t += (s.qty || 0) * (s.price || 0)); }); return t; },
            moduleItemSum(li, mi) { const mod = this.lists[li].modules[mi]; let t = 0; (mod.items || []).forEach(it => { t += (it.qty || 0) * (it.price || 0); }); return t; },
            moduleSubSum(li, mi) { const mod = this.lists[li].modules[mi]; let t = 0; (mod.items || []).forEach(it => { (it.subs || []).forEach(s => { t += (s.qty || 0) * (s.price || 0); }); }); return t; },
            listTotal(li) { const list = this.lists[li]; let t = 0; (list.modules || []).forEach((mod, mi) => t += this.moduleSum(li, mi)); return t; },
            listSelfTotal(li) {
                const list = this.lists[li]; let t = 0;
                (list.modules || []).forEach(mod => {
                    (mod.items || []).forEach(it => {
                        if (it.src === 's') t += (it.qty || 0) * (it.price || 0);
                        (it.subs || []).forEach(s => { if (s.src === 's') t += (s.qty || 0) * (s.price || 0); });
                    });
                });
                return t;
            },
            listPurchaseTotal(li) {
                const list = this.lists[li]; let t = 0;
                (list.modules || []).forEach(mod => {
                    (mod.items || []).forEach(it => {
                        if (it.src !== 's') t += (it.qty || 0) * (it.price || 0);
                        (it.subs || []).forEach(s => { if (s.src !== 's') t += (s.qty || 0) * (s.price || 0); });
                    });
                });
                return t;
            },
            fmt(v) { return (parseFloat(v) || 0).toFixed(2); },

            serializeLists() {
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
                return this.lists.map(l => ({
                    name: l.name,
                    modules: (l.modules || []).map(m => ({
                        name: m.name,
                        items: (m.items || []).map(ser),
                    })),
                }));
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
                fd.append('lists', JSON.stringify(this.serializeLists()));

                try {
                    const resp = await fetch('/api/project_save.php', {
                        method: 'POST',
                        headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': document.querySelector('input[name="_csrf"]')?.value || '' },
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
                if (!confirm('将遍历所有清单，为每个自产产品生成生产任务单（待确认状态），确认？')) return;

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
