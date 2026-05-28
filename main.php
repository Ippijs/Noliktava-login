<?php

session_start();
require_once __DIR__ . '/db.php';

if (!isset($_SESSION['user'])) {
    header('Location: Login.php');
    exit;
}

$pdo = app_pdo();
$currentUser = $_SESSION['user'];
$message = '';
$error = '';
$role = $currentUser['role'] ?? '';
$csrfToken = app_csrf_token();

if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: Login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!app_verify_csrf($_POST['csrf_token'] ?? null)) {
        $error = 'Security check failed. Please refresh the page and try again.';
    }

    $action = $_POST['action'] ?? '';

    if ($error === '' && $action === 'create_account' && $role === 'admin') {
        $username = trim($_POST['new_username'] ?? '');
        $password = (string) ($_POST['new_password'] ?? '');
        $newRole = $_POST['new_role'] ?? 'shelf_staff';

        if (app_create_user($pdo, $username, $password, $newRole)) {
            $message = 'Account created successfully.';
        } else {
            $error = 'Please check the fields. Username may already exist.';
        }
    }

    if ($error === '' && $action === 'delete_user' && $role === 'admin') {
        $targetUserId = (int) ($_POST['target_user_id'] ?? 0);

        if ($targetUserId === (int) $currentUser['id']) {
            $error = 'You cannot delete your own account.';
        } elseif (app_delete_user($pdo, (int) $currentUser['id'], $targetUserId)) {
            $message = 'User deleted successfully.';
        } else {
            $error = 'Cannot delete this user. Last admin account must remain.';
        }
    }

    if ($error === '' && $action === 'add_item') {
        $itemName = trim($_POST['item_name'] ?? '');
        $quantity = (int) ($_POST['quantity'] ?? 0);
        $shelfId = (int) ($_POST['shelf_id'] ?? 0);

        if (app_add_item($pdo, (int) $currentUser['id'], $itemName, $quantity, $shelfId)) {
            $message = 'Item added successfully.';
        } else {
            $error = 'You cannot add items or the form data is invalid.';
        }
    }

    if ($error === '' && $action === 'remove_item') {
        $itemId = (int) ($_POST['item_id'] ?? 0);
        $quantity = (int) ($_POST['quantity'] ?? 0);

        if (app_remove_item($pdo, (int) $currentUser['id'], $itemId, $quantity)) {
            $message = 'Item removed successfully.';
        } else {
            $error = 'You cannot remove items or the request is invalid.';
        }
    }

    if ($error === '' && $action === 'move_item') {
        $itemId = (int) ($_POST['item_id'] ?? 0);
        $toShelfId = (int) ($_POST['to_shelf_id'] ?? 0);

        if (app_move_item($pdo, (int) $currentUser['id'], $itemId, $toShelfId)) {
            $message = 'Item moved successfully.';
        } else {
            $error = 'You cannot move items or the request is invalid.';
        }
    }
}

$shelves = app_all_shelves($pdo);
$items = app_all_items($pdo);
$users = app_all_users($pdo);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Main Page</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="wrap">
        <div class="hero">
            <div class="topbar">
                <div>
                    <h1>Dashboard</h1>
                    <p>Logged in as <?php echo htmlspecialchars($currentUser['username']); ?> with role <?php echo htmlspecialchars($currentUser['role']); ?>.</p>
                </div>
                <div>
                    <a class="button secondary" href="main.php?logout=1">Logout</a>
                </div>
            </div>
        </div>

        <?php if ($message !== ''): ?>
            <div class="notice notice-success" data-auto-dismiss="true"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <?php if ($error !== ''): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="grid">
            <div class="stack">
                <div class="card">
                    <h2>Your Access</h2>
                    <p class="muted">Only admin users can create worker accounts.</p>

                    <?php if ($role === 'admin'): ?>
                        <a class="button" href="Register.php">Create Account</a>
                    <?php else: ?>
                        <div class="empty">Account creation is disabled for this role.</div>
                    <?php endif; ?>
                </div>

                <div class="card">
                    <h2>Inventory Rules</h2>
                    <div class="panel-note">Admin can add, remove, and move items. Item manager can add and remove items. Shelf staff can only move items to another shelf.</div>
                </div>
            </div>

            <div class="stack">
                <div class="card">
                    <div class="split">
                        <div>
                            <h2>Items Table</h2>
                            <p class="muted">Current inventory in the warehouse.</p>
                        </div>
                    </div>

                    <?php if (count($items) > 0): ?>
                        <div class="table-wrap">
                            <table>
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Item</th>
                                        <th>Quantity</th>
                                        <th>Shelf</th>
                                        <th>Created</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($items as $item): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars((string) $item['id']); ?></td>
                                            <td><?php echo htmlspecialchars($item['item_name']); ?></td>
                                            <td><?php echo htmlspecialchars((string) $item['quantity']); ?></td>
                                            <td><?php echo htmlspecialchars($item['shelf_name']); ?></td>
                                            <td><?php echo htmlspecialchars($item['created_at']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="empty">No items found yet.</div>
                    <?php endif; ?>
                </div>

                <div class="card">
                    <h2>Item Actions</h2>

                    <div class="inventory-grid">
                        <?php if (in_array($role, ['admin', 'item_manager'], true)): ?>
                            <form method="post" class="stack">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                <input type="hidden" name="action" value="add_item">
                                <div class="field">
                                    <label for="item_name">Item Name</label>
                                    <input id="item_name" name="item_name" type="text" minlength="2" maxlength="120" required>
                                </div>
                                <div class="compact">
                                    <div class="field">
                                        <label for="quantity">Quantity</label>
                                        <input id="quantity" name="quantity" type="number" min="1" max="1000000" value="1" required>
                                    </div>
                                    <div class="field">
                                        <label for="shelf_id">Shelf</label>
                                        <select id="shelf_id" name="shelf_id" required>
                                            <?php foreach ($shelves as $shelf): ?>
                                                <option value="<?php echo htmlspecialchars((string) $shelf['id']); ?>"><?php echo htmlspecialchars($shelf['shelf_name']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <button type="submit">Add Item</button>
                            </form>

                            <form method="post" class="stack">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                <input type="hidden" name="action" value="remove_item">
                                <div class="field">
                                    <label for="remove_item_id">Item</label>
                                    <select id="remove_item_id" name="item_id" required>
                                        <?php foreach ($items as $item): ?>
                                            <option value="<?php echo htmlspecialchars((string) $item['id']); ?>"><?php echo htmlspecialchars($item['item_name'] . ' (' . $item['quantity'] . ')'); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="field">
                                    <label for="remove_quantity">Quantity to Remove</label>
                                    <input id="remove_quantity" name="quantity" type="number" min="1" max="1000000" value="1" required>
                                </div>
                                <button type="submit">Remove Item</button>
                            </form>
                        <?php endif; ?>

                        <?php if (in_array($role, ['admin', 'shelf_staff'], true)): ?>
                            <form method="post" class="stack">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                <input type="hidden" name="action" value="move_item">
                                <div class="field">
                                    <label for="move_item_id">Item</label>
                                    <select id="move_item_id" name="item_id" required>
                                        <?php foreach ($items as $item): ?>
                                            <option value="<?php echo htmlspecialchars((string) $item['id']); ?>"><?php echo htmlspecialchars($item['item_name'] . ' - ' . $item['shelf_name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="field">
                                    <label for="to_shelf_id">Move To Shelf</label>
                                    <select id="to_shelf_id" name="to_shelf_id" required>
                                        <?php foreach ($shelves as $shelf): ?>
                                            <option value="<?php echo htmlspecialchars((string) $shelf['id']); ?>"><?php echo htmlspecialchars($shelf['shelf_name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <button type="submit">Move Item</button>
                            </form>
                        <?php endif; ?>
                    </div>

                    <?php if (!in_array($role, ['admin', 'item_manager', 'shelf_staff'], true)): ?>
                        <div class="empty">No item actions available for this role.</div>
                    <?php endif; ?>
                </div>

                <?php if ($role === 'admin'): ?>
                    <div class="card">
                        <h2>Accounts Table</h2>
                        <?php if (count($users) > 0): ?>
                            <div class="table-wrap">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Username</th>
                                            <th>Role</th>
                                            <th>Created</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($users as $user): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars((string) $user['id']); ?></td>
                                                <td><?php echo htmlspecialchars($user['username']); ?></td>
                                                <td>
                                                    <span class="badge <?php echo ($user['role'] === 'admin') ? 'admin' : ''; ?>">
                                                        <?php echo htmlspecialchars($user['role']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo htmlspecialchars($user['created_at']); ?></td>
                                                <td>
                                                    <?php if ((int) $user['id'] === (int) $currentUser['id']): ?>
                                                        <span class="muted">Current User</span>
                                                    <?php else: ?>
                                                        <form method="post" onsubmit="return confirm('Delete this user account?');">
                                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                                            <input type="hidden" name="action" value="delete_user">
                                                            <input type="hidden" name="target_user_id" value="<?php echo htmlspecialchars((string) $user['id']); ?>">
                                                            <button type="submit" class="danger">Delete</button>
                                                        </form>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="empty">No accounts found.</div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div id="toast-container" aria-live="polite" aria-atomic="true"></div>
    <script>
        // auto-dismiss any inline notices
        document.querySelectorAll('[data-auto-dismiss="true"]').forEach((element) => {
            window.setTimeout(() => {
                element.classList.add('notice-hide');
                window.setTimeout(() => element.remove(), 220);
            }, 2500);
        });

        function showToast(message, type = 'success', duration = 4000) {
            const container = document.getElementById('toast-container');
            if (!container || !message) return;
            const toast = document.createElement('div');
            toast.className = 'toast ' + (type === 'error' ? 'toast-error' : 'toast-success');
            toast.setAttribute('role', 'status');
            toast.textContent = message;
            container.appendChild(toast);
            requestAnimationFrame(() => toast.classList.add('show'));
            const hide = () => { toast.classList.remove('show'); toast.addEventListener('transitionend', () => toast.remove(), { once: true }); };
            const timer = setTimeout(hide, duration);
            toast.addEventListener('click', () => { clearTimeout(timer); hide(); });
        }

        document.addEventListener('DOMContentLoaded', () => {
            const successEl = document.querySelector('.notice-success, .notice');
            const errorEl = document.querySelector('.error');
            if (successEl && successEl.textContent.trim() !== '') { showToast(successEl.textContent.trim(), 'success', 3000); successEl.remove(); }
            if (errorEl && errorEl.textContent.trim() !== '') { showToast(errorEl.textContent.trim(), 'error', 6000); errorEl.remove(); }
        });

        // Toggle mobile-only class to apply mobile fixes without changing desktop
        (function(){
            function toggleMobileClass(){
                if (window.innerWidth <= 520) document.body.classList.add('mobile-dashboard');
                else document.body.classList.remove('mobile-dashboard');
            }
            window.addEventListener('resize', toggleMobileClass);
            toggleMobileClass();
        })();
    </script>
</body>
</html>