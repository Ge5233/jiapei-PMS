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
        setFlash('danger', '产品不存在');
        header('Location: /self_products.php');
        exit;
    }
    $bomItems = SelfProduct::getBom($id);
}

// 外采产品列表（给BOM下拉）
$allProducts = Product::allForSelect();
// 自产产品列表（BOM自产类型）
$allSelfProducts = class_exists('SelfProduct') ? SelfProduct::allForSelect() : [];

$pageTitle = $isEdit ? '编辑自产产品' : '新增自产产品';
$activeMenu = 'self_products';
require __DIR__ . '/includes/views/header.php';
?>

<div class="max-w-4xl mx-auto" x-data="selfProductForm(<?= h(json_encode([
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

    <form @submit.prevent="save" id="selfProductForm">
    <?= csrfField() ?>

    <!-- 基本信息 -->
    <div class="card p-6 mb-4">
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
            <input type="file" x-ref="imageInput" accept="image/jpeg,image/png" class="hidden" @change="handleImageUpload">
        </div>

        <!-- 名称 / 型号 -->
        <div class="grid grid-cols-2 gap-4 mb-4">
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
    <div class="card p-6 mb-4">
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
    <!-- Tab 标签 -->
    <div class="flex gap-0 mb-4 border-b-2 border-slate-200">
        <button type="button" class="px-6 py-2.5 text-sm font-medium rounded-t-lg transition-colors"
                :class="tab==='info' ? 'bg-white text-blue-600 border-2 border-b-white border-slate-200 -mb-0.5' : 'text-slate-500 hover:text-slate-700'"
                @click="tab='info'">基本信息</button>
        <button type="button" class="px-6 py-2.5 text-sm font-medium rounded-t-lg transition-colors"
                :class="tab==='bom' ? 'bg-white text-blue-600 border-2 border-b-white border-slate-200 -mb-0.5' : 'text-slate-500 hover:text-slate-700'"
                @click="tab='bom'">BOM 物料清单</button>
    </div>

    <!-- 备注（基本信息Tab内） -->
    <div x-show="tab==='info'" class="card p-6 mb-4">
        <h3 class="text-base font-medium text-slate-800 mb-4 pb-2 border-b border-slate-100">备注</h3>
        <textarea x-model="form.remark" class="form-input" rows="2" maxlength="2000"
                  placeholder="内部备注（不对客户展示）"></textarea>
    </div>

    <!-- BOM 物料清单 Tab -->
    <div x-show="tab==='bom'" class="card p-6 mb-4" style="overflow:visible;min-height:360px;display:flex;flex-direction:column">
        <div class="flex items-center justify-between mb-4 pb-2 border-b border-slate-100">
            <h3 class="text-base font-medium text-slate-800">BOM 物料清单</h3>
            <div class="flex gap-2 items-center">
                <?php if ($isEdit): ?>
                <a href="/export_bom.php?self_product_id=<?= $id ?>" class="btn btn-secondary text-sm">
                    <i data-lucide="download" class="w-3.5 h-3.5 mr-1"></i>导出 Excel
                </a>
                <?php endif; ?>
                <button type="button" class="btn btn-secondary text-sm" @click="addModule">
                    <i data-lucide="plus" class="w-3.5 h-3.5 mr-1"></i>添加模块
                </button>
                <button type="button" class="btn btn-secondary text-sm" @click="addBomItem">
                    <i data-lucide="plus" class="w-3.5 h-3.5 mr-1"></i>添加物料
                </button>
            </div>
        </div>

        <!-- 空状态 -->
        <div x-show="bomItems.length === 0" class="text-center py-8 text-slate-400 text-sm flex-1">
            <i data-lucide="clipboard-list" class="w-8 h-8 mx-auto mb-2 text-slate-300"></i>
            暂无物料，点击「添加物料」开始
        </div>

        <!-- 物料表格 -->
        <div x-show="bomItems.length > 0" class="overflow-x-auto flex-1" style="overflow-x:auto;overflow-y:visible">
            <table class="w-full" style="overflow:visible">
                <thead>
                        <tr class="border-b border-slate-200 bg-slate-50">
                            <th class="text-left px-3 py-2 text-xs font-medium text-slate-500 w-8">#</th>
                            <th class="text-left px-3 py-2 text-xs font-medium text-slate-500">类型</th>
                            <th class="text-left px-3 py-2 text-xs font-medium text-slate-500">物料</th>
                            <th class="text-right px-3 py-2 text-xs font-medium text-slate-500 w-24">用量</th>
                            <th class="text-left px-3 py-2 text-xs font-medium text-slate-500 w-20">单位</th>
                            <th class="text-right px-3 py-2 text-xs font-medium text-slate-500 w-28">单价</th>
                            <th class="text-right px-3 py-2 text-xs font-medium text-slate-500 w-28">小计</th>
                            <th class="text-center px-3 py-2 text-xs font-medium text-slate-500 w-16">操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="(item, idx) in bomItems" :key="idx">
                            <tr class="border-b border-slate-100 hover:bg-slate-50">
                                <td class="px-3 py-2 text-sm text-slate-500" x-text="idx + 1"></td>
                                <td class="px-3 py-2">
                                    <select :value="bomTypeOf(item)"
                                            class="form-select text-xs py-1 px-2"
                                            @change="switchBomType(idx, $event.target.value)">
                                        <option value="product">外采产品</option>
                                        <option value="self_product">自产产品</option>
                                        <option value="adhoc">临时物料</option>
                                    </select>
                                </td>
<td class="px-3 py-2">
                                    <!-- 外采产品 -->
                                    <template x-if="bomTypeOf(item)==='product'">
                                        <div class="relative">
                                            <!-- 未选中：搜索框 -->
                                            <template x-if="!item.product_id">
                                                <input type="text" @focus="item._prodOpen=true" @input="item._prodFilter=$el.value" @keydown.escape="item._prodOpen=false"
                                                                                                   @click.away="item._prodOpen=false"
                                                                                                   x-model="item._prodShow" class="text-sm border rounded px-2 py-1.5 w-full" placeholder="搜索产品..." autocomplete="off">
                                            </template>
                                            <!-- 已选中：只读标签 -->
                                            <template x-if="item.product_id">
                                                <div class="ss-tag cursor-pointer min-w-[160px]" @click="item._prodOpen=true">
                                                    <span x-text="item._prodShow"></span>
                                                    <span class="ss-tag-x" @click.stop="bomClearProduct(idx)">&times;</span>
                                                </div>
                                            </template>
                                            <div x-show="item._prodOpen && filteredBomProducts(item._prodFilter||'').length>0" class="ss-dropdown">
                                                <template x-for="p in filteredBomProducts(item._prodFilter||'')" :key="p.id">
                                                    <div @mousedown.prevent="bomPickProduct(idx, p)" :class="{sel:item.product_id==p.id}">
                                                    <span x-text="p.sku + ' ' + p.name"></span>
                                                    <span class="text-xs text-slate-400 ml-1" x-text="p.spec ? '【'+p.spec+'】' : ''"></span>
                                                </div>
                                                </template>
                                            </div>
                                        </div>
                                    </template>
                                    <!-- 自产产品 -->
                                    <template x-if="bomTypeOf(item)==='self_product'">
                                        <div class="relative">
                                            <template x-if="!item.bom_self_product_id">
                                                <input type="text" @focus="item._spOpen=true" @input="item._spFilter=$el.value" @keydown.escape="item._spOpen=false"
                                                       @click.away="item._spOpen=false"
                                                       x-model="item._spShow" class="text-sm border rounded px-2 py-1.5 w-full" placeholder="搜索自产产品..." autocomplete="off">
                                            </template>
                                            <template x-if="item.bom_self_product_id">
                                                <div class="ss-tag cursor-pointer min-w-[160px]" @click="item._spOpen=true">
                                                    <span x-text="item._spShow"></span>
                                                    <span class="ss-tag-x" @click.stop="bomClearSp(idx)">&times;</span>
                                                </div>
                                            </template>
                                            <div x-show="item._spOpen && filteredBomSpProducts(item._spFilter||'').length>0" class="ss-dropdown">
                                                <template x-for="p in filteredBomSpProducts(item._spFilter||'')" :key="p.id">
                                                    <div @mousedown.prevent="bomPickSp(idx, p)" x-text="p.name"></div>
                                                </template>
                                            </div>
                                        </div>
                                    </template>
                                    <!-- 临时物料模式 -->
                                    <template x-if="bomTypeOf(item)==='adhoc'">
                                        <input type="text" x-model="item.item_name" class="form-input text-sm"
                                               placeholder="物料名称" @input="calcTotal">
                                    </template>
                                </td>
                                <td class="px-3 py-2">
                                    <input type="number" x-model="item.quantity" step="0.0001" min="0"
                                           class="form-input text-sm text-right" @input="calcTotal">
                                </td>
                                <td class="px-3 py-2">
                                    <input type="text" x-model="item.unit" class="form-input text-sm"
                                           placeholder="个/套/kg" @input="calcTotal">
                                </td>
                                <td class="px-3 py-2">
                                    <!-- 关联产品：显示最新进价（只读） -->
                                    <template x-if="item.product_id">
                                        <div class="text-sm text-right tabular-nums text-slate-600">
                                            ¥<span x-text="formatMoney(item._product_cost ?? 0)"></span>
                                            <div class="text-xs text-slate-400">（最新进价）</div>
                                        </div>
                                    </template>
                                    <!-- 关联自产产品：显示BOM成本 -->
                                    <template x-if="item.bom_self_product_id">
                                        <div class="text-sm text-right tabular-nums text-slate-600">
                                            ¥<span x-text="formatMoney(item._sp_cost ?? 0)"></span>
                                            <div class="text-xs text-slate-400">（BOM总成本）</div>
                                        </div>
                                    </template>
                                    <!-- 临时物料：手动填 -->
                                    <template x-if="bomTypeOf(item)==='adhoc'">
                                        <input type="number" x-model="item.unit_cost" step="0.01" min="0"
                                               class="form-input text-sm text-right w-full" @input="calcTotal">
                                    </template>
                                </td>
                                <td class="px-3 py-2 text-right text-sm tabular-nums font-medium">
                                    ¥<span x-text="formatMoney(bomItemSubtotal(item))"></span>
                                </td>
                                <td class="px-3 py-2 text-center">
                                    <button type="button" class="text-red-400 hover:text-red-600 text-sm"
                                            @click="confirm('确认删除该物料？') && removeBomItem(idx)" title="删除">&times;</button>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        <!-- 合计行 -->
        <div x-show="bomItems.length > 0" class="pt-2">
            <div class="flex justify-end items-center bg-slate-50 rounded px-3 py-2 font-medium">
                <span class="text-sm text-slate-600 mr-4">材料成本合计</span>
                <span class="text-sm tabular-nums">¥<span x-text="formatMoney(calcMaterialCost)"></span></span>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- 操作按钮 -->
    <div class="flex items-center justify-between">
        <div class="text-xs text-slate-400" x-show="isEdit" x-text="'最后更新：' + form.updated_at"></div>
        <div class="flex gap-3">
            <a href="/self_products.php" class="btn btn-secondary">返回</a>
            <?php if (canViewCost()): ?>
            <button type="submit" class="btn btn-primary" id="btnSaveSp">
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
        if (dirty && !saving) { e.preventDefault(); e.returnValue = ''; }
    });
})();

document.addEventListener('alpine:init', () => {
    Alpine.data('selfProductForm', (init) => {
        // 构建外采产品索引（id → 对象）
        const productMap = {};
        (init.allProducts || []).forEach(p => { productMap[p.id] = p; });
        const selfProductMap = {};
        (init.allSelfProducts || []).forEach(p => { selfProductMap[p.id] = p; });

        return {
            tab: 'info',  // Tab 状态
            isEdit: init.isEdit,
            form: {
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
            bomItems: (init.bomItems || []).map(item => ({
                ...item,
                product_id: item.product_id ? String(item.product_id) : (item.bom_self_product_id ? '' : ''),
                bom_self_product_id: item.bom_self_product_id ? String(item.bom_self_product_id) : '',
                _product_cost: item.product_id ? (productMap[item.product_id]?.cost_price || 0) : 0,
                _sp_cost: item.bom_self_product_id ? (selfProductMap[item.bom_self_product_id]?.total_cost || 0) : 0,
                _prodShow: item.product_id ? ((productMap[item.product_id]?.sku||'') + ' ' + (productMap[item.product_id]?.name||'')) : '',
                _spShow: item.bom_self_product_id ? (selfProductMap[item.bom_self_product_id]?.name||'') : '',
                _prodOpen: false, _prodFilter: '', _spOpen: false, _spFilter: '',
            })),
            allProducts: init.allProducts || [],
            allSelfProducts: init.allSelfProducts || [],
            filteredBomProducts(q) {
                q = (q || '').toLowerCase();
                return q ? this.allProducts.filter(p => (p.sku + ' ' + p.name).toLowerCase().includes(q)) : this.allProducts;
            },
            filteredBomSpProducts(q) {
                q = (q || '').toLowerCase();
                return q ? this.allSelfProducts.filter(p => p.name.toLowerCase().includes(q)) : this.allSelfProducts;
            },
            bomTypeOf(item) {
                if (item.product_id === null && item.bom_self_product_id === null) return 'adhoc';
                if (item.bom_self_product_id) return 'self_product';
                return 'product';
            },
            bomPickProduct(idx, p) {
                const item = this.bomItems[idx];
                item.product_id = p.id;
                item._prodShow = p.sku + ' ' + p.name;
                item._prodFilter = '';
                item._prodOpen = false;
                item.unit = p.unit || '';
                item.spec = p.spec || '';
                item._product_cost = parseFloat(p.cost_price) || 0;
                this.calcTotal();
            },
            bomPickSp(idx, p) {
                const item = this.bomItems[idx];
                item.bom_self_product_id = p.id;
                item._spShow = p.name;
                item._spFilter = '';
                item._spOpen = false;
                item.unit = p.unit || '';
                item._sp_cost = parseFloat(p.total_cost) || 0;
                this.calcTotal();
            },
            bomClearProduct(idx) {
                const item = this.bomItems[idx];
                item.product_id = '';
                item._prodShow = '';
                item._prodOpen = false;
                item._product_cost = 0;
                this.calcTotal();
            },
            bomClearSp(idx) {
                const item = this.bomItems[idx];
                item.bom_self_product_id = '';
                item._spShow = '';
                item._spOpen = false;
                item._sp_cost = 0;
                this.calcTotal();
            },
            filterBomProducts() { /* getter 处理 */ },
            imagePreview: init.selfProduct?.image ? '/uploads/' + init.selfProduct.image : null,
            imageFile: null,           // File 对象
            imageChanged: false,       // 是否改过图片
            imageRemoved: false,       // 是否删除了图片
            submitted: false,

            // --- 图片 ---
            handleImageUpload(e) {
                const file = e.target.files[0];
                if (!file) return;
                if (file.size > 2 * 1024 * 1024) { alert('图片不能超过2MB'); return; }
                if (!['image/jpeg', 'image/png'].includes(file.type)) { alert('仅支持JPG/PNG'); return; }
                this.imageFile = file;
                this.imageChanged = true;
                this.imageRemoved = false;
                const reader = new FileReader();
                reader.onload = (ev) => { this.imagePreview = ev.target.result; };
                reader.readAsDataURL(file);
            },
            removeImage() {
                this.imagePreview = null;
                this.imageFile = null;
                this.imageChanged = true;
                this.imageRemoved = true;
                if (this.$refs.imageInput) this.$refs.imageInput.value = '';
            },

            // --- 模块管理 ---
            modules: [], // 模块列表 [{name, collapsed:false}]
            getModuleNames() {
                const names = new Set();
                this.bomItems.forEach(item => {
                    if (item.module_name) names.add(item.module_name);
                });
                return Array.from(names);
            },
            addModule() {
                const name = prompt('模块名称（如：框架结构、电控系统）：');
                if (!name || !name.trim()) return;
                this.bomItems.push({
                    product_id: '',
                    bom_self_product_id: '',
                    item_name: '',
                    quantity: 1,
                    unit: '',
                    unit_cost: 0,
                    _product_cost: 0,
                    _sp_cost: 0,
                    _prodShow: '',
                    _spShow: '',
                    _prodOpen: false,
                    _prodFilter: '',
                    _spOpen: false,
                    _spFilter: '',
                    sort_order: this.bomItems.length,
                    module_name: name.trim(),
                    remark: '',
                });
                this.$nextTick(() => { lucide.createIcons(); });
                this.tab = 'bom'; // 切到 BOM Tab
            },

            // --- BOM ---
            addBomItem() {
                this.bomItems.push({
                    product_id: '',
                    bom_self_product_id: '',
                    item_name: '',
                    quantity: 1,
                    unit: '',
                    unit_cost: 0,
                    _product_cost: 0,
                    _sp_cost: 0,
                    _prodShow: '',
                    _spShow: '',
                    _prodOpen: false,
                    _prodFilter: '',
                    _spOpen: false,
                    _spFilter: '',
                    sort_order: this.bomItems.length,
                    module_name: '',
                    remark: '',
                });
                this.$nextTick(() => { lucide.createIcons(); });
            },
            removeBomItem(idx) {
                this.bomItems.splice(idx, 1);
                this.calcTotal();
            },
            switchBomType(idx, type) {
                const item = this.bomItems[idx];
                // clear all
                item.product_id = '';
                item.bom_self_product_id = '';
                item.item_name = '';
                item._product_cost = 0;
                item._sp_cost = 0;
                item.unit_cost = 0;
                item._prodShow = '';
                item._spShow = '';
                if (type === 'product') {
                    item.product_id = '';
                } else if (type === 'self_product') {
                    item.bom_self_product_id = '';
                } else {
                    // adhoc - stays cleared
                }
                this.calcTotal();
            },
            bomProductChanged(idx, pid) {
                const item = this.bomItems[idx];
                item.product_id = pid;
                if (pid && productMap[pid]) {
                    item.unit = productMap[pid].unit || '';
                    item._product_cost = parseFloat(productMap[pid].cost_price) || 0;
                } else {
                    item._product_cost = 0;
                }
                this.calcTotal();
            },
            bomSpChanged(idx, sid) {
                const item = this.bomItems[idx];
                item.bom_self_product_id = sid;
                if (sid && selfProductMap[sid]) {
                    item.unit = selfProductMap[sid].unit || '';
                    item._sp_cost = parseFloat(selfProductMap[sid].total_cost) || 0;
                } else {
                    item._sp_cost = 0;
                }
                this.calcTotal();
            },
            bomItemSubtotal(item) {
                const qty = parseFloat(item.quantity) || 0;
                let cost = 0;
                if (item.product_id) {
                    cost = parseFloat(item._product_cost) || 0;
                } else if (item.bom_self_product_id) {
                    cost = parseFloat(item._sp_cost) || 0;
                } else {
                    cost = parseFloat(item.unit_cost) || 0;
                }
                return qty * cost;
            },

            // --- 成本计算 ---
            get calcMaterialCost() {
                return this.bomItems.reduce((sum, item) => sum + this.bomItemSubtotal(item), 0);
            },
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
                const m = ((price - this.totalCost) / price * 100);
                return m.toFixed(1);
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
            formatMoney(v) {
                return Number(v || 0).toFixed(2);
            },

            // --- 保存 ---
            async save() {
                if (this.submitted) return;
                if (!this.form.name.trim()) { alert('请输入产品名称'); return; }
                this.submitted = true;

                // 用 FormData 支持图片上传
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
                // 指导售价 = 总成本 × 系数（自动计算）
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

                // BOM 数据
                fd.append('bom', JSON.stringify(this.bomItems.map((item, i) => ({
                    product_id: item.product_id || null,
                    bom_self_product_id: item.bom_self_product_id || null,
                    item_name: item.item_name || null,
                    quantity: parseFloat(item.quantity) || 0,
                    unit: item.unit || '',
                    unit_cost: item.product_id || item.bom_self_product_id ? 0 : (parseFloat(item.unit_cost) || 0),
                    sort_order: i,
                    module_name: item.module_name || null,
                    remark: item.remark || '',
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
    });
});
</script>

<?php require __DIR__ . '/includes/views/footer.php'; ?>
