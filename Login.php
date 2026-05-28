<?php

session_start();
require_once __DIR__ . '/db.php';

if (isset($_SESSION['user'])) {
    header('Location: main.php');
    exit;
}

$pdo = app_pdo();
$error = '';
$csrfToken = app_csrf_token();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!app_verify_csrf($_POST['csrf_token'] ?? null)) {
        $error = 'Security check failed. Please refresh the page and try again.';
    }

    $username = trim($_POST['username'] ?? '');
    $password = (string) ($_POST['password'] ?? '');

    if ($error === '' && (!app_is_valid_username($username) || !app_is_valid_password($password))) {
        $error = 'Enter a valid username and password.';
    }

    $user = $error === '' ? app_find_user($pdo, $username) : null;

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user'] = [
            'id' => $user['id'],
            'username' => $user['username'],
            'role' => $user['role'],
        ];

        header('Location: main.php');
        exit;
    }

    $error = 'Invalid username or password.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="wrap">
        <div class="hero">
            <h1>Login</h1>
            <p>Use your account to sign in. Only admin users can register new accounts.</p>
        </div>

        <div class="grid">
            <div class="card">
                <h2>Sign In</h2>

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
                    <button type="submit">Login</button>
                </form>
                <p class="muted">Password rule: minimum 6 characters, at least one letter, at least one number, no spaces.</p>
            </div>

            <div class="card">
                <h2>How It Works</h2>
                <p class="muted">This page connects to a MySQL database, keeps users in a single table, and prepares the admin account automatically if the table is empty.</p>
                <div class="hint">After login, the main page shows the account table and the admin-only account creation page.</div>
            </div>
        </div>
    </div>
    <div id="toast-container" aria-live="polite" aria-atomic="true"></div>
    <script>
        function showToast(message, type = 'error', duration = 5000) {
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