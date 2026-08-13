<?php
/**
 * 自产产品 新增/编辑
 */
define('PMS_ENTRY', true);
require_once __DIR__ . '/includes/bootstrap.php';
if (!PMS_INSTALLED) { header('Location: /install.php'); exit; }
requireLogin();
// 员工可浏览但不编辑

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$isEdit = $id > 0;
$selfProduct = null;
$bomItems = [];

if ($isEdit) {
    $selfProduct = SelfProduct::find($id);
    if (!$selfProduct) {
        flash('error', '产品不存在');
        header('Location: /self_products.php');
        exit;
    }
    $bomItems = SelfProduct::getBom($id);
}

// 外采产品列表（给BOM下拉）
$allProducts = Product::allForSelect();
// 自产产品列表（BOM自产类型）
$allSelfProducts = class_exists('SelfProduct') ? SelfProduct::allForSelect() : [];

// JSON for JS（搜索下拉用）
$prodJson = json_encode(array_map(fn($p) => [
    'id' => $p['id'],
    'label' => $p['sku'] . ' ' . $p['name'],
    'spec' => $p['spec'] ?? '',
    'price' => (float)($p['cost_price'] ?? 0),
    'unit' => $p['unit'] ?? '',
], $allProducts), JSON_UNESCAPED_UNICODE);
$spJson = json_encode(array_map(fn($sp) => [
    'id' => $sp['id'],
    'label' => $sp['name'],
    'price' => (float)($sp['total_cost'] ?? 0),
    'unit' => $sp['unit'] ?? '',
], $allSelfProducts), JSON_UNESCAPED_UNICODE);

$pageTitle = $isEdit ? '编辑自产产品' : '新增自产产品';
$activeMenu = 'self_products';
require __DIR__ . '/includes/views/header.php';
?>

<div class="max-w-7xl mx-auto" x-data="selfProductForm(<?= h(json_encode([
    'id' => $id,
    'isEdit' => $isEdit,
    'selfProduct' => $selfProduct,
    'bomItems' => $bomItems,
    'allProducts' => $allProducts,
    'allSelfProducts' => $allSelfProducts,
], JSON_UNESCAPED_UNICODE)) ?>)">

    <div class="flex items-center justify-between mb-4">
        <div>
            <h2 class="text-lg font-medium text-slate-800"><?= $isEdit ? '编辑自产产品' : '新增自产产品' ?></h2>
        </div>
        <a href="/self_products.php" class="btn btn-secondary text-sm">
            <i data-lucide="arrow-left" class="w-4 h-4 mr-1"></i>返回列表
        </a>
    </div>

    <?php if (canViewCost()): ?>
    <!-- Tab 标签 -->
    <div class="flex gap-0 mb-4 border-b-2 border-slate-200">
        <button type="button" class="px-6 py-2.5 text-sm font-medium rounded-t-lg transition-colors"
                :class="tab==='info' ? 'bg-white text-blue-600 border-2 border-b-white border-slate-200 -mb-0.5' : 'text-slate-500 hover:text-slate-700'"
                @click="tab='info'">基本信息</button>
        <button type="button" class="px-6 py-2.5 text-sm font-medium rounded-t-lg transition-colors"
                :class="tab==='bom' ? 'bg-white text-blue-600 border-2 border-b-white border-slate-200 -mb-0.5' : 'text-slate-500 hover:text-slate-700'"
                @click="tab='bom'">BOM 物料清单</button>
    </div>
    <?php endif; ?>

    <form @submit.prevent="save" id="selfProductForm">
    <?= csrfField() ?>

    <!-- 基本信息 -->
    <div x-show="tab==='info'" class="card p-6 mb-4">
        <h3 class="text-base font-medium text-slate-800 mb-4 pb-2 border-b border-slate-100">基本信息</h3>

        <!-- 产品主图 -->
        <div class="mb-4">
            <label class="form-label">产品主图</label>
            <div class="flex items-start gap-4">
                <div class="relative group">
                    <template x-if="imagePreview">
                        <img :src="imagePreview" class="w-32 h-32 object-cover rounded-lg border border-slate-200">
                    </template>
                    <template x-if="!imagePreview">
                        <div class="w-32 h-32 rounded-lg border-2 border-dashed border-slate-300 bg-slate-50 flex items-center justify-center cursor-pointer hover:border-blue-400 hover:bg-blue-50 transition-colors"
                             @click="$refs.imageInput.click()">
                            <div class="text-center">
                                <i data-lucide="image-plus" class="w-6 h-6 mx-auto text-slate-400"></i>
                                <span class="text-xs text-slate-400 mt-1 block">点击上传</span>
                            </div>
                        </div>
                    </template>
                    <template x-if="imagePreview">
                        <button type="button"
                                class="absolute -top-2 -right-2 w-6 h-6 bg-red-500 text-white rounded-full flex items-center justify-center text-xs opacity-0 group-hover:opacity-100 transition-opacity"
                                @click="removeImage">×</button>
                    </template>
                </div>
                <div class="text-xs text-slate-400 mt-2">
                    支持 JPG / PNG<br>建议 400×400，≤ 2MB
                </div>
            </div>
            <input type="file" x-ref="imageInput" accept="image/jpeg,image/png,image/gif,image/webp" class="hidden" @change="handleImageUpload">
        </div>

        <!-- SKU / 名称 / 型号 -->
        <div class="grid grid-cols-3 gap-4 mb-4">
            <div>
                <label class="form-label">SKU</label>
                <input type="text" x-model="form.sku" class="form-input bg-slate-50 text-slate-600" readonly placeholder="自动生成">
            </div>
            <div>
                <label class="form-label">产品名称 <span class="text-red-500">*</span></label>
                <input type="text" x-model="form.name" class="form-input" required maxlength="200" placeholder="例：智能配肥机 V2">
            </div>
            <div>
                <label class="form-label">型号</label>
                <input type="text" x-model="form.model_no" class="form-input" maxlength="50" placeholder="例：PFJ-V2-2024">
            </div>
        </div>

        <!-- 规格 / 单位 -->
        <div class="grid grid-cols-2 gap-4 mb-4">
            <div>
                <label class="form-label">规格</label>
                <input type="text" x-model="form.spec" class="form-input" maxlength="200" placeholder="例：1200×800×600mm">
            </div>
            <div>
                <label class="form-label">单位</label>
                <input type="text" x-model="form.unit" class="form-input" maxlength="20" placeholder="例：套">
            </div>
        </div>

        <!-- 状态 / 描述 -->
        <div class="grid grid-cols-2 gap-4 mb-4">
            <div>
                <label class="form-label">状态</label>
                <select x-model="form.status" class="form-select">
                    <option value="1">在生产</option>
                    <option value="0">已停产</option>
                </select>
            </div>
            <div></div>
        </div>

        <div class="mb-0">
            <label class="form-label">产品描述</label>
            <textarea x-model="form.description" class="form-input" rows="3" maxlength="2000"
                      placeholder="产品功能、用途等描述"></textarea>
        </div>
    </div>

    <?php if (canViewCost()): ?>
    <!-- 成本与定价 -->
    <div x-show="tab==='info'" class="card p-6 mb-4">
        <h3 class="text-base font-medium text-slate-800 mb-4 pb-2 border-b border-slate-100">成本与定价</h3>

        <div class="grid grid-cols-3 gap-4 mb-4">
            <div>
                <label class="form-label">材料成本</label>
                <div class="relative">
                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400">¥</span>
                    <input type="text" class="form-input pl-7 bg-slate-50 text-slate-600" readonly
                           :value="formatMoney(calcMaterialCost)" tabindex="-1">
                </div>
                <p class="text-xs text-slate-400 mt-1">根据 BOM 自动计算</p>
            </div>
            <div>
                <label class="form-label">人工成本</label>
                <div class="relative">
                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400">¥</span>
                    <input type="number" x-model="form.labor_cost" step="0.01" min="0" class="form-input pl-7"
                           @input="calcTotal">
                </div>
            </div>
                <div>
                    <label class="form-label">制造费用</label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400">¥</span>
                        <input type="number" x-model="form.overhead_cost" step="0.01" min="0" class="form-input pl-7"
                               @input="calcTotal">
                    </div>
                </div>
                <div>
                    <label class="form-label">其它费用</label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400">¥</span>
                        <input type="number" x-model="form.other_cost" step="0.01" min="0" class="form-input pl-7"
                               @input="calcTotal">
                    </div>
                </div>
        </div>

        <div class="border-t border-slate-100 pt-4 mb-4">
            <div class="grid grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="form-label font-medium">总成本</label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400">¥</span>
                        <input type="text" class="form-input pl-7 bg-slate-50 text-slate-800 font-medium" readonly
                               :value="formatMoney(totalCost)" tabindex="-1">
                    </div>
                </div>
                <div>
                    <label class="form-label">费用说明</label>
                    <input type="text" x-model="form.cost_remark" class="form-input" maxlength="200" placeholder="含包装、运输、管理费等">
                </div>
            </div>
        </div>

        <div class="border-t border-slate-100 pt-4 mb-4">
            <div class="grid grid-cols-3 gap-4 mb-4">
                <div>
                    <label class="form-label">指导毛利率</label>
                    <div class="relative">
                        <input type="number" x-model="form.guide_margin_rate" step="0.01" min="0" max="99" class="form-input tabular-nums pr-8" @input="calcTotal">
                        <span class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm">%</span>
                    </div>
                </div>
                <div>
                    <label class="form-label">指导售价</label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm">¥</span>
                        <input type="text" class="form-input pl-7 bg-slate-50 font-medium tabular-nums" readonly
                               :value="formatMoney(totalCost / (1 - form.guide_margin_rate / 100))" tabindex="-1">
                    </div>
                    <p class="text-xs text-slate-400 mt-1">= 总成本 / (1 - 毛利率)</p>
                </div>
                <div></div>
            </div>

            <div class="grid grid-cols-3 gap-4">
                <div>
                    <label class="form-label">最低毛利率</label>
                    <div class="relative">
                        <input type="number" x-model="form.min_margin_rate" step="0.01" min="0" max="99" class="form-input tabular-nums pr-8" @input="calcTotal">
                        <span class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm">%</span>
                    </div>
                </div>
                <div>
                    <label class="form-label">最低售价</label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm">¥</span>
                        <input type="text" class="form-input pl-7 bg-slate-50 tabular-nums" readonly
                               :value="formatMoney(totalCost / (1 - form.min_margin_rate / 100))" tabindex="-1">
                    </div>
                    <p class="text-xs text-slate-400 mt-1">= 总成本 / (1 - 最低毛利率)</p>
                </div>
                <div>
                    <label class="form-label">最高折扣</label>
                    <div class="relative">
                        <input type="text" class="form-input pr-9 bg-slate-50 tabular-nums" readonly
                               :value="form.guide_margin_rate < 100 && form.min_margin_rate < 100 ? ((1 - form.guide_margin_rate / 100) / (1 - form.min_margin_rate / 100) * 100).toFixed(0) + '%' : '-'" tabindex="-1">
                        <span class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm">折</span>
                    </div>
                    <p class="text-xs text-slate-400 mt-1">= 最低售价 ÷ 指导售价</p>
                </div>
            </div>
        </div>

        <div class="bg-slate-50 rounded-lg p-4 flex items-center gap-4">
            <div class="text-sm text-slate-500">毛利率</div>
            <div class="text-2xl font-bold" :class="marginColor" x-text="marginPercent + '%'"></div>
            <div class="text-sm" :class="marginColor" x-text="marginLabel"></div>
            <div class="text-xs text-slate-400 ml-auto">（售价 - 总成本）/ 售价 × 100%</div>
        </div>
    </div>
    <?php endif; ?>

    <?php if (canViewCost()): ?>
    <!-- 备注（基本信息Tab内） -->
    <div x-show="tab==='info'" class="card p-6 mb-4">
        <h3 class="text-base font-medium text-slate-800 mb-4 pb-2 border-b border-slate-100">备注</h3>
        <textarea x-model="form.remark" class="form-input" rows="2" maxlength="2000"
                  placeholder="内部备注（不对客户展示）"></textarea>
    </div>

    <!-- BOM 物料清单 Tab — v4.6 模块层级 -->
    <div x-show="tab==='bom'" class="card p-6 mb-4" style="min-height:420px">
        <div class="flex items-center justify-between mb-4 pb-2 border-b border-slate-100">
            <h3 class="text-base font-medium text-slate-800">BOM 物料清单</h3>
            <div class="flex gap-2 items-center">
                <button type="button" class="btn btn-secondary text-sm" :class="{ 'ring-2 ring-blue-300': bomView==='module' }" @click="bomView='module'">按模块</button>
                <button type="button" class="btn btn-secondary text-sm" :class="{ 'ring-2 ring-blue-300': bomView==='summary' }" @click="bomView='summary'">物料汇总</button>
                <?php if ($isEdit): ?>
                <a :href="'/export_bom.php?self_product_id='+initId+'&type=module'" class="btn btn-secondary text-sm" style="text-decoration:none">导出-模块</a>
                <a :href="'/export_bom.php?self_product_id='+initId+'&type=summary'" class="btn btn-secondary text-sm" style="text-decoration:none">导出-汇总</a>
                <?php endif; ?>
            </div>
        </div>

        <!-- 模块视图 -->
        <div x-show="bomView==='module'">
            <template x-for="(mod, mi) in modules" :key="mi">
                <div class="mb-3 border rounded">
                    <div class="flex items-center gap-2 px-3 py-2 bg-slate-50 border-b">
                        <button type="button" class="flex-shrink-0 w-5 h-5 flex items-center justify-center text-slate-500 hover:text-slate-700 hover:bg-slate-200 rounded" @click="mod._open=!mod._open" :title="mod._open===false?'展开':'折叠'">
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
                                <button type="button" class="text-xs text-red-400" @click.stop="confirm('确认删除该模块及所有物料？') && modules.splice(mi,1)">×</button>
                            </div>
                        </div>
                    </div>
                    <div x-show="mod._open!==false">
                        <div x-show="!(mod.items||[]).length" class="text-center text-xs text-slate-400 py-8">还没有主材，点上方 +主材 添加</div>
                        <table class="w-full border-collapse table-fixed bom-table" x-show="(mod.items||[]).length>0">
                            <colgroup>
                                <col style="width:7%">
                                <col style="width:7%">
                                <col style="width:28%">
                                <col style="width:18%">
                                <col style="width:5%">
                                <col style="width:9%">
                                <col style="width:9%">
                                <col style="width:9%">
                                <col style="width:8%">
                            </colgroup>
                            <thead>
                                <tr class="text-xs text-slate-400 border-b border-slate-100">
                                    <th class="py-1.5 pr-1 font-medium text-left">#</th>
                                    <th class="py-1.5 pr-1 font-medium text-left">类型</th>
                                    <th class="py-1.5 pr-1 font-medium text-left">物料名称</th>
                                    <th class="py-1.5 pr-1 font-medium text-left">规格</th>
                                    <th class="py-1.5 pr-1 font-medium text-left">单位</th>
                                    <th class="py-1.5 pr-1 font-medium text-right">数量</th>
                                    <th class="py-1.5 pr-1 font-medium text-right">单价</th>
                                    <th class="py-1.5 pr-1 font-medium text-right">小计</th>
                                    <th class="py-1.5 font-medium text-right">操作</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="(it, ii) in (mod.items||[])" :key="ii">
                                    <!-- 主材行 -->
                                    <tr class="border-b-2 border-slate-200 align-top text-sm font-semibold text-slate-800 bg-white">
                                        <td class="py-2 pr-1 border-l-4 border-blue-400" style="border-left-color:#60a5fa">
                                            <div class="flex items-center gap-1">
                                                <button type="button" class="px-1 py-0.5 text-xs text-slate-400 rounded hover:bg-slate-100" :class="(it.subs||[]).length>0 ? '' : 'invisible'" @click="it._collapsed=!it._collapsed">
                                                    <span x-text="it._collapsed?'▶':'▼'"></span>
                                                </button>
                                                <span x-text="ii+1"></span>
                                            </div>
                                        </td>
                                        <td class="py-2 pr-1">
                                            <select x-model="it.src" class="text-xs border rounded py-0.5 w-full bg-white" @change="srcChanged(it)">
                                                <option value="p">外采</option><option value="s">自产</option><option value="a">临时</option>
                                            </select>
                                        </td>
                                        <td class="py-2 pr-1">
                                            <!-- 外采搜索 -->
                                            <div x-show="it.src==='p'" class="relative">
                                                <template x-if="!it.pid">
                                                    <input type="text" @click="it._prodOpen=true" @focus="it._prodOpen=true" @input="it._prodFilter=$el.value" @keydown.escape="it._prodOpen=false"
                                                           @click.away="it._prodOpen=false"
                                                           x-model="it._prodShow"
                                                           class="text-sm border rounded px-1 w-full bg-white" placeholder="输入关键词搜索..." autocomplete="off">
                                                </template>
                                                <template x-if="it.pid">
                                                    <div class="ss-tag cursor-pointer min-w-0 truncate" @click="it._prodOpen=true">
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
                                            <!-- 自产搜索 -->
                                            <div x-show="it.src==='s'" class="relative">
                                                <template x-if="!it.sid">
                                                    <input type="text" @click="it._spOpen=true" @focus="it._spOpen=true" @input="it._spFilter=$el.value" @keydown.escape="it._spOpen=false"
                                                           @click.away="it._spOpen=false"
                                                           x-model="it._spShow"
                                                           class="text-sm border rounded px-1 w-full bg-white" placeholder="输入关键词搜索..." autocomplete="off">
                                                </template>
                                                <template x-if="it.sid">
                                                    <div class="ss-tag cursor-pointer min-w-0 truncate" @click="it._spOpen=true">
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
                                            <!-- 临时 -->
                                            <input x-show="it.src==='a'" x-model="it.name" class="text-sm border rounded px-1 w-full bg-white" placeholder="名称">
                                        </td>
                                        <td class="py-2 pr-1">
                                            <input x-model="it.spec" :readonly="it.src!=='a'" class="text-sm border rounded px-1 w-full bg-white" placeholder="规格">
                                        </td>
                                        <td class="py-2 pr-1">
                                            <input x-model="it.unit" :readonly="it.src!=='a'" class="text-sm border rounded px-1 w-full text-center bg-white" placeholder="单位">
                                        </td>
                                        <td class="py-2 pr-1">
                                            <input x-model="it.qty" class="text-sm border rounded px-1 w-full text-right bg-white" placeholder="数量">
                                        </td>
                                        <td class="py-2 pr-1">
                                            <input x-model="it.price" :readonly="it.src!=='a'" class="text-sm border rounded px-1 w-full text-right bg-white" placeholder="单价">
                                        </td>
                                        <td class="py-2 pr-1 text-right">
                                            <span class="text-xs tabular-nums" x-text="'¥'+fmt(it.qty*it.price)"></span>
                                        </td>
                                        <td class="py-2 text-right">
                                            <div class="flex items-center gap-1.5 justify-end">
                                                <button type="button" class="text-xs text-blue-400 whitespace-nowrap" @click="addSub(it)">+配件</button>
                                                <button type="button" class="text-xs text-red-400" @click="confirm('确认删除该行？') && mod.items.splice(ii,1)">×</button>
                                            </div>
                                        </td>
                                    </tr>
                                    <!-- 配件行 -->
                                    <template x-for="(s, si) in (it.subs||[])" :key="si">
                                        <tr x-show="!it._collapsed" class="border-b border-slate-100 align-top text-xs">
                                                <td class="py-1 pr-1 text-slate-400" x-text="ii+1+'.'+(si+1)"></td>
                                                <td class="py-1 pr-1">
                                                    <select x-model="s.src" class="text-xs border rounded py-0.5 w-full" @change="srcChanged(s)">
                                                        <option value="p">外采</option><option value="s">自产</option><option value="a">临时</option>
                                                    </select>
                                                </td>
                                                <td class="py-1 pr-1">
                                                    <!-- 外采 -->
                                                    <div x-show="s.src==='p'" class="relative">
                                                        <template x-if="!s.pid">
                                                            <input type="text" @click="s._prodOpen=true" @focus="s._prodOpen=true" @input="s._prodFilter=$el.value" @keydown.escape="s._prodOpen=false"
                                                                   @click.away="s._prodOpen=false"
                                                                   x-model="s._prodShow"
                                                                   class="text-xs border rounded px-1 w-full" placeholder="搜索..." autocomplete="off">
                                                        </template>
                                                        <template x-if="s.pid">
                                                            <div class="ss-tag cursor-pointer min-w-0 truncate" @click="s._prodOpen=true">
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
                                                    <!-- 自产 -->
                                                    <div x-show="s.src==='s'" class="relative">
                                                        <template x-if="!s.sid">
                                                            <input type="text" @click="s._spOpen=true" @focus="s._spOpen=true" @input="s._spFilter=$el.value" @keydown.escape="s._spOpen=false"
                                                                   @click.away="s._spOpen=false"
                                                                   x-model="s._spShow"
                                                                   class="text-xs border rounded px-1 w-full" placeholder="搜索..." autocomplete="off">
                                                        </template>
                                                        <template x-if="s.sid">
                                                            <div class="ss-tag cursor-pointer min-w-0 truncate" @click="s._spOpen=true">
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
                                                    <!-- 临时 -->
                                                    <input x-show="s.src==='a'" x-model="s.name" class="text-xs border rounded px-1 w-full" placeholder="名称">
                                                </td>
                                                <td class="py-1 pr-1">
                                                    <input x-model="s.spec" :readonly="s.src!=='a'" class="text-xs border rounded px-1 w-full" placeholder="规格">
                                                </td>
                                                <td class="py-1 pr-1">
                                                    <input x-model="s.unit" :readonly="s.src!=='a'" class="text-xs border rounded px-1 w-full text-center">
                                                </td>
                                                <td class="py-1 pr-1">
                                                    <input x-model="s.qty" class="text-xs border rounded px-1 w-full text-right">
                                                </td>
                                                <td class="py-1 pr-1">
                                                    <input x-model="s.price" :readonly="s.src!=='a'" class="text-xs border rounded px-1 w-full text-right">
                                                </td>
                                                <td class="py-1 pr-1 text-right">
                                                    <span class="tabular-nums" x-text="'¥'+fmt(s.qty*s.price)"></span>
                                                </td>
                                                <td class="py-1 text-right">
                                                    <button type="button" class="text-xs text-red-400" @click="confirm('确认删除该配件？') && it.subs.splice(si,1)">×</button>
                                                </td>
                                            </tr>
                                        </template>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>
            </template>
            <!-- 合计 -->
            <div x-show="modules.length>0" class="text-right font-medium text-sm mt-3 pr-2">
                材料成本合计：
                <span class="text-xs text-slate-500 mr-2" x-show="totalItems>0">主材 <span x-text="'¥'+fmt(totalItems)"></span></span>
                <span class="text-xs text-slate-500 mr-2" x-show="totalSubs>0">配件 <span x-text="'¥'+fmt(totalSubs)"></span></span>
                <span class="text-blue-600 text-lg" x-text="'¥'+fmt(totalAll)"></span>
            </div>
            <button type="button" class="btn btn-secondary text-sm w-full mt-2" @click="addMod">+ 添加模块</button>
        </div>

        <!-- 汇总视图 -->
        <div x-show="bomView==='summary'" class="overflow-x-auto">
            <table class="data-table text-sm w-full">
                <thead><tr><th>物料</th><th>规格</th><th class="text-right">数量</th><th>单位</th><th class="text-right">单价</th><th class="text-right">金额</th><th>来源模块</th></tr></thead>
                <tbody>
                    <template x-for="r in summary" :key="r.k">
                        <tr><td x-text="r.n"></td><td x-text="r.spec" class="text-slate-500"></td><td class="text-right" x-text="r.q"></td><td x-text="r.u"></td><td class="text-right" x-text="'¥'+fmt(r.p)"></td><td class="text-right font-medium" x-text="'¥'+fmt(r.t)"></td><td class="text-xs text-slate-400" x-text="r.srcs"></td></tr>
                    </template>
                </tbody>
            </table>
            <div class="text-right mt-2 pr-4 font-medium">合计：<span class="text-blue-600" x-text="'¥'+fmt(summaryTotal)"></span></div>
        </div>
    </div>
    <?php endif; ?>

    <!-- 操作按钮 -->
    <div class="flex items-center justify-between">
        <div class="text-xs text-slate-400" x-show="isEdit" x-text="'最后更新：' + form.updated_at"></div>
        <div class="flex gap-3">
            <a href="/self_products.php" class="btn btn-secondary">返回</a>
            <?php if (canViewCost()): ?>
            <button type="button" class="btn btn-primary" id="btnSaveSp" @click="save" onclick="window.__saving=true">
                <i data-lucide="save" class="w-4 h-4 mr-1.5"></i>
                <span x-text="isEdit ? '保存修改' : '创建产品'"></span>
            </button>
            <?php else: ?>
            <span class="text-sm text-slate-400">您没有编辑权限</span>
            <?php endif; ?>
        </div>
    </div>

    </form>
</div>

<script>
// ===== 未保存拦截 =====
(function() {
    var dirty = false, saving = false;
    var f = document.getElementById('selfProductForm');
    if (!f) return;
    f.addEventListener('input', function() { dirty = true; });
    f.addEventListener('change', function() { dirty = true; });
    f.addEventListener('submit', function() { saving = true; dirty = false; });
    window.markDirty = function() { dirty = true; };
    document.addEventListener('click', function(e) {
        if (!dirty || saving) return;
        var a = e.target.closest('a[href]');
        if (!a) return;
        var href = a.getAttribute('href');
        if (!href || href.charAt(0) === '#' || href.startsWith('javascript:')) return;
        if (a.hostname && a.hostname !== location.hostname) return;
        if (a.closest('#selfProductForm')) return;
        e.preventDefault();
        e.stopImmediatePropagation();
        var dlg = document.createElement('div');
        dlg.style.cssText = 'position:fixed;z-index:99999;inset:0;background:rgba(0,0,0,.4);display:flex;align-items:center;justify-content:center';
        dlg.innerHTML = '<div style="background:#fff;border-radius:12px;padding:28px 32px;text-align:center;box-shadow:0 20px 40px rgba(0,0,0,.2)"><p style="margin:0 0 20px;font-size:14px;color:#334155">有未保存的修改</p><div style="display:flex;gap:12px;justify-content:center"><button id="_btnSave" style="background:#3b82f6;color:#fff;border:none;border-radius:6px;padding:8px 20px;font-size:14px;cursor:pointer">保存并离开</button><button id="_btnDiscard" style="background:#f1f5f9;color:#475569;border:1px solid #e2e8f0;border-radius:6px;padding:8px 20px;font-size:14px;cursor:pointer">不保存</button></div></div>';
        document.body.appendChild(dlg);
        document.getElementById('_btnSave').onclick = function() {
            dlg.remove(); saving = true;
            document.getElementById('btnSaveSp').click();
        };
        document.getElementById('_btnDiscard').onclick = function() { dlg.remove(); dirty = false; window.location.href = href; };
        dlg.addEventListener('click', function(ev) { if (ev.target === dlg) dlg.remove(); });
    }, true);
    window.addEventListener('beforeunload', function(e) {
        if (dirty && !saving && !window.__saving) { e.preventDefault(); e.returnValue = ''; }
    });
})();

document.addEventListener('alpine:init', () => {
    Alpine.data('selfProductForm', (init) => {
        // 产品列表（搜索用）
        const PL = <?= $prodJson ?> || [];
        const SL = <?= $spJson ?> || [];

        // 从服务器 modules 数据还原前端结构
        function normModules(rawModules) {
            if (!rawModules || !rawModules.length) return [{ name: '', _open: true, items: [] }];
            return rawModules.map(m => ({
                name: m.name || '',
                _open: true,
                items: (m.items || []).map(it => {
                    const src = it.self_product_id ? 's' : (it.product_id ? 'p' : (it.item_name ? 'a' : 'p'));
                    return {
                        src: src,
                        pid: it.product_id || '',
                        sid: it.self_product_id || '',
                        name: it.item_name || '',
                        spec: it.spec || '',
                        unit: it.unit || '',
                        qty: parseFloat(it.quantity) || 0,
                        price: parseFloat(it.unit_price) || 0,
                        _prodOpen: false, _prodFilter: '',
                        _prodShow: it.product_id ? (PL.find(x => x.id == it.product_id)?.label || '') : (it.item_name || ''),
                        _spOpen: false, _spFilter: '',
                        _spShow: it.self_product_id ? (SL.find(x => x.id == it.self_product_id)?.label || '') : '',
                        _collapsed: true,
                        subs: (it.subs || []).map(s => {
                            const ssrc = s.self_product_id ? 's' : (s.product_id ? 'p' : (s.item_name ? 'a' : 'a'));
                            return {
                                src: ssrc,
                                pid: s.product_id || '',
                                sid: s.self_product_id || '',
                                name: s.item_name || '',
                                spec: s.spec || '',
                                unit: s.unit || '',
                                qty: parseFloat(s.quantity) || 0,
                                price: parseFloat(s.unit_price) || 0,
                                _prodOpen: false, _prodFilter: '',
                                _prodShow: s.product_id ? (PL.find(x => x.id == s.product_id)?.label || '') : (s.item_name || ''),
                                _spOpen: false, _spFilter: '',
                                _spShow: s.self_product_id ? (SL.find(x => x.id == s.self_product_id)?.label || '') : '',
                            };
                        }),
                    };
                }),
            }));
        }

        const comp = {
            tab: 'info',
            bomView: 'module',  // BOM 视图：module | summary
            initId: init.id,
            isEdit: init.isEdit,
            form: {
                sku: init.selfProduct?.sku || '',
                name: init.selfProduct?.name || '',
                model_no: init.selfProduct?.model_no || '',
                spec: init.selfProduct?.spec || '',
                unit: init.selfProduct?.unit || '套',
                description: init.selfProduct?.description || '',
                status: init.selfProduct?.status !== undefined ? String(init.selfProduct.status) : '1',
                labor_cost: init.selfProduct?.labor_cost || 0,
                overhead_cost: init.selfProduct?.overhead_cost || 0,
                other_cost: init.selfProduct?.other_cost || 0,
                guide_price: init.selfProduct?.guide_price || 0,
                min_discount: init.selfProduct?.min_discount || 1.00,
                guide_margin_rate: init.selfProduct?.guide_margin_rate || 30.00,
                min_margin_rate: init.selfProduct?.min_margin_rate || 15.00,
                cost_remark: init.selfProduct?.cost_remark || '',
                remark: init.selfProduct?.remark || '',
                updated_at: init.selfProduct?.updated_at || '',
            },
            modules: normModules(init.bomItems?.modules || []),
            imagePreview: init.selfProduct?.image ? '/uploads/' + init.selfProduct.image : null,
            imageFile: null,
            imageChanged: false,
            imageRemoved: false,
            submitted: false,

            // --- 模块操作 ---
            addMod() {
                this.modules.push({ name: '', _open: true, items: [] });
                this.$nextTick(() => { lucide.createIcons(); });
            },
            moveMod(i, d) {
                const t = i + d;
                if (t < 0 || t >= this.modules.length) return;
                [this.modules[i], this.modules[t]] = [this.modules[t], this.modules[i]];
            },
            addItem(mi) {
                this.modules[mi].items = [...this.modules[mi].items, {
                    src: 'p', pid: '', sid: '', name: '', spec: '', unit: '', qty: 0, price: 0,
                    _prodOpen: false, _prodFilter: '', _prodShow: '',
                    _spOpen: false, _spFilter: '', _spShow: '',
                    _collapsed: true, subs: [],
                }];
                this.modules[mi]._open = true;
            },
            addSub(it) {
                it.subs = [...it.subs, {
                    src: 'p', pid: '', sid: '', name: '', spec: '', unit: '', qty: 0, price: 0,
                    _prodOpen: false, _prodFilter: '', _prodShow: '',
                    _spOpen: false, _spFilter: '', _spShow: '',
                }];
                it._collapsed = false;
            },

            // --- 搜索 ---
            filteredProducts(q) {
                q = (q || '').toLowerCase();
                return q ? PL.filter(p => p.label.toLowerCase().includes(q)) : PL;
            },
            filteredSp(q) {
                q = (q || '').toLowerCase();
                return q ? SL.filter(s => s.label.toLowerCase().includes(q)) : SL;
            },
            pickProduct(it, p) {
                it.pid = p.id; it._prodShow = p.label; it._prodFilter = ''; it._prodOpen = false;
                it.price = p.price; it.unit = p.unit; it.spec = p.spec || ''; it.name = '';
            },
            pickSp(it, s) {
                it.sid = s.id; it._spShow = s.label; it._spFilter = ''; it._spOpen = false;
                it.price = s.price; it.unit = s.unit; it.name = '';
            },
            clearItem(it, src) {
                if (src === 'p') { it.pid = ''; it._prodShow = ''; it.price = 0; it.spec = ''; it.unit = ''; }
                else { it.sid = ''; it._spShow = ''; it.price = 0; it.unit = ''; }
            },
            srcChanged(it) {
                ['pid', 'sid', 'name', 'price', '_prodShow', '_spShow', 'spec', 'unit'].forEach(k => it[k] = '');
            },

            // --- 金额计算 ---
            moduleSum(i) {
                const mod = this.modules[i]; let t = 0;
                (mod.items || []).forEach(it => {
                    t += (it.qty || 0) * (it.price || 0);
                    (it.subs || []).forEach(s => t += (s.qty || 0) * (s.price || 0));
                });
                return t;
            },
            moduleItemSum(i) {
                const mod = this.modules[i]; let t = 0;
                (mod.items || []).forEach(it => { t += (it.qty || 0) * (it.price || 0); });
                return t;
            },
            moduleSubSum(i) {
                const mod = this.modules[i]; let t = 0;
                (mod.items || []).forEach(it => {
                    (it.subs || []).forEach(s => { t += (s.qty || 0) * (s.price || 0); });
                });
                return t;
            },
            get totalAll() { let t = 0; this.modules.forEach((m, i) => t += this.moduleSum(i)); return t; },
            get totalItems() { let t = 0; this.modules.forEach((m, i) => t += this.moduleItemSum(i)); return t; },
            get totalSubs() { let t = 0; this.modules.forEach((m, i) => t += this.moduleSubSum(i)); return t; },

            // --- 汇总视图 ---
            get summary() {
                const m = {};
                const add = (k, n, spec, u, q, p, src) => {
                    if (!m[k]) m[k] = { k, n, spec, u, q: 0, p, t: 0, srcs: new Set };
                    m[k].q = +(m[k].q + parseFloat(q)).toFixed(2);
                    m[k].t = +(m[k].t + parseFloat(q) * parseFloat(p)).toFixed(2);
                    m[k].srcs.add(src);
                };
                this.modules.forEach(mod => {
                    (mod.items || []).forEach(it => {
                        const nm = it.src === 'a' ? it.name : (it.pid ? (PL.find(x => String(x.id) == String(it.pid))?.label) : SL.find(x => String(x.id) == String(it.sid))?.label);
                        add(nm || '?', nm || '?', it.spec, it.unit, it.qty || 0, it.price || 0, mod.name);
                        (it.subs || []).forEach(s => {
                            const sn = s.src === 'a' ? s.name : (s.pid ? (PL.find(x => String(x.id) == String(s.pid))?.label) : SL.find(x => String(x.id) == String(s.sid))?.label);
                            add(sn || '?', sn || '?', s.spec, s.unit, s.qty || 0, s.price || 0, mod.name);
                        });
                    });
                });
                return Object.values(m).map(r => ({ ...r, srcs: [...r.srcs].join(', ') }));
            },
            get summaryTotal() { return this.summary.reduce((t, r) => t + r.t, 0); },

            // --- 成本（用于基本信息Tab） ---
            get calcMaterialCost() { return this.totalAll; },
            get totalCost() {
                return this.calcMaterialCost
                    + (parseFloat(this.form.labor_cost) || 0)
                    + (parseFloat(this.form.overhead_cost) || 0)
                    + (parseFloat(this.form.other_cost) || 0);
            },
            calcTotal() {},
            get marginPercent() {
                const price = this.totalCost / (1 - parseFloat(this.form.guide_margin_rate || 30) / 100);
                if (price <= 0) return '—';
                return ((price - this.totalCost) / price * 100).toFixed(1);
            },
            get marginColor() {
                const m = parseFloat(this.marginPercent);
                if (isNaN(m)) return 'text-slate-400';
                return m >= 15 ? 'text-emerald-600' : (m >= 5 ? 'text-amber-600' : 'text-red-600');
            },
            get marginLabel() {
                const m = parseFloat(this.marginPercent);
                if (isNaN(m)) return '';
                return m >= 15 ? '健康' : (m >= 5 ? '偏低' : '警告');
            },
            formatMoney(v) { return Number(v || 0).toFixed(2); },
            fmt(v) { return (parseFloat(v) || 0).toFixed(2); },

            // --- 图片 ---
            handleImageUpload(e) {
                const file = e.target.files[0];
                if (!file) return;
                if (file.size > 10 * 1024 * 1024) { alert('图片不能超过10MB'); return; }
                if (!['image/jpeg', 'image/png', 'image/gif', 'image/webp'].includes(file.type)) { alert('仅支持JPG/PNG/GIF/WEBP'); return; }
                this.imageFile = file;
                this.imageChanged = true;
                this.imageRemoved = false;
                const reader = new FileReader();
                reader.onload = (ev) => { this.imagePreview = ev.target.result; };
                reader.readAsDataURL(file);
            },
            removeImage() {
                if (!confirm('确定删除主图吗？')) return;
                this.imagePreview = null;
                this.imageFile = null;
                this.imageChanged = true;
                this.imageRemoved = true;
                if (this.$refs.imageInput) this.$refs.imageInput.value = '';
            },

            // --- 保存 ---
            async save() {
                if (this.submitted) return;
                if (!this.form.name.trim()) { alert('请输入产品名称'); return; }
                this.submitted = true;

                // 序列化 BOM：模块 → 主材 → 配件
                const ser = it => {
                    let source = 'adhoc';
                    if (it.src === 'p' && it.pid) source = 'product';
                    else if (it.src === 's' && it.sid) source = 'self_product';
                    return {
                        source_type: source,
                        product_id: source === 'product' ? parseInt(it.pid) : null,
                        self_product_id: source === 'self_product' ? parseInt(it.sid) : null,
                        item_name: source === 'product' ? null : (source === 'self_product' ? null : ((it.name || it._prodShow || '').trim() || null)),
                        spec: it.spec || '',
                        unit: it.unit || '',
                        quantity: parseFloat(it.qty) || 0,
                        unit_price: parseFloat(it.price) || 0,
                        sub_items: (it.subs || []).map(ser),
                    };
                };

                const fd = new FormData();
                fd.append('id', init.id);
                fd.append('name', this.form.name);
                fd.append('model_no', this.form.model_no);
                fd.append('spec', this.form.spec);
                fd.append('unit', this.form.unit);
                fd.append('description', this.form.description);
                fd.append('status', this.form.status);
                fd.append('labor_cost', this.form.labor_cost);
                fd.append('overhead_cost', this.form.overhead_cost);
                fd.append('other_cost', this.form.other_cost);
                const gp = this.totalCost / (1 - parseFloat(this.form.guide_margin_rate || 30) / 100);
                fd.append('guide_price', gp.toFixed(2));
                fd.append('min_discount', this.form.min_discount);
                fd.append('guide_margin_rate', this.form.guide_margin_rate);
                fd.append('min_margin_rate', this.form.min_margin_rate);
                fd.append('cost_remark', this.form.cost_remark || '');
                fd.append('remark', this.form.remark);
                fd.append('material_cost', this.calcMaterialCost.toFixed(2));
                fd.append('total_cost', this.totalCost.toFixed(2));

                if (this.imageFile) {
                    fd.append('image', this.imageFile);
                } else if (this.imageRemoved) {
                    fd.append('image_remove', '1');
                }

                // BOM: 模块结构
                fd.append('bom', JSON.stringify(this.modules.map(m => ({
                    name: m.name,
                    items: (m.items || []).map(ser),
                }))));

                try {
                    const resp = await fetch('/api/self_product_save.php', {
                        method: 'POST',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-Token': document.querySelector('input[name="_csrf"]')?.value || '',
                        },
                        body: fd,
                    });
                    const text = await resp.text();
                    let data;
                    try { data = JSON.parse(text); } catch { throw new Error('服务器返回非JSON (' + resp.status + '): ' + text.substring(0, 200)); }
                    if (data.ok) {
                        window.location.href = '/self_products.php';
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
