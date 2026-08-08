<?php
/**
 * API: 产品保存（新增/更新）
 */
define('PMS_ENTRY', true);
require_once __DIR__ . '/../includes/bootstrap.php';
if (!PMS_INSTALLED) { jsonResponse(['ok' => false, 'message' => '系统未安装'], 400); }
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['ok' => false, 'message' => 'Method not allowed'], 405);
}
verifyCsrf();

$id = (int)($_POST['id'] ?? 0);
$sku = trim($_POST['sku'] ?? '');
$name = trim($_POST['name'] ?? '');
$categoryId = $_POST['category_id'] ?? '';
$spec = trim($_POST['spec'] ?? '');
$unit = trim($_POST['unit'] ?? '');
$costPrice = (float)($_POST['cost_price'] ?? 0);
$guidePrice = (float)($_POST['guide_price'] ?? 0);
$minDiscount = (float)($_POST['min_discount'] ?? 1.0);
$supplierId = $_POST['supplier_id'] ?? '';
$status = isset($_POST['status']) ? 1 : 0;
$remark = trim($_POST['remark'] ?? '');
$priceRemark = trim($_POST['price_remark'] ?? '');
$guideCoefficient = (float)($_POST['guide_price_coefficient'] ?? 1.100);
$minPrice = (float)($_POST['min_price'] ?? 0);

if ($name === '') jsonResponse(['ok' => false, 'message' => '产品名称必填']);
if ($sku === '') jsonResponse(['ok' => false, 'message' => 'SKU 必填']);
if ($guidePrice < 0 || $costPrice < 0) jsonResponse(['ok' => false, 'message' => '价格不能为负']);
if ($minDiscount < 0.01 || $minDiscount > 1) jsonResponse(['ok' => false, 'message' => '最高折扣应填 0.01 ~ 1 之间（如 0.85 = 8.5 折）']);

// SKU 唯一性
$existing = Product::findBySku($sku);
if ($existing && (int)$existing['id'] !== $id) {
    jsonResponse(['ok' => false, 'message' => "SKU「{$sku}」已存在，请换一个"]);
}

$data = [
    'sku' => $sku,
    'name' => $name,
    'category_id' => $categoryId !== '' ? (int)$categoryId : null,
    'spec' => $spec,
    'unit' => $unit,
    'cost_price' => $costPrice,
    'guide_price' => $guidePrice,
    'min_discount' => $minDiscount,
    'supplier_id' => $supplierId !== '' ? (int)$supplierId : null,
    'status' => $status,
    'remark' => $remark,
    'price_remark' => $priceRemark,
    'guide_price_coefficient' => $guideCoefficient,
    'min_price' => $minPrice,
];

try {
    if ($id > 0) {
        // 检查分类是否变化，决定是否需要重新生成 SKU
        $oldProduct = Product::find($id);
        $oldCategoryId = $oldProduct['category_id'] ? (int)$oldProduct['category_id'] : null;
        $newCategoryId = $categoryId !== '' ? (int)$categoryId : null;
        $uncategorizedId = Category::getUncategorizedId();
        
        // 判断是否需要重新生成 SKU
        $needRegenerateSku = false;
        if ($oldCategoryId !== $newCategoryId) {
            // 分类变化了
            if ($newCategoryId !== null && $newCategoryId !== $uncategorizedId) {
                // 新分类不是"未分类"，需要重新生成 SKU
                $needRegenerateSku = true;
            }
        }
        
        if ($needRegenerateSku) {
            // 重新生成 SKU
            $newSku = generateSku($newCategoryId);
            $data['sku'] = $newSku;
            logAction('update', 'product', $id, "产品[{$name}]分类变更，SKU 从[{$sku}]重新生成为[{$newSku}]");
        }
        
        Product::update($id, $data);
        logAction('update', 'product', $id, "更新产品：{$name}（SKU: {$data['sku']}）");
        flash('success', '保存成功');
    } else {
        $data['created_by'] = $_SESSION['user_id'] ?? null;
        $newId = Product::create($data);
        logAction('create', 'product', $newId, "创建产品：{$name}（SKU: {$sku}）");
        flash('success', '创建成功');
        // 跳到编辑页
        header('Location: /product_edit.php?id=' . $newId . '&created=1');
        exit;
    }
} catch (Throwable $e) {
    jsonResponse(['ok' => false, 'message' => '保存失败：' . $e->getMessage()]);
}

header('Location: /product_edit.php?id=' . $id . '&saved=1');
exit;
