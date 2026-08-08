<?php
/**
 * API: 报价单 保存（新增/编辑）
 */
define('PMS_ENTRY', true);
require_once __DIR__ . '/../includes/bootstrap.php';
if (!PMS_INSTALLED) { jsonResponse(['ok' => false, 'message' => '系统未安装'], 400); }
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['ok' => false, 'message' => 'Method not allowed'], 405);
}
verifyCsrf();

$isFormData = stripos($_SERVER['CONTENT_TYPE'] ?? '', 'multipart') !== false;

if ($isFormData) {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $projectName = trim($_POST['project_name'] ?? '');
    $customerName = trim($_POST['customer_name'] ?? '');
    $contactPerson = trim($_POST['contact_person'] ?? '');
    $contactPhone = trim($_POST['contact_phone'] ?? '');
    $paymentTerms = trim($_POST['payment_terms'] ?? '');
    $warranty = trim($_POST['warranty'] ?? '1年');
    $deliveryPeriod = trim($_POST['delivery_period'] ?? '');
    $validUntil = trim($_POST['valid_until'] ?? '');
    $taxRate = (float)($_POST['tax_rate'] ?? 0.13);
    $status = $_POST['status'] ?? 'draft';
    $remark = trim($_POST['remark'] ?? '');
    $subtotal = (float)($_POST['subtotal'] ?? 0);
    $taxAmount = (float)($_POST['tax_amount'] ?? 0);
    $totalAmount = (float)($_POST['total_amount'] ?? 0);
    $itemsJson = $_POST['items'] ?? '[]';
} else {
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $id = isset($input['id']) ? (int)$input['id'] : 0;
    $projectName = trim($input['project_name'] ?? '');
    $customerName = trim($input['customer_name'] ?? '');
    $contactPerson = trim($input['contact_person'] ?? '');
    $contactPhone = trim($input['contact_phone'] ?? '');
    $paymentTerms = trim($input['payment_terms'] ?? '');
    $warranty = trim($input['warranty'] ?? '1年');
    $deliveryPeriod = trim($input['delivery_period'] ?? '');
    $validUntil = trim($input['valid_until'] ?? '');
    $taxRate = (float)($input['tax_rate'] ?? 0.13);
    $status = $input['status'] ?? 'draft';
    $remark = trim($input['remark'] ?? '');
    $subtotal = (float)($input['subtotal'] ?? 0);
    $taxAmount = (float)($input['tax_amount'] ?? 0);
    $totalAmount = (float)($input['total_amount'] ?? 0);
    $itemsJson = json_encode($input['items'] ?? []);
}

if ($projectName === '') jsonResponse(['ok' => false, 'message' => '请输入项目名称']);

$isEdit = $id > 0;
$data = [
    'project_name' => $projectName,
    'customer_name' => $customerName,
    'contact_person' => $contactPerson,
    'contact_phone' => $contactPhone,
    'payment_terms' => $paymentTerms,
    'warranty' => $warranty,
    'delivery_period' => $deliveryPeriod,
    'valid_until' => $validUntil ?: null,
    'tax_rate' => $taxRate,
    'status' => $status,
    'remark' => $remark,
    'subtotal' => $subtotal,
    'tax_amount' => $taxAmount,
    'total_amount' => $totalAmount,
];

$db = Database::getInstance();
$db->beginTransaction();
try {
    if ($isEdit) {
        Quote::update($id, $data);
        $actionLabel = '编辑';
    } else {
        $data['quote_no'] = Quote::generateNo();
        $data['created_by'] = $_SESSION['user_id'] ?? null;
        $id = Quote::create($data);
        $actionLabel = '创建';
    }

    $items = json_decode($itemsJson, true) ?: [];
    Quote::saveItems($id, $items);

    logAction($isEdit ? 'update' : 'create', 'quote', $id, "{$actionLabel}报价单：{$projectName}");

    $db->commit();
    jsonResponse(['ok' => true, 'message' => $actionLabel . '成功', 'id' => $id]);
} catch (Throwable $e) {
    $db->rollBack();
    jsonResponse(['ok' => false, 'message' => '保存失败：' . $e->getMessage()]);
}
