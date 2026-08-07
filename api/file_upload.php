<?php
/**
 * API: 文件上传
 */
define('PMS_ENTRY', true);
require_once __DIR__ . '/../includes/bootstrap.php';
if (!PMS_INSTALLED) { jsonResponse(['ok' => false, 'message' => '系统未安装']); }
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['ok' => false, 'message' => 'Method not allowed'], 405);
}
verifyCsrf();

$productId = (int)($_POST['product_id'] ?? 0);
if ($productId <= 0) {
    jsonResponse(['ok' => false, 'message' => '无效的产品 ID']);
}

$product = Product::find($productId);
if (!$product) {
    jsonResponse(['ok' => false, 'message' => '产品不存在']);
}

if (empty($_FILES['file'])) {
    jsonResponse(['ok' => false, 'message' => '未选择文件']);
}

$file = $_FILES['file'];
if ($file['error'] !== UPLOAD_ERR_OK) {
    $errors = [
        UPLOAD_ERR_INI_SIZE => '文件超过服务器限制',
        UPLOAD_ERR_FORM_SIZE => '文件超过表单限制',
        UPLOAD_ERR_PARTIAL => '文件只上传了一部分',
        UPLOAD_ERR_NO_FILE => '没有文件被上传',
    ];
    jsonResponse(['ok' => false, 'message' => $errors[$file['error']] ?? '上传错误']);
}

$originalName = $file['name'];
$ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
$allowedExts = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx'];
if (!in_array($ext, $allowedExts)) {
    jsonResponse(['ok' => false, 'message' => '不允许的文件类型：' . $ext]);
}

// 大小限制
$fileType = detectFileType($ext);
$maxSize = match ($fileType) {
    'image' => 5 * 1024 * 1024,
    'pdf' => 10 * 1024 * 1024,
    default => 10 * 1024 * 1024,
};
if ($file['size'] > $maxSize) {
    jsonResponse(['ok' => false, 'message' => '文件太大（' . formatFileSize($file['size']) . '），限制：' . formatFileSize($maxSize)]);
}

// 存到 uploads/
$uploadDir = __DIR__ . '/../uploads';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$storedName = date('Ymd') . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
$destPath = $uploadDir . '/' . $storedName;

if (!move_uploaded_file($file['tmp_name'], $destPath)) {
    jsonResponse(['ok' => false, 'message' => '保存文件失败']);
}

try {
    $fileId = FileModel::create($productId, $originalName, $storedName, $fileType, $file['size']);
    logAction('upload', 'product_file', $fileId, "上传文件：{$originalName} → 产品「{$product['name']}」");
    jsonResponse(['ok' => true, 'id' => $fileId]);
} catch (Throwable $e) {
    @unlink($destPath);
    jsonResponse(['ok' => false, 'message' => '记录失败：' . $e->getMessage()]);
}
