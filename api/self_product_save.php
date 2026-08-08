<?php
/**
 * API: 自产产品 保存（新增/编辑 + 图片 + BOM）
 * POST multipart/form-data
 */
define('PMS_ENTRY', true);
require_once __DIR__ . '/../includes/bootstrap.php';
if (!PMS_INSTALLED) { jsonResponse(['ok' => false, 'message' => '系统未安装'], 400); }
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['ok' => false, 'message' => 'Method not allowed'], 405);
}
verifyCsrf();

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$name = trim($_POST['name'] ?? '');
$modelNo = trim($_POST['model_no'] ?? '');
$spec = trim($_POST['spec'] ?? '');
$unit = trim($_POST['unit'] ?? '套');
$description = trim($_POST['description'] ?? '');
$status = isset($_POST['status']) ? (int)$_POST['status'] : 1;
$laborCost = (float)($_POST['labor_cost'] ?? 0);
$overheadCost = (float)($_POST['overhead_cost'] ?? 0);
$guidePrice = (float)($_POST['guide_price'] ?? 0);
$minDiscount = (float)($_POST['min_discount'] ?? 1.00);
$guideCoefficient = (float)($_POST['guide_price_coefficient'] ?? 1.600);
$minPriceCoefficient = (float)($_POST['min_price_coefficient'] ?? 0.900);
$costRemark = trim($_POST['cost_remark'] ?? '');
$remark = trim($_POST['remark'] ?? '');
$materialCost = (float)($_POST['material_cost'] ?? 0);
$totalCost = (float)($_POST['total_cost'] ?? 0);
$bomJson = $_POST['bom'] ?? '[]';
$imageRemove = ($_POST['image_remove'] ?? '') === '1';

if ($name === '') jsonResponse(['ok' => false, 'message' => '产品名称必填']);
if ($guidePrice < 0 || $laborCost < 0 || $overheadCost < 0) jsonResponse(['ok' => false, 'message' => '成本/售价不能为负']);
if ($minDiscount < 0.01 || $minDiscount > 1.00) jsonResponse(['ok' => false, 'message' => '最低折扣需在 0.01 ~ 1.00 之间']);

$isEdit = $id > 0;

// ---- 图片处理 ----
$imageName = null;
if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
    $file = $_FILES['image'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png'])) {
        jsonResponse(['ok' => false, 'message' => '仅支持 JPG/PNG 格式']);
    }
    if ($file['size'] > 2 * 1024 * 1024) {
        jsonResponse(['ok' => false, 'message' => '图片不能超过 2MB']);
    }
    $imageName = date('Ymd_') . bin2hex(random_bytes(8)) . '.' . $ext;
    $uploadPath = __DIR__ . '/../uploads/' . $imageName;
    if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
        jsonResponse(['ok' => false, 'message' => '图片上传失败']);
    }
    // 编辑时删旧图
    if ($isEdit) {
        $old = SelfProduct::find($id);
        if ($old && $old['image']) {
            $oldPath = __DIR__ . '/../uploads/' . $old['image'];
            if (file_exists($oldPath)) @unlink($oldPath);
        }
    }
}

// 构建 update data
$updateData = [
    'name' => $name,
    'model_no' => $modelNo,
    'spec' => $spec,
    'unit' => $unit,
    'description' => $description,
    'status' => $status,
    'labor_cost' => $laborCost,
    'overhead_cost' => $overheadCost,
    'material_cost' => $materialCost,
    'total_cost' => $totalCost,
    'guide_price' => $guidePrice,
    'min_discount' => $minDiscount,
    'guide_price_coefficient' => $guideCoefficient,
    'min_price_coefficient' => $minPriceCoefficient,
    'cost_remark' => $costRemark,
    'remark' => $remark,
];

if ($imageRemove) {
    $updateData['image'] = '';   // 标记删除
} elseif ($imageName) {
    $updateData['image'] = $imageName;
}

// ---- 保存（事务）----
$db = Database::getInstance();
$db->beginTransaction();
try {
    if ($isEdit) {
        SelfProduct::update($id, $updateData);
        $actionLabel = '编辑';
    } else {
        $updateData['created_by'] = $_SESSION['user_id'] ?? null;
        $id = SelfProduct::create($updateData);
        $actionLabel = '创建';
    }

    // BOM
    $bomInput = json_decode($bomJson, true) ?: [];
    SelfProduct::saveBom($id, $bomInput);

    logAction($isEdit ? 'update' : 'create', 'self_product', $id, "{$actionLabel}自产产品：{$name}");

    $db->commit();
    jsonResponse(['ok' => true, 'message' => $actionLabel . '成功', 'id' => $id]);
} catch (Throwable $e) {
    $db->rollBack();
    if ($imageName) {
        $uploadedPath = __DIR__ . '/../uploads/' . $imageName;
        if (file_exists($uploadedPath)) @unlink($uploadedPath);
    }
    jsonResponse(['ok' => false, 'message' => '保存失败：' . $e->getMessage()]);
}
