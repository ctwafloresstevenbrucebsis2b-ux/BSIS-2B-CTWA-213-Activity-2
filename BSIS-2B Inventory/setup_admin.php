<?php
require 'config.php';
$count = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='admin'")->fetchColumn();
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $count === 0) {
    verify_csrf();
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    if (!preg_match('/^[A-Za-z0-9_.-]{3,30}$/', $username) || strlen($password) < 8) {
        $msg = 'Use a valid username (3–30 characters) and a password of at least 8 characters.';
    } else {
        $stmt = $pdo->prepare("INSERT INTO users(username,password,status,role) VALUES(?,?,'approved','admin')");
        $stmt->execute([$username, password_hash($password, PASSWORD_DEFAULT)]);
        $msg = 'Administrator created. Delete setup_admin.php immediately.';
        $count = 1;
    }
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Initial Admin Setup</title><link rel="stylesheet" href="assets/style.css"></head>
<body class="auth"><main class="card"><h1>Initial admin setup</h1><?php if ($count): ?><div class="success">An administrator account exists. Delete setup_admin.php now.</div><?php else: ?><?php if ($msg): ?><div class="alert"><?=e($msg)?></div><?php endif; ?>
<form method="post"><input type="hidden" name="csrf" value="<?=e(csrf())?>"><label>Admin username</label><input name="username" minlength="3" maxlength="30" required><label>Password (8+ characters)</label><input type="password" name="password" minlength="8" required><button>Create admin</button></form><?php endif; ?></main></body></html>
