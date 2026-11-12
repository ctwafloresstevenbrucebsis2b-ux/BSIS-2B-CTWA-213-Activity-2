<?php
require 'config.php';
if (!empty($_SESSION['user']['id'])) { header('Location: dashboard.php'); exit; }
$err = $msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm'] ?? '';
    if (!preg_match('/^[A-Za-z0-9_.-]{3,30}$/', $username)) {
        $err = 'Username must be 3–30 characters and use only letters, numbers, dots, underscores or hyphens.';
    } elseif (strlen($password) < 8) {
        $err = 'Password must be at least 8 characters.';
    } elseif ($password !== $confirm) {
        $err = 'Passwords do not match.';
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO users(username,password,status,role) VALUES(?,?,'pending','user')");
            $stmt->execute([$username, password_hash($password, PASSWORD_DEFAULT)]);
            $msg = 'Registration successful. Wait for administrator approval before signing in.';
        } catch (PDOException $e) {
            $err = 'That username is already taken.';
        }
    }
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Register | Inventory System</title><link rel="stylesheet" href="assets/style.css"></head>
<body class="auth"><main class="card"><div class="mark">IS</div><small>INVENTORY MANAGEMENT</small><h1>Create account</h1><p>Register and wait for administrator approval.</p>
<?php if ($err): ?><div class="alert"><?=e($err)?></div><?php endif; ?><?php if ($msg): ?><div class="success"><?=e($msg)?></div><?php endif; ?>
<form method="post"><input type="hidden" name="csrf" value="<?=e(csrf())?>"><label>Username</label><input name="username" minlength="3" maxlength="30" autocomplete="username" required><label>Password</label><input type="password" name="password" minlength="8" autocomplete="new-password" required><label>Confirm password</label><input type="password" name="confirm" minlength="8" autocomplete="new-password" required><button>Register</button></form>
<p class="center">Already registered? <a href="index.php">Sign in</a></p></main></body></html>
