<?php
/**
 * 工具函数
 */

if (!defined('PMS_ENTRY')) {
    http_response_code(403);
    exit('Forbidden');
}

/**
 * HTML 转义输出
 */
function h(?string $str): string
{
    return htmlspecialchars((string)$str, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/**
 * 闪存消息
 */
function flash(?string $type = null, ?string $message = null)
{
    if ($type === null) {
        // 读取并清除
        $msg = $_SESSION['_flash'] ?? null;
        unset($_SESSION['_flash']);
        return $msg;
    }
    $_SESSION['_flash'] = ['type' => $type, 'message' => $message];
}

/**
 * 渲染闪存消息 HTML
 */
function renderFlash(): string
{
    $f = flash();
    if (!$f) return '';
    $type = $f['type'] ?? 'info';
    $message = $f['message'] ?? '';
    $colorMap = [
        'success' => 'bg-green-50 border-green-200 text-green-700',
        'error' => 'bg-red-50 border-red-200 text-red-700',
        'warning' => 'bg-yellow-50 border-yellow-200 text-yellow-700',
        'info' => 'bg-blue-50 border-blue-200 text-blue-700',
    ];
    $class = $colorMap[$type] ?? $colorMap['info'];
    return '<div class="border rounded-md px-4 py-3 mb-4 ' . h($class) . '">' . h($message) . '</div>';
}

/**
 * 生成 / 获取 CSRF Token
 */
function csrfToken(): string
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}

/**
 * 渲染 CSRF 隐藏域
 */
function csrfField(): string
{
    return '<input type="hidden" name="_csrf" value="' . h(csrfToken()) . '">';
}

/**
 * 校验 CSRF Token
 */
function verifyCsrf(): void
{
    $token = $_POST['_csrf'] ?? $_GET['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!hash_equals($_SESSION['_csrf'] ?? '', $token)) {
        http_response_code(403);
        exit('CSRF 校验失败，请刷新页面重试');
    }
}

/**
 * 格式化金额（元）
 */
function formatPrice($value): string
{
    if ($value === null || $value === '' || !is_numeric($value)) {
        return '¥0.00';
    }
    return '¥' . number_format((float)$value, 2);
}

/**
 * 计算毛利率（百分比）
 * @return float|null null 表示不适用（进价或售价为 0）
 */
function calcMargin(?float $cost, ?float $price): ?float
{
    if ($cost === null || $price === null) return null;
    if ($price <= 0) return null;
    return round(($price - $cost) / $price * 100, 2);
}

/**
 * 毛利率徽章 class
 */
function marginClass(?float $margin): string
{
    if ($margin === null) return 'bg-slate-100 text-slate-600';
    if ($margin < 10) return 'bg-red-100 text-red-700';
    if ($margin < 20) return 'bg-yellow-100 text-yellow-700';
    return 'bg-green-100 text-green-700';
}

/**
 * 记录操作日志
 */
function logAction(string $action, ?string $targetType = null, ?int $targetId = null, ?string $details = null): void
{
    try {
        $userId = $_SESSION['user_id'] ?? null;
        $username = $_SESSION['username'] ?? null;
        $ip = clientIp();

        $sql = "INSERT INTO operation_logs (user_id, username, action, target_type, target_id, details, ip, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
        $stmt = Database::getInstance()->prepare($sql);
        $stmt->execute([$userId, $username, $action, $targetType, $targetId, $details, $ip]);
    } catch (Throwable $e) {
        // 日志失败不中断主流程
        error_log('logAction failed: ' . $e->getMessage());
    }
}

/**
 * 获取客户端 IP
 */
function clientIp(): string
{
    foreach (['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'] as $key) {
        if (!empty($_SERVER[$key])) {
            $ip = trim(explode(',', $_SERVER[$key])[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
    }
    return '0.0.0.0';
}

/**
 * 文件大小格式化
 */
function formatFileSize(int $bytes): string
{
    if ($bytes < 1024) return $bytes . ' B';
    if ($bytes < 1024 * 1024) return round($bytes / 1024, 1) . ' KB';
    return round($bytes / 1024 / 1024, 1) . ' MB';
}

/**
 * 文件类型识别
 */
function detectFileType(string $ext): string
{
    $ext = strtolower($ext);
    if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'])) return 'image';
    if ($ext === 'pdf') return 'pdf';
    if (in_array($ext, ['doc', 'docx'])) return 'word';
    if (in_array($ext, ['xls', 'xlsx'])) return 'excel';
    if (in_array($ext, ['ppt', 'pptx'])) return 'ppt';
    return 'other';
}

/**
 * 重定向
 */
function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

/**
 * JSON 响应
 */
function jsonResponse(array $data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * 截断字符串
 */
function strLimit(string $str, int $len = 50, string $suffix = '...'): string
{
    if (mb_strlen($str) <= $len) return $str;
    return mb_substr($str, 0, $len) . $suffix;
}

/**
 * 生成 SKU
 * 格式：父分类ID(2位) + 子分类ID(2位) + 序号(3位)
 * 
 * @param int $categoryId 分类 ID（必须是二级分类）
 * @return string 生成的 SKU
 * @throws RuntimeException 如果分类无效或不是二级分类
 */
function generateSku(int $categoryId): string
{
    if ($categoryId <= 0) {
        throw new RuntimeException('请选择分类');
    }
    
    $db = Database::getInstance();
    
    // 查询分类信息（包含 parent_sort_id 和 sub_id）
    $stmt = $db->prepare("SELECT id, name, parent_id, parent_sort_id, sub_id FROM categories WHERE id = ?");
    $stmt->execute([$categoryId]);
    $category = $stmt->fetch();
    
    if (!$category) {
        throw new RuntimeException('分类不存在');
    }
    
    $parentId = (int)$category['parent_id'];
    $parentSortId = (int)$category['parent_sort_id'];
    $subId = (int)$category['sub_id'];
    
    // 如果 parent_id = 0，说明是一级分类，不能作为产品分类
    if ($parentId === 0) {
        throw new RuntimeException('请选择二级分类（子分类）');
    }
    
    // 如果是子分类，需要获取父分类的 parent_sort_id
    if ($parentId > 0) {
        $stmt = $db->prepare("SELECT parent_sort_id FROM categories WHERE id = ?");
        $stmt->execute([$parentId]);
        $parent = $stmt->fetch();
        $parentSortId = $parent ? (int)$parent['parent_sort_id'] : 0;
    }
    
    // 生成 SKU 前缀：父分类parent_sort_id(2位) + 子分类sub_id(2位)
    $prefix = str_pad($parentSortId, 2, '0', STR_PAD_LEFT) . str_pad($subId, 2, '0', STR_PAD_LEFT);
    
    // 查询该分类下已有产品数量（用于生成序号）
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM products WHERE category_id = ?");
    $stmt->execute([$categoryId]);
    $result = $stmt->fetch();
    $count = (int)$result['count'];
    
    // 生成序号（3位，从 001 开始）
    $seq = str_pad($count + 1, 3, '0', STR_PAD_LEFT);
    
    // 完整 SKU
    $sku = $prefix . $seq;
    
    // 检查 SKU 是否已存在（防止冲突）
    $stmt = $db->prepare("SELECT id FROM products WHERE sku = ?");
    $stmt->execute([$sku]);
    if ($stmt->fetch()) {
        // SKU 已存在，递增序号
        for ($i = 1; $i <= 999; $i++) {
            $seq = str_pad($i, 3, '0', STR_PAD_LEFT);
            $sku = $prefix . $seq;
            $stmt = $db->prepare("SELECT id FROM products WHERE sku = ?");
            $stmt->execute([$sku]);
            if (!$stmt->fetch()) {
                break;
            }
        }
    }
    
    return $sku;
}
