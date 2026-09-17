<?php
// ============================================================
//  manage_users.php  -  User Management
// ============================================================
//  Lists every user in the system with the ability to
//  activate or deactivate accounts. Self-deactivation is
//  blocked — an admin cannot accidentally lock themselves
//  out, which would lock the platform out entirely if they
//  were the only admin.
// ============================================================

require_once 'includes/db.php';
require_admin();

$section = 'Admin';
$page    = 'manage_users.php';
$title   = 'Manage Users';
require_once 'includes/header.php';


// ─── Read filter ───────────────────────────────────────────
$f_role   = isset($_GET['role'])   && in_array($_GET['role'], ['user', 'admin'], true)
            ? $_GET['role'] : '';
$f_status = isset($_GET['status']) && in_array($_GET['status'], ['1', '0'], true)
            ? $_GET['status'] : '';
$f_search = trim($_GET['search'] ?? '');


// ─── Build the WHERE clause ────────────────────────────────
$where  = ['1=1'];
$types  = '';
$params = [];

if ($f_role !== '') {
    $where[]  = 'u.role = ?';
    $types   .= 's';
    $params[] = $f_role;
}
if ($f_status !== '') {
    $where[]  = 'u.is_active = ?';
    $types   .= 'i';
    $params[] = (int)$f_status;
}
if ($f_search !== '') {
    $safe = '%' . addcslashes($f_search, '%_\\') . '%';
    $where[]  = '(u.username LIKE ? OR u.email LIKE ? OR p.name LIKE ?)';
    $types   .= 'sss';
    array_push($params, $safe, $safe, $safe);
}

$where_sql = 'WHERE ' . implode(' AND ', $where);


// ─── Fetch users with activity summary ─────────────────────
$users = db_all($conn, "
    SELECT u.id, u.username, u.email, u.role, u.is_active, u.created_at,
           p.name,
           (SELECT COUNT(*) FROM transactions t WHERE t.user_id = u.id) AS txn_count,
           (SELECT MAX(t.txn_date) FROM transactions t WHERE t.user_id = u.id) AS last_activity
    FROM users u
    LEFT JOIN user_profiles p ON p.user_id = u.id
    $where_sql
    ORDER BY u.created_at DESC
", $types, $params);

$current_admin_id = current_user_id();
?>

<!-- Filter bar -->
<form method="GET">
<div class="filter-bar">
    <select name="role">
        <option value="">All Roles</option>
        <option value="user"  <?= $f_role === 'user'  ? 'selected' : '' ?>>Regular Users</option>
        <option value="admin" <?= $f_role === 'admin' ? 'selected' : '' ?>>Administrators</option>
    </select>
    <select name="status">
        <option value="">Any Status</option>
        <option value="1" <?= $f_status === '1' ? 'selected' : '' ?>>Active</option>
        <option value="0" <?= $f_status === '0' ? 'selected' : '' ?>>Inactive</option>
    </select>
    <input type="text" name="search"
           placeholder="Search username, email, or name..."
           value="<?= htmlspecialchars($f_search) ?>">
    <button type="submit" class="btn btn-primary">Filter</button>
    <a href="manage_users.php" class="btn btn-ghost">Clear</a>
</div>
</form>

<!-- Users table -->
<div class="card">
    <div class="card-title">
        Users
        <span class="card-meta"><?= count($users) ?> record(s)</span>
    </div>

    <?php if (empty($users)): ?>
        <div class="empty">No users match your filter.</div>
    <?php else: ?>
    <div class="tbl-wrap">
    <table>
        <thead>
            <tr>
                <th>Name</th>
                <th>Username</th>
                <th>Email</th>
                <th>Role</th>
                <th>Status</th>
                <th>Transactions</th>
                <th>Last Activity</th>
                <th>Joined</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($users as $u):
            $is_self   = (int)$u['id'] === $current_admin_id;
            $is_active = (int)$u['is_active'] === 1;
        ?>
            <tr>
                <td class="td-name">
                    <?= htmlspecialchars($u['name'] ?? '—') ?>
                    <?php if ($is_self): ?>
                        <span class="sf-role" style="margin-left:4px">YOU</span>
                    <?php endif; ?>
                </td>
                <td class="td-muted"><?= htmlspecialchars($u['username']) ?></td>
                <td class="td-muted"><?= htmlspecialchars($u['email']) ?></td>
                <td>
                    <span class="badge <?= $u['role'] === 'admin' ? 'badge-warn' : 'badge-info' ?>">
                        <?= ucfirst($u['role']) ?>
                    </span>
                </td>
                <td>
                    <span class="badge <?= $is_active ? 'badge-inc' : 'badge-exp' ?>">
                        <?= $is_active ? 'Active' : 'Inactive' ?>
                    </span>
                </td>
                <td class="td-muted td-mono"><?= (int)$u['txn_count'] ?></td>
                <td class="td-muted td-mono">
                    <?= $u['last_activity']
                       ? date('d M Y', strtotime($u['last_activity']))
                       : '—' ?>
                </td>
                <td class="td-muted td-mono">
                    <?= date('d M Y', strtotime($u['created_at'])) ?>
                </td>
                <td>
                    <?php if ($is_self): ?>
                        <span class="td-muted" style="font-size:.72rem">—</span>
                    <?php elseif ($is_active): ?>
                        <a href="user_toggle.php?id=<?= (int)$u['id'] ?>&action=deactivate"
                           class="btn btn-sm btn-danger"
                           onclick="return confirm('Deactivate this account? The user will not be able to sign in until reactivated.')">
                            Deactivate
                        </a>
                    <?php else: ?>
                        <a href="user_toggle.php?id=<?= (int)$u['id'] ?>&action=activate"
                           class="btn btn-sm btn-success"
                           onclick="return confirm('Reactivate this account?')">
                            Activate
                        </a>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>
</div>

<!-- Notes for the admin -->
<div class="card" style="margin-top:18px">
    <div class="card-title">Notes</div>
    <ul class="tip-list">
        <li>
            <div class="tip-head">You cannot deactivate yourself</div>
            <div class="tip-body">
                The Actions column is empty on your own row to prevent
                accidental self-lockout, which could lock the platform
                out entirely if you are the only administrator.
            </div>
        </li>
        <li>
            <div class="tip-head">Deactivation is reversible</div>
            <div class="tip-body">
                Deactivating an account preserves all the user's data &mdash;
                transactions, budgets, goals, settings, and notifications.
                Reactivating restores their ability to sign in.
            </div>
        </li>
        <li>
            <div class="tip-head">Every action is logged</div>
            <div class="tip-body">
                Every activate or deactivate operation is recorded in
                the <code>admin_logs</code> table. The audit trail is
                visible on the System Logs page.
            </div>
        </li>
    </ul>
</div>

<?php require_once 'includes/footer.php'; ?>
