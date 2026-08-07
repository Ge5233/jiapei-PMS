<?php
/**
 * API: 用户保存（新增/更新/删除）
 */
define('PMS_ENTRY', true);
require_once __DIR__ . '/../includes/bootstrap.php';
if (!PMS_INSTALLED) { header('Location: /install.php'); exit; }
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /users.php');
    exit;
}
verifyCsrf();

$action = $_POST['action'] ?? 'save';

if ($action === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0 || $id === (int)($_SESSION['user_id'] ?? 0)) {
        flash('error', '不能删除自己或不存在的用户');
        header('Location: /users.php');
        exit;
    }
    $u = User::find($id);
    if (!$u) {
        flash('error', '用户不存在');
        header('Location: /users.php');
        exit;
    }
    try {
        User::delete($id);
        logAction('delete', 'user', $id, "删除用户：{$u['username']}");
        flash('success', '用户已删除');
    } catch (Throwable $e) {
        flash('error', '删除失败：' . $e->getMessage());
    }
    header('Location: /users.php');
    exit;
}

$id = (int)($_POST['id'] ?? 0);
$username = trim($_POST['username'] ?? '');
$name = trim($_POST['name'] ?? '');
$password = $_POST['password'] ?? '';
$role = $_POST['role'] ?? 'staff';
$status = (int)($_POST['status'] ?? 1);

if ($username === '' || $name === '') {
    flash('error', '账号和姓名必填');
    header('Location: /users.php');
    exit;
}
if (!preg_match('/^[A-Za-z0-9_]+$/', $username)) {
    flash('error', '账号只能是字母/数字/下划线');
    header('Location: /users.php');
    exit;
}
if (!in_array($role, ['admin', 'staff'], true)) {
    flash('error', '无效的角色');
    header('Location: /users.php');
    exit;
}

try {
    if ($id > 0) {
        $existing = User::find($id);
        if (!$existing) {
            flash('error', '用户不存在');
            header('Location: /users.php');
            exit;
        }
        User::update($id, $password !== '' ? $password : null, $name, $role, $status);
        logAction('update', 'user', $id, "更新用户：{$username}");
    } else {
        if (strlen($password) < 6) {
            flash('error', '密码至少 6 位');
            header('Location: /users.php');
            exit;
        }
        if (User::findByUsername($username)) {
            flash('error', "账号「{$username}」已存在");
            header('Location: /users.php');
            exit;
        }
        $newId = User::create($username, $password, $name, $role, $status);
        logAction('create', 'user', $newId, "创建用户：{$username}（{$role}）");
    }
    flash('success', '保存成功');
} catch (Throwable $e) {
    flash('error', '保存失败：' . $e->getMessage());
}

header('Location: /users.php');
exit;
