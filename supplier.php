<?php
/**
 * 供应商管理
 */
define('PMS_ENTRY', true);
require_once __DIR__ . '/includes/bootstrap.php';
if (!PMS_INSTALLED) { header('Location: /install.php'); exit; }
requireLogin();
requireCostView();

$action = $_GET['action'] ?? 'list';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$supplier = null;
if ($id > 0) {
    $supplier = Supplier::find($id);
    if (!$supplier) {
        flash('error', '供应商不存在');
        header('Location: /supplier.php');
        exit;
    }
}

$keyword = trim($_GET['q'] ?? '');
$status = $_GET['status'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$pageSize = 20;

$result = Supplier::list([
    'keyword' => $keyword,
    'status' => $status !== '' ? (int)$status : null,
    'page' => $page,
    'page_size' => $pageSize,
]);

$rows = $result['rows'];
$total = $result['total'];
$totalPages = max(1, (int)ceil($total / $pageSize));

function buildSupplierPageUrl(int $page): string {
    $params = $_GET;
    $params['page'] = $page;
    return '?' . http_build_query($params);
}

$pageTitle = $action === 'edit' ? ($id > 0 ? '编辑供应商' : '新增供应商') : '供应商管理';
$activeMenu = 'suppliers';
require __DIR__ . '/includes/views/header.php';
?>

<?php if ($action === 'edit'): ?>
    <!-- 新增/编辑表单 -->
    <div class="mb-4">
        <a href="/supplier.php" class="text-sm text-slate-500 hover:text-slate-700 flex items-center w-fit">
            <i data-lucide="arrow-left" class="w-4 h-4 mr-1"></i>返回列表
        </a>
    </div>

    <form method="post" action="/api/supplier_save.php" class="max-w-3xl">
        <?= csrfField() ?>
        <input type="hidden" name="id" value="<?= $id ?>">

        <div class="card mb-4">
            <div class="card-header">基本信息</div>
            <div class="card-body grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="md:col-span-2">
                    <label class="form-label">供应商名称 <span class="text-red-500">*</span></label>
                    <input type="text" name="name" class="form-input" required value="<?= h($supplier['name'] ?? '') ?>" placeholder="公司全称">
                </div>
                <div>
                    <label class="form-label">联系人</label>
                    <input type="text" name="contact" class="form-input" value="<?= h($supplier['contact'] ?? '') ?>" placeholder="销售/采购对接人">
                </div>
                <div>
                    <label class="form-label">联系电话</label>
                    <input type="text" name="phone" class="form-input" value="<?= h($supplier['phone'] ?? '') ?>" placeholder="手机或座机">
                </div>
                <div>
                    <label class="form-label">邮箱</label>
                    <input type="email" name="email" class="form-input" value="<?= h($supplier['email'] ?? '') ?>" placeholder="报价单接收邮箱">
                </div>
                <div>
                    <label class="form-label">状态</label>
                    <select name="status" class="form-select">
                        <option value="1" <?= !$supplier || (int)($supplier['status'] ?? 1) === 1 ? 'selected' : '' ?>>启用</option>
                        <option value="0" <?= $supplier && (int)$supplier['status'] === 0 ? 'selected' : '' ?>>停用</option>
                    </select>
                </div>
                <div class="md:col-span-2">
                    <label class="form-label">公司地址</label>
                    <input type="text" name="address" class="form-input" value="<?= h($supplier['address'] ?? '') ?>" placeholder="完整地址">
                </div>
                <div class="md:col-span-2">
                    <label class="form-label">备注</label>
                    <textarea name="remark" class="form-textarea" rows="2"><?= h($supplier['remark'] ?? '') ?></textarea>
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header">财务 / 资质信息（对账用）</div>
            <div class="card-body grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="form-label">开户行</label>
                    <input type="text" name="bank_name" class="form-input" value="<?= h($supplier['bank_name'] ?? '') ?>" placeholder="例 中国工商银行 XX 分行">
                </div>
                <div>
                    <label class="form-label">银行账号</label>
                    <input type="text" name="bank_account" class="form-input tabular-nums" value="<?= h($supplier['bank_account'] ?? '') ?>" placeholder="对公账号">
                </div>
                <div>
                    <label class="form-label">营业执照号</label>
                    <input type="text" name="license_no" class="form-input tabular-nums" value="<?= h($supplier['license_no'] ?? '') ?>" placeholder="例 91310115XXXXXXXXXX">
                </div>
            </div>
        </div>

        <div class="flex justify-end gap-2 mb-8">
            <a href="/supplier.php" class="btn btn-secondary">取消</a>
            <button type="submit" class="btn btn-primary">
                <i data-lucide="save" class="w-4 h-4 mr-1.5"></i><?= $id > 0 ? '保存修改' : '创建供应商' ?>
            </button>
        </div>
    </form>

<?php else: ?>
    <!-- 列表 -->
    <div class="flex items-center justify-between mb-4">
        <div>
            <h2 class="text-lg font-medium text-slate-800">供应商列表</h2>
            <p class="text-sm text-slate-500 mt-0.5">共 <?= $total ?> 个供应商</p>
        </div>
        <a href="/supplier.php?action=edit" class="btn btn-primary">
            <i data-lucide="plus" class="w-4 h-4 mr-1.5"></i>新增供应商
        </a>
    </div>

    <!-- 筛选 -->
    <div class="card p-4 mb-4">
        <form method="get" class="flex flex-wrap gap-3 items-end">
            <div class="flex-1 min-w-[200px]">
                <label class="form-label">搜索</label>
                <input type="text" name="q" class="form-input" placeholder="供应商名称 / 联系人 / 电话" value="<?= h($keyword) ?>">
            </div>
            <div class="min-w-[120px]">
                <label class="form-label">状态</label>
                <select name="status" class="form-select">
                    <option value="">全部</option>
                    <option value="1" <?= $status === '1' ? 'selected' : '' ?>>启用</option>
                    <option value="0" <?= $status === '0' ? 'selected' : '' ?>>停用</option>
                </select>
            </div>
            <div class="flex gap-2">
                <button type="submit" class="btn btn-primary">
                    <i data-lucide="search" class="w-4 h-4 mr-1.5"></i>查询
                </button>
                <a href="/supplier.php" class="btn btn-secondary">重置</a>
            </div>
        </form>
    </div>

    <div class="card">
        <?php if (empty($rows)): ?>
            <div class="card-body text-center text-slate-400 py-16">
                <i data-lucide="truck" class="w-12 h-12 mx-auto mb-3"></i>
                <p>暂无供应商</p>
                <a href="/supplier.php?action=edit" class="btn btn-primary mt-4 inline-flex">
                    <i data-lucide="plus" class="w-4 h-4 mr-1.5"></i>新增供应商
                </a>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>名称</th>
                            <th>联系人</th>
                            <th>电话</th>
                            <th>邮箱</th>
                            <th class="text-right">产品数</th>
                            <th class="text-center">状态</th>
                            <th class="text-right">操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $s): ?>
                            <tr>
                                <td>
                                    <a href="/supplier.php?action=edit&id=<?= $s['id'] ?>" class="text-blue-600 hover:underline font-medium">
                                        <?= h($s['name']) ?>
                                    </a>
                                </td>
                                <td><?= h($s['contact'] ?? '-') ?></td>
                                <td class="tabular-nums text-slate-600"><?= h($s['phone'] ?? '-') ?></td>
                                <td class="text-slate-600 text-xs"><?= h($s['email'] ?? '-') ?></td>
                                <td class="text-right tabular-nums">
                                    <?php if ((int)$s['product_count'] > 0): ?>
                                        <span class="badge badge-blue"><?= (int)$s['product_count'] ?></span>
                                    <?php else: ?>
                                        <span class="text-slate-400">0</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <span class="badge <?= (int)$s['status'] === 1 ? 'badge-green' : 'badge-slate' ?>">
                                        <?= (int)$s['status'] === 1 ? '启用' : '停用' ?>
                                    </span>
                                </td>
                                <td class="text-right whitespace-nowrap">
                                    <a href="/supplier.php?action=edit&id=<?= $s['id'] ?>" class="btn btn-ghost btn-sm">
                                        <i data-lucide="edit-2" class="w-3.5 h-3.5"></i>
                                    </a>
                                    <button class="btn btn-ghost btn-sm" onclick="deleteSupplier(<?= $s['id'] ?>, '<?= h(addslashes($s['name'])) ?>', <?= (int)$s['product_count'] ?>)">
                                        <i data-lucide="trash-2" class="w-3.5 h-3.5 text-red-500"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="px-4 py-3 border-t border-slate-200 flex items-center justify-between text-sm text-slate-500">
                <div>第 <?= $page ?> / <?= $totalPages ?> 页</div>
                <div class="flex gap-1">
                    <?php if ($page > 1): ?>
                        <a href="<?= h(buildSupplierPageUrl($page - 1)) ?>" class="btn btn-secondary btn-sm">上一页</a>
                    <?php endif; ?>
                    <?php if ($page < $totalPages): ?>
                        <a href="<?= h(buildSupplierPageUrl($page + 1)) ?>" class="btn btn-secondary btn-sm">下一页</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
    function deleteSupplier(id, name, productCount) {
        if (productCount > 0) {
            alert('供应商「' + name + '」下还有 ' + productCount + ' 个产品，无法删除\n\n请先把产品转移到其他供应商或删除产品。');
            return;
        }
        if (!confirm('确定要删除供应商「' + name + '」吗？')) return;
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '/api/supplier_delete.php';
        const csrf = document.createElement('input');
        csrf.name = '_csrf';
        csrf.value = '<?= h(csrfToken()) ?>';
        form.appendChild(csrf);
        const idInput = document.createElement('input');
        idInput.name = 'id';
        idInput.value = id;
        form.appendChild(idInput);
        document.body.appendChild(form);
        form.submit();
    }
    </script>
<?php endif; ?>

<?php require __DIR__ . '/includes/views/footer.php'; ?>
