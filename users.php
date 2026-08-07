<?php
/**
 * 用户管理（仅管理员）
 */
define('PMS_ENTRY', true);
require_once __DIR__ . '/includes/bootstrap.php';
if (!PMS_INSTALLED) { header('Location: /install.php'); exit; }
requireAdmin();

$page = max(1, (int)($_GET['page'] ?? 1));
$logResult = Log::list($page, 30);
$logRows = $logResult['rows'];
$logTotal = $logResult['total'];
$logTotalPages = max(1, (int)ceil($logTotal / 30));

$users = User::all();
$currentUserId = (int)($_SESSION['user_id'] ?? 0);

$pageTitle = '用户管理';
$activeMenu = 'users';
require __DIR__ . '/includes/views/header.php';
?>

<div x-data="{ tab: 'users' }">
    <div class="flex items-center gap-4 mb-4 border-b border-slate-200">
        <button @click="tab='users'" :class="tab==='users' ? 'border-blue-600 text-blue-600' : 'border-transparent text-slate-500'" class="px-4 py-2 border-b-2 text-sm font-medium">
            <i data-lucide="users" class="w-4 h-4 inline mr-1.5"></i>用户列表（<?= count($users) ?>）
        </button>
        <button @click="tab='logs'" :class="tab==='logs' ? 'border-blue-600 text-blue-600' : 'border-transparent text-slate-500'" class="px-4 py-2 border-b-2 text-sm font-medium">
            <i data-lucide="activity" class="w-4 h-4 inline mr-1.5"></i>操作日志（<?= $logTotal ?>）
        </button>
    </div>

    <!-- 用户列表 -->
    <div x-show="tab==='users'">
        <div class="flex justify-end mb-3">
            <button class="btn btn-primary" onclick="openUserModal()">
                <i data-lucide="user-plus" class="w-4 h-4 mr-1.5"></i>新增用户
            </button>
        </div>

        <div class="card">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>账号</th>
                        <th>姓名</th>
                        <th>角色</th>
                        <th>状态</th>
                        <th>创建时间</th>
                        <th class="text-right">操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                        <tr>
                            <td class="text-slate-500 tabular-nums"><?= $u['id'] ?></td>
                            <td class="font-medium"><?= h($u['username']) ?></td>
                            <td><?= h($u['name']) ?></td>
                            <td>
                                <span class="badge <?= $u['role'] === 'admin' ? 'badge-purple' : 'badge-blue' ?>">
                                    <?= $u['role'] === 'admin' ? '管理员' : '员工' ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge <?= (int)$u['status'] === 1 ? 'badge-green' : 'badge-slate' ?>">
                                    <?= (int)$u['status'] === 1 ? '启用' : '禁用' ?>
                                </span>
                            </td>
                            <td class="text-slate-500 text-xs"><?= h($u['created_at']) ?></td>
                            <td class="text-right whitespace-nowrap">
                                <button class="btn btn-ghost btn-sm" onclick='openUserModal(<?= json_encode($u, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>
                                    <i data-lucide="edit-2" class="w-3.5 h-3.5"></i>
                                </button>
                                <?php if ((int)$u['id'] !== $currentUserId): ?>
                                <button class="btn btn-ghost btn-sm" onclick="deleteUser(<?= $u['id'] ?>, '<?= h(addslashes($u['username'])) ?>')">
                                    <i data-lucide="trash-2" class="w-3.5 h-3.5 text-red-500"></i>
                                </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- 操作日志 -->
    <div x-show="tab==='logs'">
        <div class="card">
            <?php if (empty($logRows)): ?>
                <div class="card-body text-center text-slate-400 py-12">暂无日志</div>
            <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>时间</th>
                            <th>用户</th>
                            <th>操作</th>
                            <th>对象</th>
                            <th>详情</th>
                            <th>IP</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $actionLabels = [
                            'create' => '创建', 'update' => '更新', 'delete' => '删除',
                            'login' => '登录', 'logout' => '登出', 'upload' => '上传',
                            'move' => '移动', 'reorder' => '重排',
                        ];
                        foreach ($logRows as $log):
                        ?>
                            <tr>
                                <td class="text-slate-500 text-xs whitespace-nowrap"><?= h($log['created_at']) ?></td>
                                <td class="text-slate-700"><?= h($log['username'] ?? '-') ?></td>
                                <td><span class="badge badge-blue"><?= h($actionLabels[$log['action']] ?? $log['action']) ?></span></td>
                                <td class="text-slate-500 text-xs"><?= h($log['target_type'] ?? '-') ?><?= $log['target_id'] ? ' #' . (int)$log['target_id'] : '' ?></td>
                                <td class="text-slate-600 text-sm"><?= h(strLimit($log['details'] ?? '-', 60)) ?></td>
                                <td class="text-slate-400 text-xs font-mono"><?= h($log['ip'] ?? '-') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <?php if ($logTotalPages > 1): ?>
                <div class="px-4 py-3 border-t border-slate-200 flex items-center justify-between text-sm text-slate-500">
                    <div>第 <?= $page ?> / <?= $logTotalPages ?> 页 · 共 <?= $logTotal ?> 条</div>
                    <div class="flex gap-1">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?= $page - 1 ?>#logs" class="btn btn-secondary btn-sm">上一页</a>
                        <?php endif; ?>
                        <?php if ($page < $logTotalPages): ?>
                            <a href="?page=<?= $page + 1 ?>#logs" class="btn btn-secondary btn-sm">下一页</a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- 用户弹窗 -->
<div id="userModal" class="hidden">
    <div class="modal-backdrop" onclick="closeUserModal()">
        <div class="modal-content w-full max-w-md p-6" onclick="event.stopPropagation()">
            <h3 class="text-base font-medium text-slate-800 mb-4" id="userModalTitle">新增用户</h3>
            <form id="userForm" method="post" action="/api/user_save.php">
                <?= csrfField() ?>
                <input type="hidden" name="id" id="user_id">
                <div class="mb-3">
                    <label class="form-label">账号 <span class="text-red-500">*</span></label>
                    <input type="text" name="username" id="user_username" class="form-input" required maxlength="50" pattern="[A-Za-z0-9_]+" title="字母/数字/下划线">
                </div>
                <div class="mb-3">
                    <label class="form-label">姓名 <span class="text-red-500">*</span></label>
                    <input type="text" name="name" id="user_name" class="form-input" required maxlength="50">
                </div>
                <div class="mb-3">
                    <label class="form-label">密码 <span id="passwordRequired" class="text-red-500">*</span></label>
                    <input type="password" name="password" id="user_password" class="form-input" minlength="6" maxlength="50" placeholder="新增必填，编辑留空则不修改">
                </div>
                <div class="mb-3">
                    <label class="form-label">角色 <span class="text-red-500">*</span></label>
                    <select name="role" id="user_role" class="form-select" required>
                        <option value="staff">员工（查看/报价）</option>
                        <option value="admin">管理员（全部权限）</option>
                    </select>
                </div>
                <div class="mb-4">
                    <label class="form-label">状态</label>
                    <select name="status" id="user_status" class="form-select">
                        <option value="1">启用</option>
                        <option value="0">禁用</option>
                    </select>
                </div>
                <div class="flex justify-end gap-2">
                    <button type="button" class="btn btn-secondary" onclick="closeUserModal()">取消</button>
                    <button type="submit" class="btn btn-primary">保存</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openUserModal(user) {
    const isEdit = !!user;
    document.getElementById('userModalTitle').textContent = isEdit ? '编辑用户' : '新增用户';
    document.getElementById('user_id').value = user?.id || '';
    document.getElementById('user_username').value = user?.username || '';
    document.getElementById('user_username').readOnly = isEdit;
    document.getElementById('user_name').value = user?.name || '';
    document.getElementById('user_password').value = '';
    document.getElementById('user_password').required = !isEdit;
    document.getElementById('passwordRequired').style.display = isEdit ? 'none' : 'inline';
    document.getElementById('user_role').value = user?.role || 'staff';
    document.getElementById('user_status').value = user?.status ?? 1;
    document.getElementById('userModal').classList.remove('hidden');
}
function closeUserModal() { document.getElementById('userModal').classList.add('hidden'); }
function deleteUser(id, name) {
    if (!confirm('确定要删除用户「' + name + '」吗？')) return;
    const f = document.createElement('form');
    f.method = 'POST'; f.action = '/api/user_save.php';
    f.innerHTML = '<input name="_csrf" value="<?= h(csrfToken()) ?>"><input name="action" value="delete"><input name="id" value="' + id + '">';
    document.body.appendChild(f); f.submit();
}
</script>

<?php require __DIR__ . '/includes/views/footer.php'; ?>
