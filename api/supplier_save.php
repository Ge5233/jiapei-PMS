<?php
/**
 * 供应商保存接口（创建/更新）
 */
define('PMS_ENTRY', true);
require_once __DIR__ . '/../includes/bootstrap.php';
if (!PMS_INSTALLED) { http_response_code(404); exit; }
requireLogin();
if (!isAdmin()) { http_response_code(403); exit('Forbidden'); }

verifyCsrf();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

$id = (int)($_POST['id'] ?? 0);
$name = trim($_POST['name'] ?? '');
$contact = trim($_POST['contact'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$email = trim($_POST['email'] ?? '');
$address = trim($_POST['address'] ?? '');
$bankName = trim($_POST['bank_name'] ?? '');
$bankAccount = trim($_POST['bank_account'] ?? '');
$licenseNo = trim($_POST['license_no'] ?? '');
$remark = trim($_POST['remark'] ?? '');
$status = (int)($_POST['status'] ?? 1);

// 校验
$errors = [];
if ($name === '') $errors[] = '供应商名称必填';
elseif (mb_strlen($name) > 100) $errors[] = '供应商名称不超过 100 字';
elseif (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = '邮箱格式不正确';

if (!empty($errors)) {
    flash('error', implode('；', $errors));
    header('Location: ' . ($id > 0 ? "/supplier.php?action=edit&id=$id" : "/supplier.php?action=edit"));
    exit;
}

$data = [
    'name' => $name,
    'contact' => $contact,
    'phone' => $phone,
    'email' => $email,
    'address' => $address,
    'bank_name' => $bankName,
    'bank_account' => $bankAccount,
    'license_no' => $licenseNo,
    'remark' => $remark,
    'status' => $status,
];

try {
    if ($id > 0) {
        Supplier::update($id, $data);
        Log::record('update', 'supplier', $id, "更新供应商：$name");
        flash('success', '供应商已更新');
    } else {
        // 名称查重
        $existing = Supplier::findByName($name);
        if ($existing) {
            flash('error', '供应商名称已存在');
            header('Location: /supplier.php?action=edit');
            exit;
        }
        $newId = Supplier::create($data);
        Log::record('create', 'supplier', $newId, "创建供应商：$name");
        flash('success', '供应商已创建');
    }
} catch (Throwable $e) {
    flash('error', '保存失败：' . $e->getMessage());
    header('Location: ' . ($id > 0 ? "/supplier.php?action=edit&id=$id" : "/supplier.php?action=edit"));
    exit;
}

header('Location: /supplier.php');
exit;
