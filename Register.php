<?php

session_start();
require_once __DIR__ . '/db.php';

if (!isset($_SESSION['user'])) {
    header('Location: Login.php');
    exit;
}

if (($_SESSION['user']['role'] ?? '') !== 'admin') {
    header('Location: main.php');
    exit;
}

$pdo = app_pdo();
$message = '';
$error = '';
$csrfToken = app_csrf_token();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!app_verify_csrf($_POST['csrf_token'] ?? null)) {
        $error = 'Security check failed. Please refresh the page and try again.';
    }

    $username = trim($_POST['username'] ?? '');
    $password = (string) ($_POST['password'] ?? '');
    $role = $_POST['role'] ?? 'shelf_staff';

    if ($error === '' && app_create_user($pdo, $username, $password, $role)) {
        $message = 'Account created successfully.';
    } elseif ($error === '') {
        $error = 'Please enter a unique username and a password with at least 6 characters, at least one letter, at least one number, and no spaces.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register Account</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="wrap">
        <div class="hero">
            <h1>Register Account</h1>
            <p>Only the admin account can create worker accounts for item manager and shelf staff.</p>
        </div>

        <div class="card">
            <h2>New User</h2>

            <?php if ($message !== ''): ?>
                <div class="notice"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>

            <?php if ($error !== ''): ?>
                <div class="error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="post">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                <div class="field">
                    <label for="username">Username</label>
                    <input id="username" name="username" type="text" minlength="3" maxlength="50" pattern="[A-Za-z0-9_]{3,50}" required>
                </div>
                <div class="field">
                    <label for="password">Password</label>
                    <input id="password" name="password" type="password" minlength="6" maxlength="72" required>
                </div>
                <div class="field">
                    <label for="role">Role</label>
                    <select id="role" name="role">
                        <option value="item_manager">Item Manager</option>
                        <option value="shelf_staff">Shelf Staff</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
                <div class="actions">
                    <button type="submit">Create Account</button>
                    <a class="button" href="main.php">Back to Dashboard</a>
                </div>
            </form>
        </div>
    </div>
    <div id="toast-container" aria-live="polite" aria-atomic="true"></div>
    <script>
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
    </script>
</body>
</html>