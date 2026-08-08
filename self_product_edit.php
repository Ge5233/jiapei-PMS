<?php
/**
 * 自产产品 新增/编辑
 */
define('PMS_ENTRY', true);
require_once __DIR__ . '/includes/bootstrap.php';
if (!PMS_INSTALLED) { header('Location: /install.php'); exit; }
requireLogin();

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
], JSON_UNESCAPED_UNICODE)) ?>)">

    <div class="flex items-center justify-between mb-4">
        <div>
            <h2 class="text-lg font-medium text-slate-800"><?= $isEdit ? '编辑自产产品' : '新增自产产品' ?></h2>
        </div>
        <a href="/self_products.php" class="btn btn-secondary text-sm">
            <i data-lucide="arrow-left" class="w-4 h-4 mr-1"></i>返回列表
        </a>
    </div>

    <form @submit.prevent="save">

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
                    <label class="form-label">指导售价系数</label>
                    <div class="relative">
                        <input type="number" x-model="form.guide_price_coefficient" step="0.001" min="0.1" max="5" class="form-input tabular-nums" @input="calcTotal">
                        <span class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm">×</span>
                    </div>
                </div>
                <div>
                    <label class="form-label">指导售价</label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm">¥</span>
                        <input type="text" class="form-input pl-7 bg-slate-50 font-medium tabular-nums" readonly
                               :value="formatMoney(totalCost * form.guide_price_coefficient)" tabindex="-1">
                    </div>
                    <p class="text-xs text-slate-400 mt-1">= 总成本 × 系数</p>
                </div>
                <div></div>
            </div>

            <div class="grid grid-cols-3 gap-4">
                <div>
                    <label class="form-label">最低售价系数</label>
                    <div class="relative">
                        <input type="number" x-model="form.min_price_coefficient" step="0.001" min="0.1" max="5" class="form-input tabular-nums" @input="calcTotal">
                        <span class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm">×</span>
                    </div>
                </div>
                <div>
                    <label class="form-label">最低售价</label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm">¥</span>
                        <input type="text" class="form-input pl-7 bg-slate-50 tabular-nums" readonly
                               :value="formatMoney(totalCost * form.min_price_coefficient)" tabindex="-1">
                    </div>
                    <p class="text-xs text-slate-400 mt-1">= 总成本 × 系数</p>
                </div>
                <div>
                    <label class="form-label">最高折扣</label>
                    <div class="relative">
                        <input type="text" class="form-input pr-9 bg-slate-50 tabular-nums" readonly
                               :value="form.guide_price_coefficient > 0 ? (form.min_price_coefficient / form.guide_price_coefficient * 100).toFixed(0) + '%' : '-'" tabindex="-1">
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

    <!-- BOM 物料清单 -->
    <div class="card p-6 mb-4">
        <div class="flex items-center justify-between mb-4 pb-2 border-b border-slate-100">
            <h3 class="text-base font-medium text-slate-800">BOM 物料清单</h3>
            <div class="flex gap-2">
                <?php if ($isEdit): ?>
                <a href="/export_bom.php?self_product_id=<?= $id ?>" class="btn btn-secondary text-sm">
                    <i data-lucide="download" class="w-3.5 h-3.5 mr-1"></i>导出 Excel
                </a>
                <?php endif; ?>
                <button type="button" class="btn btn-secondary text-sm" @click="addBomItem">
                    <i data-lucide="plus" class="w-3.5 h-3.5 mr-1"></i>添加物料
                </button>
            </div>
        </div>

        <template x-if="bomItems.length === 0">
            <div class="text-center py-8 text-slate-400 text-sm">
                <i data-lucide="clipboard-list" class="w-8 h-8 mx-auto mb-2 text-slate-300"></i>
                暂无物料，点击「添加物料」开始
            </div>
        </template>

        <template x-if="bomItems.length > 0">
            <div class="overflow-x-auto">
                <table class="w-full">
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
                                    <select :value="item.product_id ? 'linked' : 'adhoc'"
                                            class="form-select text-xs py-1 px-2"
                                            @change="switchBomType(idx, $event.target.value)">
                                        <option value="linked">外采产品</option>
                                        <option value="adhoc">临时物料</option>
                                    </select>
                                </td>
                                <td class="px-3 py-2">
                                    <!-- 外采产品模式 -->
                                    <template x-if="item.product_id || item.product_id === ''">
                                        <select class="form-select text-sm" :value="item.product_id"
                                                @change="bomProductChanged(idx, $event.target.value)">
                                            <option value="">-- 选择产品 --</option>
                                            <template x-for="p in allProducts" :key="p.id">
                                                <option :value="p.id" x-text="p.sku + ' ' + p.name"></option>
                                            </template>
                                        </select>
                                    </template>
                                    <!-- 临时物料模式 -->
                                    <template x-if="item.product_id === null">
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
                                    <!-- 临时物料：手动填 -->
                                    <template x-if="item.product_id === null">
                                        <input type="number" x-model="item.unit_cost" step="0.01" min="0"
                                               class="form-input text-sm text-right w-full" @input="calcTotal">
                                    </template>
                                </td>
                                <td class="px-3 py-2 text-right text-sm tabular-nums font-medium">
                                    ¥<span x-text="formatMoney(bomItemSubtotal(item))"></span>
                                </td>
                                <td class="px-3 py-2 text-center">
                                    <button type="button" class="text-red-400 hover:text-red-600 text-sm"
                                            @click="removeBomItem(idx)" title="删除">&times;</button>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                    <tfoot>
                        <tr class="bg-slate-50 font-medium">
                            <td colspan="6" class="px-3 py-2 text-right text-sm text-slate-600">材料成本合计</td>
                            <td class="px-3 py-2 text-right text-sm tabular-nums">¥<span x-text="formatMoney(calcMaterialCost)"></span></td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </template>
    </div>

    <!-- 备注 -->
    <div class="card p-6 mb-4">
        <h3 class="text-base font-medium text-slate-800 mb-4 pb-2 border-b border-slate-100">备注</h3>
        <textarea x-model="form.remark" class="form-input" rows="2" maxlength="2000"
                  placeholder="内部备注（不对客户展示）"></textarea>
    </div>

    <!-- 操作按钮 -->
    <div class="flex items-center justify-between">
        <div class="text-xs text-slate-400" x-show="isEdit" x-text="'最后更新：' + form.updated_at"></div>
        <div class="flex gap-3">
            <a href="/self_products.php" class="btn btn-secondary">取消</a>
            <button type="submit" class="btn btn-primary">
                <i data-lucide="save" class="w-4 h-4 mr-1.5"></i>
                <span x-text="isEdit ? '保存修改' : '创建产品'"></span>
            </button>
        </div>
    </div>

    </form>
</div>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('selfProductForm', (init) => {
        // 构建外采产品索引（id → 对象）
        const productMap = {};
        (init.allProducts || []).forEach(p => { productMap[p.id] = p; });

        return {
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
                guide_price: init.selfProduct?.guide_price || 0,
                min_discount: init.selfProduct?.min_discount || 1.00,
                guide_price_coefficient: init.selfProduct?.guide_price_coefficient || 1.600,
                min_price_coefficient: init.selfProduct?.min_price_coefficient || 0.900,
                cost_remark: init.selfProduct?.cost_remark || '',
                remark: init.selfProduct?.remark || '',
                updated_at: init.selfProduct?.updated_at || '',
            },
            bomItems: (init.bomItems || []).map(item => ({
                ...item,
                product_id: item.product_id ? String(item.product_id) : null,
                // 如果是关联产品，从 productMap 取得最新进价
                _product_cost: item.product_id ? (productMap[item.product_id]?.cost_price || 0) : 0,
            })),
            allProducts: init.allProducts || [],
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

            // --- BOM ---
            addBomItem() {
                this.bomItems.push({
                    product_id: '',
                    item_name: '',
                    quantity: 1,
                    unit: '',
                    unit_cost: 0,
                    _product_cost: 0,
                    sort_order: this.bomItems.length,
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
                if (type === 'linked') {
                    item.product_id = '';
                    item._product_cost = 0;
                } else {
                    item.product_id = null;
                    item.item_name = '';
                    item.unit_cost = 0;
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
            bomItemSubtotal(item) {
                const qty = parseFloat(item.quantity) || 0;
                const cost = item.product_id
                    ? (parseFloat(item._product_cost) || 0)
                    : (parseFloat(item.unit_cost) || 0);
                return qty * cost;
            },

            // --- 成本计算 ---
            get calcMaterialCost() {
                return this.bomItems.reduce((sum, item) => sum + this.bomItemSubtotal(item), 0);
            },
            get totalCost() {
                return this.calcMaterialCost
                    + (parseFloat(this.form.labor_cost) || 0)
                    + (parseFloat(this.form.overhead_cost) || 0);
            },
            calcTotal() {},
            get marginPercent() {
                const price = this.totalCost * parseFloat(this.form.guide_price_coefficient || 1.6);
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
                // 指导售价 = 总成本 × 系数（自动计算）
                const gp = this.totalCost * parseFloat(this.form.guide_price_coefficient || 1.6);
                fd.append('guide_price', gp.toFixed(2));
                fd.append('min_discount', this.form.min_discount);
                fd.append('guide_price_coefficient', this.form.guide_price_coefficient);
                fd.append('min_price_coefficient', this.form.min_price_coefficient);
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
                    item_name: item.item_name || null,
                    quantity: parseFloat(item.quantity) || 0,
                    unit: item.unit || '',
                    unit_cost: item.product_id ? 0 : (parseFloat(item.unit_cost) || 0),
                    sort_order: i,
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
