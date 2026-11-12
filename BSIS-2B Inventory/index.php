<?php
require 'config.php';
if (!empty($_SESSION['user']['id'])) { header('Location: dashboard.php'); exit; }
$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $username = trim($_POST['username'] ?? '');
    $stmt = $pdo->prepare('SELECT id, username, password, status, role FROM users WHERE username = ? LIMIT 1');
    $stmt->execute([$username]);
    $account = $stmt->fetch();

    if (!$account || !password_verify($_POST['password'] ?? '', $account['password'])) {
        $err = 'Incorrect username or password.';
    } elseif ($account['status'] !== 'approved') {
        $err = $account['status'] === 'pending'
            ? 'Your account is waiting for administrator approval.'
            : 'Your account has not been approved.';
    } else {
        session_regenerate_id(true);
        $_SESSION['user'] = [
            'id' => (int)$account['id'],
            'username' => $account['username'],
            'role' => $account['role']
        ];
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
        header('Location: dashboard.php');
        exit;
    }
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Sign In | Inventory System</title><link rel="stylesheet" href="assets/style.css"></head>
<body class="auth"><main class="card"><div class="mark">IS</div><small>INVENTORY MANAGEMENT</small><h1>Welcome back</h1><p>Sign in to manage your inventory.</p>
<?php if ($err): ?><div class="alert"><?=e($err)?></div><?php endif; ?>
<form method="post"><input type="hidden" name="csrf" value="<?=e(csrf())?>"><label>Username</label><input name="username" maxlength="30" autocomplete="username" required><label>Password</label><input type="password" name="password" autocomplete="current-password" required><button>Sign In</button></form>
<p class="center">Don't have an account? <a href="register.php">Create account</a></p></main></body></html>
