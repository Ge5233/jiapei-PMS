<?php
/**
 * 产品编辑/新增
 */
define('PMS_ENTRY', true);
require_once __DIR__ . '/includes/bootstrap.php';
if (!PMS_INSTALLED) { header('Location: /install.php'); exit; }
requireLogin();

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
    <a href="/products.php" class="text-sm text-slate-500 hover:text-slate-700 flex items-center w-fit">
        <i data-lucide="arrow-left" class="w-4 h-4 mr-1"></i>返回列表
    </a>
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
                <p class="form-help">格式：父分类ID(2位) + 子分类ID(2位) + 序号(3位)，选择分类后自动生成</p>
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

    <div class="card mb-4">
        <div class="card-header">价格信息</div>
        <div class="card-body">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                <div>
                    <label class="form-label">综合进价 <span class="text-red-500">*</span></label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm">¥</span>
                        <input type="number" step="0.01" min="0" name="cost_price" id="cost_price" class="form-input pl-7 tabular-nums" required value="<?= h($product['cost_price'] ?? '0.00') ?>">
                    </div>
                    <p class="form-help">含运费、税费等总成本</p>
                </div>
                <div>
                    <label class="form-label">指导售价 <span class="text-red-500">*</span></label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm">¥</span>
                        <input type="number" step="0.01" min="0" name="guide_price" id="guide_price" class="form-input pl-7 tabular-nums" required value="<?= h($product['guide_price'] ?? '0.00') ?>">
                    </div>
                </div>
                <div>
                    <label class="form-label">最高允许折扣 <span class="text-red-500">*</span></label>
                    <div class="relative">
                        <input type="number" step="0.01" min="0.01" max="1" name="min_discount" id="min_discount" class="form-input pr-12 tabular-nums" required value="<?= h($product['min_discount'] ?? '1.00') ?>">
                        <span class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-500 text-sm" id="discountPercentLabel">100%</span>
                    </div>
                    <p class="form-help">0.01 ~ 1.00。例 0.85 = 最低 8.5 折</p>
                </div>
            </div>

            <div class="bg-slate-50 border border-slate-200 rounded-md p-3 text-sm">
                <div class="flex flex-wrap items-center gap-x-6 gap-y-2">
                    <div class="flex items-center gap-2">
                        <span class="text-slate-600">标准毛利率：</span>
                        <div class="relative">
                            <input type="number" step="0.01" min="0" max="100" id="liveMarginInput" class="form-input py-1 pl-2 pr-6 w-24 tabular-nums text-sm" placeholder="-">
                            <span class="absolute right-2 top-1/2 -translate-y-1/2 text-slate-500 text-xs">%</span>
                        </div>
                        <span class="text-slate-400 text-xs">（填毛利率自动算售价）</span>
                    </div>
                    <span class="text-slate-300">|</span>
                    <span class="text-slate-600">最低实际售价：<span class="font-semibold tabular-nums" id="liveActualPrice">-</span></span>
                    <span class="text-slate-300">|</span>
                    <div class="flex items-center gap-2">
                        <span class="text-slate-600">最低实际毛利率：</span>
                        <div class="relative">
                            <input type="number" step="0.01" min="0" max="100" id="liveActualMarginInput" class="form-input py-1 pl-2 pr-6 w-24 tabular-nums text-sm" placeholder="-">
                            <span class="absolute right-2 top-1/2 -translate-y-1/2 text-slate-500 text-xs">%</span>
                        </div>
                        <span class="text-slate-400 text-xs">（填毛利率自动算折扣）</span>
                    </div>
                </div>
            </div>

            <?php if ($isEdit): ?>
                <div class="mt-3">
                    <label class="form-label">价格变更原因（可选）</label>
                    <input type="text" name="price_remark" class="form-input" placeholder="例：原料涨价 / 活动促销">
                    <p class="form-help">仅当进价/售价/折扣发生变化时才会写入历史记录</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

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
        <a href="/products.php" class="btn btn-secondary">取消</a>
        <button type="submit" class="btn btn-primary">
            <i data-lucide="save" class="w-4 h-4 mr-1.5"></i><?= $isEdit ? '保存修改' : '创建产品' ?>
        </button>
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
            // 选择分类后自动生成 SKU
            this.generateSku(id);
        },
        async generateSku(categoryId) {
            if (!categoryId || categoryId <= 0) return;
            
            // 如果 SKU 已有值且用户手动修改过，不自动覆盖
            const skuInput = document.getElementById('sku');
            if (skuInput && skuInput.value && skuInput.dataset.manualEdit === 'true') {
                return;
            }
            
            try {
                const formData = new FormData();
                formData.append('category_id', categoryId);
                formData.append('_csrf', document.querySelector('input[name="_csrf"]').value);
                
                const response = await fetch('/api/generate_sku.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                if (data.success && skuInput) {
                    skuInput.value = data.sku;
                    skuInput.dataset.manualEdit = 'false';
                } else if (data.error) {
                    console.warn('SKU 生成失败:', data.error);
                }
            } catch (error) {
                console.error('SKU 生成请求失败:', error);
            }
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
let isCalculating = false; // 防止循环触发
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

// SKU 自动生成：监听手动编辑 + 生成按钮
const skuInput = document.getElementById('sku');
const generateSkuBtn = document.getElementById('generateSkuBtn');
if (skuInput) {
    // 监听手动编辑，标记为手动修改
    skuInput.addEventListener('input', function() {
        this.dataset.manualEdit = 'true';
    });
}
if (generateSkuBtn) {
    generateSkuBtn.addEventListener('click', async function() {
        // 从隐藏 input 获取分类 ID
        const categoryIdInput = document.querySelector('input[name="category_id"]');
        if (!categoryIdInput || !categoryIdInput.value) {
            alert('请先选择分类');
            return;
        }
        
        const categoryId = parseInt(categoryIdInput.value);
        if (categoryId <= 0) {
            alert('请先选择分类');
            return;
        }
        
        // 调用 API 生成 SKU
        try {
            const formData = new FormData();
            formData.append('category_id', categoryId);
            formData.append('_csrf', document.querySelector('input[name="_csrf"]').value);
            
            const response = await fetch('/api/generate_sku.php', {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            if (data.success && skuInput) {
                skuInput.value = data.sku;
                skuInput.dataset.manualEdit = 'false';
            } else if (data.error) {
                alert('SKU 生成失败: ' + data.error);
            }
        } catch (error) {
            alert('SKU 生成请求失败: ' + error.message);
        }
    });
}

// 文件上传：必须等 app.js 加载完（app.js 在 footer.php 的 <script src> 引入）
// 所以包装在 DOMContentLoaded 里，等同步脚本执行完
document.addEventListener('DOMContentLoaded', function() {
    const uploadArea = document.getElementById('uploadArea');
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
