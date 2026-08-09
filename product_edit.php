<?php
/**
 * 产品编辑/新增
 */
define('PMS_ENTRY', true);
require_once __DIR__ . '/includes/bootstrap.php';
if (!PMS_INSTALLED) { header('Location: /install.php'); exit; }
requireLogin();
// 员工可浏览但不编辑

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$product = null;
$isEdit = false;

if ($id > 0) {
    $product = Product::find($id);
    if (!$product) {
        flash('error', '产品不存在');
        header('Location: /products.php');
        exit;
    }
    $isEdit = true;
}

$categories = Category::allGrouped();
$suppliers = Supplier::allActive();

// 包装供应商数据给 combobox（保留 联系人 后缀便于搜索）
$allSuppliers = [];
foreach ($suppliers as $s) {
    $label = $s['name'] . ($s['contact'] ? '（' . h($s['contact']) . '）' : '');
    $allSuppliers[] = [
        'id' => (int)$s['id'],
        'name' => $label,
        'children' => []
    ];
}
$currentSupplierId = (int)($product['supplier_id'] ?? 0);
$currentSupplierLabel = '';
foreach ($allSuppliers as $s) {
    if ($s['id'] === $currentSupplierId) {
        $currentSupplierLabel = $s['name'];
        break;
    }
}

$files = $isEdit ? FileModel::listByProduct($id) : [];
$history = $isEdit ? PriceHistory::listByProduct($id, 20) : [];

// 收集所有分类给前端树形 combobox（一级 + 二级，带 parent_sort_id 和 sub_id）
$allCategories = [];
foreach ($categories as $c) {
    $parentSortId = (int)($c['parent_sort_id'] ?? 0);
    $allCategories[] = [
        'id' => (int)$c['id'],
        'name' => $c['name'],
        'parent_id' => 0,
        'parent_sort_id' => $parentSortId,
        'sub_id' => 0
    ];
    foreach ($c['children'] as $sub) {
        $allCategories[] = [
            'id' => (int)$sub['id'],
            'name' => $sub['name'],
            'parent_id' => (int)$c['id'],
            'parent_sort_id' => $parentSortId,
            'sub_id' => (int)($sub['sub_id'] ?? 0)
        ];
    }
}
$currentCategoryId = (int)($product['category_id'] ?? 0);
$currentCategoryLabel = '';
foreach ($allCategories as $c) {
    if ($c['id'] === $currentCategoryId) {
        if ($c['parent_id'] > 0) {
            foreach ($allCategories as $p) {
                if ($p['id'] === $c['parent_id']) {
                    $currentCategoryLabel = $p['name'] . ' / ' . $c['name'];
                    break;
                }
            }
        } else {
            $currentCategoryLabel = $c['name'];
        }
        break;
    }
}

$pageTitle = $isEdit ? '编辑产品' : '新增产品';
$activeMenu = 'products';
require __DIR__ . '/includes/views/header.php';
?>

<div class="mb-4">
    <a href="/products.php" class="text-sm text-slate-500 hover:text-slate-700 flex items-center w-fit" id="backToList">
        <i data-lucide="arrow-left" class="w-4 h-4 mr-1"></i>返回列表
    </a>
    <?php if (isset($_GET['created'])): ?>
    <div class="mt-2 p-3 bg-emerald-50 border border-emerald-200 rounded flex items-center justify-between">
        <span class="text-sm text-emerald-700">✅ 产品创建成功！</span>
        <div class="flex gap-2">
            <a href="/product_edit.php" class="btn btn-primary px-3 py-1 text-sm">继续新增</a>
            <a href="/products.php" class="btn btn-secondary px-3 py-1 text-sm">返回列表</a>
        </div>
    </div>
    <?php endif; ?>
    <?php if (isset($_GET['saved'])): ?>
    <div class="mt-2 p-3 bg-emerald-50 border border-emerald-200 rounded">
        <span class="text-sm text-emerald-700">✅ 保存成功</span>
    </div>
    <?php endif; ?>
</div>

<form method="post" action="/api/product_save.php" id="productForm" class="max-w-3xl">
    <?= csrfField() ?>
    <input type="hidden" name="id" value="<?= $id ?>">

    <div class="card mb-4">
        <div class="card-header">基本信息</div>
        <div class="card-body grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="md:col-span-2">
                <label class="form-label">产品名称 <span class="text-red-500">*</span></label>
                <input type="text" name="name" class="form-input" required value="<?= h($product['name'] ?? '') ?>">
            </div>
            <div>
                <label class="form-label">产品编码 / SKU <span class="text-red-500">*</span></label>
                <div class="flex gap-1.5">
                    <input type="text" name="sku" id="sku" class="form-input tabular-nums flex-1" required value="<?= h($product['sku'] ?? '') ?>" placeholder="选择分类后自动生成">
                    <button type="button" id="generateSkuBtn" class="px-3 py-2 text-sm bg-slate-100 text-slate-700 rounded-md hover:bg-slate-200 transition-colors flex items-center gap-1" title="根据分类自动生成 SKU">
                        <i data-lucide="refresh-cw" class="w-3.5 h-3.5"></i>
                        生成
                    </button>
                </div>
                <p class="form-help">格式：父分类ID(2位) + 子分类ID(2位) + 序号(3位)，选择分类后自动生成。当前 SKU 是否符合规则？<span id="skuStatus" class="text-xs text-slate-400">检查中...</span></p>
            </div>
            <div>
                <label class="form-label">供应商</label>
                <div class="flex gap-1.5">
                    <div x-data="supplierCombobox()" x-init="init()" @click.outside="open = false" class="relative flex-1">
                        <input type="hidden" name="supplier_id" :value="selectedId">
                        <button type="button" @click="open = !open" class="form-select w-full text-left flex items-center justify-between">
                            <span :class="selectedId ? 'text-slate-800' : 'text-slate-400'" x-text="selectedLabel || '未选择'"></span>
                            <i data-lucide="chevron-down" class="w-4 h-4 text-slate-400 flex-shrink-0"></i>
                        </button>
                        <div x-show="open" x-cloak x-transition.opacity class="absolute z-50 mt-1 w-full bg-white border border-slate-200 rounded-md shadow-lg max-h-80 overflow-hidden flex flex-col">
                            <div class="p-2 border-b border-slate-100">
                                <div class="relative">
                                    <i data-lucide="search" class="w-3.5 h-3.5 absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-400"></i>
                                    <input type="text" x-model="keyword" x-ref="kwInput" @input="filter()" placeholder="输入关键字筛选" class="form-input pl-7 py-1 text-sm">
                                </div>
                            </div>
                            <div class="overflow-y-auto flex-1" @click.stop>
                                <template x-for="s in filteredSuppliers" :key="s.id">
                                    <div @click="pick(s.id, s.name)" class="px-3 py-1.5 text-sm hover:bg-blue-50 cursor-pointer" :class="selectedId === s.id ? 'bg-blue-50 text-blue-700' : ''">
                                        <span x-text="s.name"></span>
                                    </div>
                                </template>
                                <div x-show="filteredSuppliers.length === 0" class="px-3 py-6 text-center text-sm text-slate-400">
                                    没有匹配的供应商
                                </div>
                            </div>
                        </div>
                    </div>
                    <a href="/supplier.php?action=edit" target="_blank" class="btn btn-secondary px-2.5" title="新增供应商">
                        <i data-lucide="plus" class="w-4 h-4"></i>
                    </a>
                </div>
            </div>
            <div>
                <label class="form-label">分类</label>
                <div x-data="categoryCombobox()" x-init="init()" @click.outside="open = false" class="relative">
                    <input type="hidden" name="category_id" :value="selectedId">
                    <button type="button" @click="open = !open" class="form-select w-full text-left flex items-center justify-between">
                        <span :class="selectedId ? 'text-slate-800' : 'text-slate-400'" x-text="selectedLabel || '未选择'"></span>
                        <i data-lucide="chevron-down" class="w-4 h-4 text-slate-400 flex-shrink-0"></i>
                    </button>
                    <div x-show="open" x-cloak x-transition.opacity class="absolute z-50 mt-1 w-full bg-white border border-slate-200 rounded-md shadow-lg max-h-80 overflow-hidden flex flex-col">
                        <div class="p-2 border-b border-slate-100">
                            <div class="relative">
                                <i data-lucide="search" class="w-3.5 h-3.5 absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-400"></i>
                                <input type="text" x-model="keyword" x-ref="kwInput" @input="filter()" placeholder="输入关键字筛选（跨级匹配）" class="form-input pl-7 py-1 text-sm">
                            </div>
                        </div>
                        <div class="overflow-y-auto flex-1" @click.stop>
                            <template x-for="group in filteredGroups" :key="group.id">
                                <div>
                                    <template x-if="group.children.length > 0">
                                        <div>
                                            <div class="px-3 py-1.5 text-xs font-semibold text-slate-500 bg-slate-50" x-text="group.name"></div>
                                            <template x-for="sub in group.children" :key="sub.id">
                                                <div @click="pick(sub.id, group.name + ' / ' + sub.name)" class="px-3 py-1.5 pl-7 text-sm hover:bg-blue-50 cursor-pointer" :class="selectedId === sub.id ? 'bg-blue-50 text-blue-700' : ''">
                                                    <span x-text="sub.name"></span>
                                                </div>
                                            </template>
                                        </div>
                                    </template>
                                    <template x-if="group.children.length === 0 && matchesParent(group)">
                                        <div @click="pick(group.id, group.name)" class="px-3 py-1.5 text-sm hover:bg-blue-50 cursor-pointer" :class="selectedId === group.id ? 'bg-blue-50 text-blue-700' : ''">
                                            <span x-text="group.name"></span>
                                        </div>
                                    </template>
                                </div>
                            </template>
                            <div x-show="filteredGroups.length === 0" class="px-3 py-6 text-center text-sm text-slate-400">
                                没有匹配的分类
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div>
                <label class="form-label">规格</label>
                <input type="text" name="spec" class="form-input" placeholder="例 500ml/瓶" value="<?= h($product['spec'] ?? '') ?>">
            </div>
            <div>
                <label class="form-label">单位</label>
                <input type="text" name="unit" class="form-input" placeholder="个 / 箱 / 件" value="<?= h($product['unit'] ?? '') ?>">
            </div>
            <div class="md:col-span-2">
                <label class="form-label">备注</label>
                <textarea name="remark" class="form-textarea" rows="2"><?= h($product['remark'] ?? '') ?></textarea>
            </div>
        </div>
    </div>

    <?php if (canViewCost()): ?>
    <div class="card mb-4">
        <div class="card-header">价格信息</div>
        <div class="card-body">
            <!-- 进价 + 其它费用 = 综合进价 -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                <div>
                    <label class="form-label">进价 <span class="text-red-500">*</span></label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm">¥</span>
                        <input type="number" step="0.01" min="0" name="cost_price" id="cost_price" class="form-input pl-7 tabular-nums" required value="<?= h($product['cost_price'] ?? '0.00') ?>" oninput="calcPrices()">
                    </div>
                </div>
                <div>
                    <label class="form-label">其它费用</label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm">¥</span>
                        <input type="number" step="0.01" min="0" name="other_cost" id="other_cost" class="form-input pl-7 tabular-nums" value="<?= h($product['other_cost'] ?? '0.00') ?>" oninput="calcPrices()">
                    </div>
                </div>
                <div>
                    <label class="form-label">综合进价</label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm">¥</span>
                        <input type="text" id="total_cost_display" class="form-input pl-7 bg-slate-50 tabular-nums font-medium" readonly value="<?= h(number_format(($product['cost_price']??0)+($product['other_cost']??0), 2)) ?>">
                    </div>
                    <p class="form-help text-slate-400">= 进价 + 其它费用</p>
                </div>
            </div>
            <div class="mb-4">
                <label class="form-label">费用说明</label>
                <input type="text" name="cost_remark" class="form-input" maxlength="200" placeholder="含运费、管理费等" value="<?= h($product['cost_remark'] ?? '') ?>">
            </div>

            <!-- 指导毛利率 → 指导售价 -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4 pt-4 border-t border-slate-100">
                <div>
                    <label class="form-label">指导毛利率</label>
                    <div class="relative">
                        <input type="number" step="0.01" min="0" max="99" name="guide_margin_rate" id="guide_margin_rate" class="form-input tabular-nums pr-8" value="<?= h($product['guide_margin_rate'] ?? '30.00') ?>" oninput="calcPrices()">
                        <span class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm">%</span>
                    </div>
                </div>
                <div>
                    <label class="form-label">指导售价</label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm">¥</span>
                        <input type="text" id="guide_price" class="form-input pl-7 bg-slate-50 tabular-nums font-medium" readonly value="<?= h($product['guide_price'] ?? '0.00') ?>">
                        <input type="hidden" name="guide_price" id="guide_price_input" value="<?= h($product['guide_price'] ?? '0') ?>">
                    </div>
                    <p class="form-help text-slate-400">= 综合进价 / (1 - 毛利率)</p>
                </div>
                <div></div>
            </div>

            <!-- 最低毛利率 → 最低售价 → 最高折扣 -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                <div>
                    <label class="form-label">最低毛利率</label>
                    <div class="relative">
                        <input type="number" step="0.01" min="0" max="99" name="min_margin_rate" id="min_margin_rate" class="form-input tabular-nums pr-8" value="<?= h($product['min_margin_rate'] ?? '15.00') ?>" oninput="calcPrices()">
                        <span class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm">%</span>
                    </div>
                </div>
                <div>
                    <label class="form-label">最低售价</label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm">¥</span>
                        <input type="text" id="min_price_display" class="form-input pl-7 bg-slate-50 tabular-nums" readonly value="<?= h(number_format(($product['guide_price']??0)*(float)($product['min_discount'] ?? 1.00), 2)) ?>">
                    </div>
                    <p class="form-help text-slate-400">= 综合进价 / (1 - 最低毛利率)</p>
                </div>
                <div>
                    <label class="form-label">最高折扣</label>
                    <div class="relative">
                        <input type="text" id="min_discount_display" class="form-input pr-9 bg-slate-50 tabular-nums" readonly value="<?= $product['min_discount'] ? number_format((float)$product['min_discount']*100, 0).'%' : '-' ?>">
                        <span class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm">折</span>
                    </div>
                    <p class="form-help text-slate-400">= (1-指导毛利率) / (1-最低毛利率)</p>
                </div>
            </div>

            <?php if ($isEdit): ?>
                <div class="pt-3 border-t border-slate-100">
                    <label class="form-label">价格变更原因（可选）</label>
                    <input type="text" name="price_remark" class="form-input" placeholder="例：原料涨价 / 活动促销">
                    <p class="form-help">仅当进价/售价/折扣发生变化时才会写入历史记录</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>


    <div class="card mb-4">
        <div class="card-header">状态</div>
        <div class="card-body">
            <label class="inline-flex items-center cursor-pointer">
                <input type="checkbox" name="status" value="1" class="rounded text-blue-600" <?= !$isEdit || (int)($product['status'] ?? 1) === 1 ? 'checked' : '' ?>>
                <span class="ml-2 text-sm">上架销售</span>
            </label>
            <p class="form-help mt-1">不勾选 = 下架状态</p>
        </div>
    </div>

    <div class="flex justify-end gap-2 mb-8">
        <a href="/products.php" class="btn btn-secondary">返回</a>
        <?php if (canViewCost()): ?>
        <button type="submit" class="btn btn-primary">
            <i data-lucide="save" class="w-4 h-4 mr-1.5"></i><?= $isEdit ? '保存修改' : '创建产品' ?>
        </button>
        <?php else: ?>
        <span class="text-sm text-slate-400">您没有编辑权限</span>
        <?php endif; ?>
    </div>
</form>

<?php if ($isEdit): ?>
<!-- 资料文件 -->
<div class="max-w-3xl mb-4">
    <div class="card">
        <div class="card-header flex items-center justify-between">
            <span>产品资料（图片 / PDF / Word / Excel）</span>
            <span class="text-xs text-slate-400 font-normal">最多 5MB / PDF 10MB</span>
        </div>
        <div class="card-body">
            <div class="upload-area mb-4" id="uploadArea">
                <input type="file" id="fileInput" class="hidden" accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx">
                <i data-lucide="upload-cloud" class="w-8 h-8 mx-auto mb-2"></i>
                <p>点击或拖拽文件到此处上传</p>
                <p class="text-xs text-slate-400 mt-1">支持 图片 / PDF / Word / Excel / PPT</p>
            </div>

            <?php if (empty($files)): ?>
                <p class="text-center text-slate-400 text-sm py-4">暂无文件</p>
            <?php else: ?>
                <div class="space-y-2">
                    <?php foreach ($files as $f): ?>
                        <div class="flex items-center justify-between p-3 border border-slate-200 rounded-md hover:bg-slate-50">
                            <div class="flex items-center gap-3 min-w-0">
                                <div class="w-9 h-9 rounded bg-slate-100 flex items-center justify-center flex-shrink-0">
                                    <i data-lucide="<?= $f['file_type'] === 'image' ? 'image' : ($f['file_type'] === 'pdf' ? 'file-text' : ($f['file_type'] === 'word' ? 'file-text' : 'file')) ?>" class="w-4 h-4 text-slate-500"></i>
                                </div>
                                <div class="min-w-0">
                                    <div class="text-sm font-medium text-slate-700 truncate"><?= h($f['original_name']) ?></div>
                                    <div class="text-xs text-slate-400"><?= h($f['file_type']) ?> · <?= formatFileSize((int)$f['file_size']) ?> · <?= h($f['uploaded_at']) ?></div>
                                </div>
                            </div>
                            <div class="flex gap-1 flex-shrink-0">
                                <?php if (in_array($f['file_type'], ['image', 'pdf'])): ?>
                                    <a href="/uploads/<?= h($f['stored_name']) ?>" target="_blank" class="btn btn-ghost btn-sm" title="预览">
                                        <i data-lucide="eye" class="w-3.5 h-3.5"></i>
                                    </a>
                                <?php endif; ?>
                                <a href="/uploads/<?= h($f['stored_name']) ?>" download="<?= h($f['original_name']) ?>" class="btn btn-ghost btn-sm" title="下载">
                                    <i data-lucide="download" class="w-3.5 h-3.5"></i>
                                </a>
                                <button class="btn btn-ghost btn-sm" onclick="deleteFile(<?= $f['id'] ?>, '<?= h(addslashes($f['original_name'])) ?>')" title="删除">
                                    <i data-lucide="trash-2" class="w-3.5 h-3.5 text-red-500"></i>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- 价格历史 -->
<div class="max-w-3xl mb-8">
    <div class="card">
        <div class="card-header">价格变更历史</div>
        <?php if (empty($history)): ?>
            <div class="card-body text-center text-slate-400 py-8 text-sm">暂无价格变更记录</div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>时间</th>
                            <th>字段</th>
                            <th class="text-right">原值</th>
                            <th class="text-right">新值</th>
                            <th>操作人</th>
                            <th>备注</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $fieldLabels = ['cost_price' => '进价', 'guide_price' => '售价', 'min_discount' => '折扣'];
                        foreach ($history as $h):
                        ?>
                            <tr>
                                <td class="text-slate-500 text-xs whitespace-nowrap"><?= h($h['changed_at']) ?></td>
                                <td><?= h($fieldLabels[$h['field']] ?? $h['field']) ?></td>
                                <td class="text-right tabular-nums text-slate-500"><?= h($h['old_value']) ?></td>
                                <td class="text-right tabular-nums text-blue-600"><?= h($h['new_value']) ?></td>
                                <td class="text-slate-600"><?= h($h['user_name'] ?? '-') ?></td>
                                <td class="text-slate-500 text-xs"><?= h($h['remark'] ?? '-') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<script>
// 供应商 combobox（平铺）
function supplierCombobox() {
    return {
        allSuppliers: <?= json_encode($allSuppliers, JSON_UNESCAPED_UNICODE) ?>,
        open: false,
        keyword: '',
        selectedId: <?= $currentSupplierId ?>,
        selectedLabel: <?= json_encode($currentSupplierLabel, JSON_UNESCAPED_UNICODE) ?>,
        filteredSuppliers: [],
        init() {
            this.filter();
            this.$watch('open', (v) => {
                if (v) {
                    this.$nextTick(() => this.$refs.kwInput?.focus());
                } else {
                    this.keyword = '';
                    this.filter();
                }
            });
        },
        pick(id, label) {
            this.selectedId = id;
            this.selectedLabel = label;
            this.open = false;
            // 供应商改变不触发 SKU 生成（SKU 只与分类相关）
        },
        filter() {
            const kw = this.keyword.trim().toLowerCase();
            if (kw === '') {
                this.filteredSuppliers = this.allSuppliers.slice();
                return;
            }
            this.filteredSuppliers = this.allSuppliers.filter(s =>
                s.name.toLowerCase().includes(kw)
            );
        }
    };
}

// 分类树形 combobox
function categoryCombobox() {
    return {
        allGroups: <?= json_encode($categories, JSON_UNESCAPED_UNICODE) ?>,
        open: false,
        keyword: '',
        selectedId: <?= $currentCategoryId ?>,
        selectedLabel: <?= json_encode($currentCategoryLabel, JSON_UNESCAPED_UNICODE) ?>,
        originalCategoryId: <?= $currentCategoryId ?>, // 保存原始分类ID
        init() {
            this.filter();
            this.$watch('open', (v) => {
                if (v) {
                    this.$nextTick(() => this.$refs.kwInput?.focus());
                } else {
                    this.keyword = '';
                    this.filter();
                }
            });
        },
        pick(id, label) {
            // 检查分类是否发生变化
            if (this.originalCategoryId > 0 && id !== this.originalCategoryId) {
                if (!confirm('修改分类会重新生成产品 SKU，确定继续吗？')) {
                    return;
                }
            }
            this.selectedId = id;
            this.selectedLabel = label;
            this.open = false;
            // 选择分类后自动生成 SKU
            this.$nextTick(() => generateSkuFromCategory(id));
        },
        filteredGroups: [],
        filter() {
            const kw = this.keyword.trim().toLowerCase();
            if (kw === '') {
                this.filteredGroups = JSON.parse(JSON.stringify(this.allGroups));
                return;
            }
            // 关键字匹配：一级或二级任一命中即显示
            this.filteredGroups = this.allGroups
                .map(g => {
                    const parentHit = g.name.toLowerCase().includes(kw);
                    const childHits = (g.children || []).filter(c => c.name.toLowerCase().includes(kw));
                    if (parentHit) {
                        return JSON.parse(JSON.stringify(g));
                    }
                    if (childHits.length > 0) {
                        return { ...g, children: childHits };
                    }
                    return null;
                })
                .filter(g => g !== null);
        },
        matchesParent(g) {
            const kw = this.keyword.trim().toLowerCase();
            return kw === '' || g.name.toLowerCase().includes(kw);
        }
    };
}
window.categoryCombobox = categoryCombobox;
window.supplierCombobox = supplierCombobox;

// 实时毛利 + 实际售价 + 实际毛利率（双向计算）
// 价格自动计算（毛利率模式）
function calcPrices() {
    const cost = parseFloat(document.getElementById('cost_price').value) || 0;
    const other = parseFloat(document.getElementById('other_cost').value) || 0;
    const totalCost = cost + other;
    const gm = parseFloat(document.getElementById('guide_margin_rate').value) || 0;
    const mm = parseFloat(document.getElementById('min_margin_rate').value) || 0;
    // 综合进价
    document.getElementById('total_cost_display').value = totalCost.toFixed(2);
    // 指导售价 = 综合进价 / (1-毛利率)
    const gp = gm < 100 ? totalCost / (1 - gm / 100) : totalCost;
    document.getElementById('guide_price').value = gp.toFixed(2);
    const guideInput = document.getElementById('guide_price_input');
    if (guideInput) guideInput.value = gp.toFixed(2);
    // 最低售价 = 综合进价 / (1-最低毛利率)
    const mp = mm < 100 ? totalCost / (1 - mm / 100) : totalCost;
    document.getElementById('min_price_display').value = mp.toFixed(2);
    // 最高折扣 = (1-指导毛利率)/(1-最低毛利率)
    const disc = gm < 100 && mm < 100 ? ((1-gm/100)/(1-mm/100)*100) : 0;
    document.getElementById('min_discount_display').value = disc > 0 ? disc.toFixed(0) + '%' : '-';
}
document.getElementById('cost_price').addEventListener('input', calcPrices);
document.getElementById('guide_margin_rate').addEventListener('input', calcPrices);
document.getElementById('min_margin_rate').addEventListener('input', calcPrices);
document.getElementById('other_cost').addEventListener('input', calcPrices);
calcPrices();

// SKU 自动生成
let lastModifiedField = ''; // 记录最后修改的字段

function calcMarginLive(triggerField = '') {
    if (isCalculating) return;
    isCalculating = true;
    
    try {
        const cost = parseFloat(document.getElementById('cost_price').value) || 0;
        const priceEl = document.getElementById('guide_price');
        const discEl = document.getElementById('min_discount');
        const marginInput = document.getElementById('liveMarginInput');
        const actualMarginInput = document.getElementById('liveActualMarginInput');
        
        let price = parseFloat(priceEl.value) || 0;
        let discRaw = parseFloat(discEl.value) || 0;
        const disc = Math.min(1, Math.max(0.01, discRaw));
        
        // 折扣百分号标签
        document.getElementById('discountPercentLabel').textContent = (disc * 100).toFixed(0) + '%';
        
        const actualPriceEl = document.getElementById('liveActualPrice');
        
        // 根据最后修改的字段，决定计算方向
        if (triggerField === 'margin' && marginInput.value !== '' && cost > 0) {
            // 用户修改了标准毛利率 → 计算售价
            const margin = parseFloat(marginInput.value) || 0;
            if (margin >= 0 && margin < 100) {
                price = cost / (1 - margin / 100);
                priceEl.value = price.toFixed(2);
            }
        } else if (triggerField === 'actualMargin' && actualMarginInput.value !== '' && cost > 0 && price > 0) {
            // 用户修改了最低实际毛利率 → 计算折扣
            const actualMargin = parseFloat(actualMarginInput.value) || 0;
            if (actualMargin >= 0 && actualMargin < 100) {
                const actualPrice = cost / (1 - actualMargin / 100);
                discRaw = actualPrice / price;
                discRaw = Math.min(1, Math.max(0.01, discRaw));
                discEl.value = discRaw.toFixed(2);
                document.getElementById('discountPercentLabel').textContent = (discRaw * 100).toFixed(0) + '%';
            }
        }
        
        // 重新获取最新值
        price = parseFloat(priceEl.value) || 0;
        discRaw = parseFloat(discEl.value) || 0;
        const discFinal = Math.min(1, Math.max(0.01, discRaw));
        
        if (price <= 0) {
            if (marginInput) marginInput.value = '';
            actualPriceEl.textContent = '-';
            actualPriceEl.className = 'font-semibold tabular-nums text-slate-400';
            if (actualMarginInput) actualMarginInput.value = '';
            return;
        }
        
        // 标准毛利率（显示值，不触发计算）
        const m = (price - cost) / price * 100;
        if (triggerField !== 'margin' && marginInput) {
            marginInput.value = m.toFixed(2);
            // 根据毛利率值设置颜色
            if (m < 10) {
                marginInput.className = 'form-input py-1 pl-2 pr-6 w-24 tabular-nums text-sm text-red-600 font-bold';
            } else if (m < 20) {
                marginInput.className = 'form-input py-1 pl-2 pr-6 w-24 tabular-nums text-sm text-yellow-600';
            } else {
                marginInput.className = 'form-input py-1 pl-2 pr-6 w-24 tabular-nums text-sm text-green-600';
            }
        }
        
        // 实际售价
        const actualPrice = price * discFinal;
        actualPriceEl.textContent = '¥' + actualPrice.toFixed(2);
        actualPriceEl.className = 'font-semibold tabular-nums text-slate-700';
        
        // 实际毛利率（显示值，不触发计算）
        if (actualPrice > 0 && discFinal > 0) {
            const am = (actualPrice - cost) / actualPrice * 100;
            if (triggerField !== 'actualMargin' && actualMarginInput) {
                actualMarginInput.value = am.toFixed(2);
                // 根据毛利率值设置颜色
                if (am < 10) {
                    actualMarginInput.className = 'form-input py-1 pl-2 pr-6 w-24 tabular-nums text-sm text-red-600 font-bold';
                } else if (am < 20) {
                    actualMarginInput.className = 'form-input py-1 pl-2 pr-6 w-24 tabular-nums text-sm text-yellow-600';
                } else {
                    actualMarginInput.className = 'form-input py-1 pl-2 pr-6 w-24 tabular-nums text-sm text-green-600';
                }
            }
        } else {
            if (actualMarginInput) actualMarginInput.value = '';
        }
    } finally {
        isCalculating = false;
    }
}

// 实时毛利率：input 元素在脚本之前已存在，可直接绑定
const _costEl = document.getElementById('cost_price');
const _priceEl = document.getElementById('guide_price');
const _discEl = document.getElementById('min_discount');
const _marginInputEl = document.getElementById('liveMarginInput');
const _actualMarginInputEl = document.getElementById('liveActualMarginInput');

if (_costEl) {
    _costEl.addEventListener('input', () => calcMarginLive('cost'));
    _costEl.addEventListener('keydown', (e) => { if (e.key === 'Enter') e.preventDefault(); });
}
if (_priceEl) {
    _priceEl.addEventListener('input', () => calcMarginLive('price'));
    _priceEl.addEventListener('keydown', (e) => { if (e.key === 'Enter') e.preventDefault(); });
}
if (_discEl) {
    _discEl.addEventListener('input', () => calcMarginLive('discount'));
    _discEl.addEventListener('keydown', (e) => { if (e.key === 'Enter') e.preventDefault(); });
}
if (_marginInputEl) {
    _marginInputEl.addEventListener('input', () => calcMarginLive('margin'));
    _marginInputEl.addEventListener('keydown', (e) => { if (e.key === 'Enter') e.preventDefault(); });
}
if (_actualMarginInputEl) {
    _actualMarginInputEl.addEventListener('input', () => calcMarginLive('actualMargin'));
    _actualMarginInputEl.addEventListener('keydown', (e) => { if (e.key === 'Enter') e.preventDefault(); });
}
calcMarginLive();

// 分类 prefix 索引（用于 SKU 合规检查）
const catPrefixMap = <?= json_encode(array_reduce($allCategories, function($m, $c) {
    $m[(string)$c['id']] = ['parent_sort_id' => (int)$c['parent_sort_id'], 'sub_id' => (int)$c['sub_id']];
    return $m;
}, [])) ?>;

// SKU 自动生成：监听手动编辑 + 生成按钮
const skuInput = document.getElementById('sku');
const generateSkuBtn = document.getElementById('generateSkuBtn');
if (skuInput) {
    // 监听手动编辑，标记为手动修改
    skuInput.addEventListener('input', function() {
        this.dataset.manualEdit = 'true';
    });
}
// 全局 SKU 生成函数
async function generateSkuFromCategory(categoryId) {
    if (!categoryId || categoryId <= 0) return;
    const skuIn = document.getElementById('sku');
    if (skuIn && skuIn.dataset.manualEdit === 'true') return; // 用户手动改过就不覆盖
    try {
        const fd = new FormData();
        fd.append('category_id', categoryId);
        fd.append('_csrf', document.querySelector('input[name="_csrf"]').value);
        const r = await fetch('/api/generate_sku.php', { method: 'POST', body: fd });
        const d = await r.json();
        if (d.success && skuIn) {
            skuIn.value = d.sku;
            skuIn.dataset.manualEdit = 'false';
            checkSkuFormat();
        } else if (d.error) {
            console.warn('SKU 生成:', d.error);
        }
    } catch (e) {
        console.warn('SKU 请求失败:', e);
    }
}

if (generateSkuBtn) {
    generateSkuBtn.addEventListener('click', function() {
        const categoryIdInput = document.querySelector('input[name="category_id"]');
        if (!categoryIdInput || !categoryIdInput.value) {
            alert('请先选择分类');
            return;
        }
        // 强制生成（允许覆盖手动输入）
        const skuIn = document.getElementById('sku');
        if (skuIn) skuIn.dataset.manualEdit = 'false';
        generateSkuFromCategory(parseInt(categoryIdInput.value));
    });
}

// SKU 合规检查
function checkSkuFormat() {
    const skuVal = skuInput?.value;
    const catOpt = document.querySelector('input[name="category_id"]');
    const statusEl = document.getElementById('skuStatus');
    if (!skuVal || !catOpt?.value || !statusEl) return;
    const prefixData = catPrefixMap[catOpt.value];
    if (prefixData) {
        const expectedPrefix = String(prefixData.parent_sort_id).padStart(2,'0') 
            + String(prefixData.sub_id).padStart(2,'0');
        if (skuVal.length === 7 && skuVal.startsWith(expectedPrefix)) {
            statusEl.innerHTML = '<span class="text-emerald-500">✅ 合规</span>';
        } else {
            statusEl.innerHTML = '<span class="text-red-500">⚠️ 不符合规则（期望前缀：'+expectedPrefix+'XX），请点"生成"</span>';
        }
    }
}
document.querySelector('input[name="category_id"]')?.addEventListener('change', checkSkuFormat);
skuInput?.addEventListener('input', checkSkuFormat);
checkSkuFormat();

// 文件上传：必须等 app.js 加载完（app.js 在 footer.php 的 <script src> 引入）
// 所以包装在 DOMContentLoaded 里，等同步脚本执行完
// 未保存警告
let formDirty = false;
const form = document.getElementById('productForm');
if (form) {
    form.addEventListener('input', () => { formDirty = true; });
    form.addEventListener('change', () => { formDirty = true; });
    form.addEventListener('submit', () => { formDirty = false; });
}

// 拦截返回列表
const backBtn = document.getElementById('backToList');
if (backBtn) {
    backBtn.addEventListener('click', function(e) {
        if (!formDirty) return; // clean, allow
        e.preventDefault();
        const overlay = document.createElement('div');
        overlay.style.cssText = 'position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.4);display:flex;align-items:center;justify-content:center';
        overlay.innerHTML = '<div class="bg-white rounded-lg shadow-xl p-6 max-w-sm w-full mx-4 text-center"><p class="text-sm text-slate-700 mb-4">有未保存的修改</p><div class="flex gap-3 justify-center"><button id="leaveSave" class="btn btn-primary text-sm px-4">保存并离开</button><button id="leaveDiscard" class="btn btn-secondary text-sm px-4">不保存</button></div></div>';
        document.body.appendChild(overlay);
        overlay.querySelector('#leaveSave').onclick = () => { document.body.removeChild(overlay); form.requestSubmit(); };
        overlay.querySelector('#leaveDiscard').onclick = () => { document.body.removeChild(overlay); formDirty = false; location.href = '/products.php'; };
        overlay.onclick = (ev) => { if (ev.target === overlay) { document.body.removeChild(overlay); } };
    });
}

// 拦截所有侧栏/导航跳转
window.addEventListener('beforeunload', function(e) {
    if (formDirty) {
        e.preventDefault();
        e.returnValue = '有未保存的修改，确定离开吗？';
        return e.returnValue;
    }
});

// 也拦截 .nav-link 的点击（左侧菜单）
document.querySelectorAll('a[href]:not([target]):not([download])').forEach(a => {
    a.addEventListener('click', function(e) {
        if (!formDirty) return;
        const href = this.getAttribute('href');
        if (!href || href === '#' || href.startsWith('javascript:') || href.startsWith('#') || this === backBtn) return;
        e.preventDefault();
        const overlay = document.createElement('div');
        overlay.style.cssText = 'position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.4);display:flex;align-items:center;justify-content:center';
        overlay.innerHTML = `<div class="bg-white rounded-lg shadow-xl p-6 max-w-sm w-full mx-4 text-center"><p class="text-sm text-slate-700 mb-4">有未保存的修改</p><div class="flex gap-3 justify-center"><button id="leaveSave" class="btn btn-primary text-sm px-4">保存并离开</button><button id="leaveDiscard" class="btn btn-secondary text-sm px-4">不保存</button></div></div>`;
        document.body.appendChild(overlay);
        overlay.querySelector('#leaveSave').onclick = () => { document.body.removeChild(overlay); form.requestSubmit(); };
        overlay.querySelector('#leaveDiscard').onclick = () => { document.body.removeChild(overlay); formDirty = false; location.href = href; };
        overlay.onclick = (ev) => { if (ev.target === overlay) { document.body.removeChild(overlay); } };
    });
});

// 文件上传
document.addEventListener('DOMContentLoaded', () => {
    const fileInput = document.getElementById('fileInput');
    const productId = <?= $id ?>;

    if (uploadArea && typeof initUpload === 'function') {
        initUpload(uploadArea, fileInput, async (file) => {
            if (file.size > 10 * 1024 * 1024) {
                alert('文件太大（' + (file.size / 1024 / 1024).toFixed(1) + 'MB），最大 10MB');
                return;
            }
            const fd = new FormData();
            fd.append('file', file);
            fd.append('product_id', productId);
            fd.append('_csrf', '<?= h(csrfToken()) ?>');
            uploadArea.innerHTML = '<p class="text-blue-600">上传中...</p>';
            try {
                const r = await fetch('/api/file_upload.php', { method: 'POST', body: fd });
                const d = await r.json();
                if (d.ok) {
                    location.reload();
                } else {
                    alert('上传失败：' + (d.message || '未知错误'));
                    location.reload();
                }
            } catch (e) {
                alert('上传失败：' + e.message);
                location.reload();
            }
        });
    }
});

function deleteFile(fileId, name) {
    if (!confirm('确定要删除文件「' + name + '」吗？')) return;
    const fd = new FormData();
    fd.append('id', fileId);
    fd.append('_csrf', '<?= h(csrfToken()) ?>');
    fetch('/api/file_delete.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (d.ok) location.reload();
            else alert('删除失败：' + (d.message || '未知错误'));
        })
        .catch(e => alert('删除失败：' + e.message));
}
</script>

<?php require __DIR__ . '/includes/views/footer.php'; ?>
