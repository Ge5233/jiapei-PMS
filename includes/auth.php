<?php
/**
 * 认证相关
 */

if (!defined('PMS_ENTRY')) {
    http_response_code(403);
    exit('Forbidden');
}

/**
 * 检查是否已登录
 */
function isLoggedIn(): bool
{
    if (empty($_SESSION['user_id'])) return false;

    // Session 过期检查
    $lifetime = (int)($_ENV['SESSION_LIFETIME'] ?? '7200');
    if (isset($_SESSION['_last_active']) && (time() - $_SESSION['_last_active']) > $lifetime) {
        logout();
        return false;
    }
    $_SESSION['_last_active'] = time();
    return true;
}

/**
 * 要求登录（否则跳登录页）
 */
function requireLogin(): void
{
    if (!isLoggedIn()) {
        $currentUrl = $_SERVER['REQUEST_URI'] ?? '';
        redirect('/login.php?redirect=' . urlencode($currentUrl));
    }
}

/**
 * 要求管理员
 */
function requireAdmin(): void
{
    requireLogin();
    if (($_SESSION['role'] ?? '') !== 'admin') {
        http_response_code(403);
        echo '<h1>权限不足</h1><p>该功能仅限管理员使用</p>';
        exit;
    }
}

/**
 * 登录
 */
function loginUser(string $username, string $password): array
{
    $sql = "SELECT id, username, password_hash, name, role, status FROM users WHERE username = ? LIMIT 1";
    $stmt = Database::getInstance()->prepare($sql);
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if (!$user) {
        return ['ok' => false, 'message' => '账号或密码错误'];
    }
    if ((int)$user['status'] === 0) {
        return ['ok' => false, 'message' => '账号已禁用，请联系管理员'];
    }
    if (!password_verify($password, $user['password_hash'])) {
        return ['ok' => false, 'message' => '账号或密码错误'];
    }

    // 重新生成 session id 防固定攻击
    session_regenerate_id(true);

    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['name'] = $user['name'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['_last_active'] = time();

    logAction('login', 'user', (int)$user['id'], '用户登录');

    return ['ok' => true];
}

/**
 * 登出
 */
function logout(): void
{
    if (!empty($_SESSION['user_id'])) {
        logAction('logout', 'user', (int)$_SESSION['user_id'], '用户登出');
    }
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}

/**
 * 获取当前用户信息
 */
function currentUser(): ?array
{
    if (!isLoggedIn()) return null;
    return [
        'id' => $_SESSION['user_id'],
        'username' => $_SESSION['username'],
        'name' => $_SESSION['name'] ?? $_SESSION['username'],
        'role' => $_SESSION['role'],
    ];
}

/**
 * 是否管理员
 */
function isAdmin(): bool
{
    return ($_SESSION['role'] ?? '') === 'admin';
}

/** 员工能否看成本（只有管理员能看） */
function canViewCost(): bool
{
    return isAdmin();
}

/** 阻止非管理员访问成本相关页面 */
function requireCostView(): void
{
    if (!canViewCost()) {
        header('Location: /quotes.php');
        exit;
    }
}
