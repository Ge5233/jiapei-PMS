<?php
/**
 * 我的资料 - 改密码
 */
define('PMS_ENTRY', true);
require_once __DIR__ . '/includes/bootstrap.php';
if (!PMS_INSTALLED) { header('Location: /install.php'); exit; }
requireLogin();

$user = currentUser();
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $old = $_POST['old_password'] ?? '';
    $new = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    $dbUser = User::find((int)$user['id']);
    if (!password_verify($old, $dbUser['password_hash'])) {
        $error = '原密码错误';
    } elseif (strlen($new) < 6) {
        $error = '新密码至少 6 位';
    } elseif ($new !== $confirm) {
        $error = '两次输入的新密码不一致';
    } else {
        User::update((int)$user['id'], $new, null, null, null);
        logAction('update', 'user', (int)$user['id'], '修改自己的密码');
        $success = '密码已更新';
    }
}

$pageTitle = '我的资料';
$activeMenu = 'profile';
require __DIR__ . '/includes/views/header.php';
?>

<div class="max-w-md">
    <div class="card mb-4">
        <div class="card-header">账号信息</div>
        <div class="card-body">
            <div class="grid grid-cols-3 gap-3 text-sm">
                <div class="text-slate-500">账号</div>
                <div class="col-span-2 font-medium"><?= h($user['username']) ?></div>
                <div class="text-slate-500">姓名</div>
                <div class="col-span-2"><?= h($user['name']) ?></div>
                <div class="text-slate-500">角色</div>
                <div class="col-span-2">
                    <span class="badge <?= $user['role'] === 'admin' ? 'badge-purple' : 'badge-blue' ?>">
                        <?= $user['role'] === 'admin' ? '管理员' : '员工' ?>
                    </span>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">修改密码</div>
        <div class="card-body">
            <?php if ($error): ?>
                <div class="mb-4 p-3 rounded border border-red-200 bg-red-50 text-red-700 text-sm"><?= h($error) ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="mb-4 p-3 rounded border border-green-200 bg-green-50 text-green-700 text-sm"><?= h($success) ?></div>
            <?php endif; ?>
            <form method="post">
                <?= csrfField() ?>
                <div class="mb-3">
                    <label class="form-label">原密码 <span class="text-red-500">*</span></label>
                    <input type="password" name="old_password" class="form-input" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">新密码 <span class="text-red-500">*</span></label>
                    <input type="password" name="new_password" class="form-input" required minlength="6">
                    <p class="form-help">至少 6 位</p>
                </div>
                <div class="mb-4">
                    <label class="form-label">确认新密码 <span class="text-red-500">*</span></label>
                    <input type="password" name="confirm_password" class="form-input" required minlength="6">
                </div>
                <button type="submit" class="btn btn-primary">
                    <i data-lucide="save" class="w-4 h-4 mr-1.5"></i>更新密码
                </button>
            </form>
        </div>
    </div>
</div>

<?php require __DIR__ . '/includes/views/footer.php'; ?>
