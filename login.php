<?php
/**
 * 登录页
 */
define('PMS_ENTRY', true);
require_once __DIR__ . '/includes/bootstrap.php';

if (!PMS_INSTALLED) {
    header('Location: /install.php');
    exit;
}

if (isLoggedIn()) {
    header('Location: /dashboard.php');
    exit;
}

$error = '';
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = '请输入账号和密码';
    } else {
        $result = loginUser($username, $password);
        if ($result['ok']) {
            $redirect = $_GET['redirect'] ?? '/dashboard.php';
            // 防止开放重定向
            if (!preg_match('#^/[a-zA-Z0-9_./?=&-]*$#', $redirect)) {
                $redirect = '/dashboard.php';
            }
            header('Location: ' . $redirect);
            exit;
        } else {
            $error = $result['message'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>登录 - 产品管理系统</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="/assets/app.css">
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="bg-slate-100 min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-sm">
        <div class="text-center mb-6">
            <div class="inline-flex items-center justify-center w-14 h-14 bg-blue-600 rounded-lg mb-3">
                <i data-lucide="package" class="w-7 h-7 text-white"></i>
            </div>
            <h1 class="text-xl font-semibold text-slate-800">产品管理系统</h1>
            <p class="text-sm text-slate-500 mt-1">请登录</p>
        </div>

        <div class="card p-6">
            <?php if ($error): ?>
                <div class="mb-4 p-3 rounded border border-red-200 bg-red-50 text-red-700 text-sm">
                    <?= h($error) ?>
                </div>
            <?php endif; ?>

            <form method="post">
                <?= csrfField() ?>
                <div class="mb-4">
                    <label class="form-label">账号</label>
                    <input type="text" name="username" class="form-input" value="<?= h($username) ?>" autofocus required>
                </div>
                <div class="mb-6">
                    <label class="form-label">密码</label>
                    <input type="password" name="password" class="form-input" required>
                </div>
                <button type="submit" class="btn btn-primary w-full">
                    <i data-lucide="log-in" class="w-4 h-4 mr-1.5"></i>登录
                </button>
            </form>
        </div>

        <p class="text-center text-xs text-slate-400 mt-6">内部使用 · 请妥善保管账号</p>
    </div>
    <script>lucide.createIcons();</script>
</body>
</html>
