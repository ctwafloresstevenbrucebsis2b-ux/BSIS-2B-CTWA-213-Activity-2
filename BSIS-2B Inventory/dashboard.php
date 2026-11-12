<?php
require 'config.php';
login_required();
$isAdmin = ($_SESSION['user']['role'] ?? '') === 'admin';
$msg = $err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    try {
        // Product creation, editing and deletion are administrator-only.
        if (in_array($action, ['add_product', 'edit_product', 'delete_product'], true)) {
            admin_required();
            if ($action === 'add_product' || $action === 'edit_product') {
                $name = trim($_POST['name'] ?? '');
                $description = trim($_POST['description'] ?? '');
                $quantity = filter_var($_POST['quantity'] ?? null, FILTER_VALIDATE_INT);
                $price = filter_var($_POST['price'] ?? null, FILTER_VALIDATE_FLOAT);
                if ($name === '' || strlen($name) > 150 || $quantity === false || $quantity < 0 || $price === false || $price < 0) {
                    throw new Exception('Enter valid product details. Quantity and price cannot be negative.');
                }
                if ($action === 'add_product') {
                    $stmt = $pdo->prepare('INSERT INTO products(name,description,quantity,price,created_by) VALUES(?,?,?,?,?)');
                    $stmt->execute([$name, $description, $quantity, $price, $_SESSION['user']['id']]);
                    $msg = 'Product added successfully.';
                } else {
                    $stmt = $pdo->prepare('UPDATE products SET name=?,description=?,quantity=?,price=? WHERE id=?');
                    $stmt->execute([$name, $description, $quantity, $price, (int)($_POST['id'] ?? 0)]);
                    $msg = $stmt->rowCount() ? 'Product updated successfully.' : 'No product changes were made.';
                }
            } else {
                $stmt = $pdo->prepare('DELETE FROM products WHERE id=?');
                $stmt->execute([(int)($_POST['id'] ?? 0)]);
                $msg = $stmt->rowCount() ? 'Product deleted successfully.' : 'Product was not found.';
            }
        } elseif (in_array($action, ['approve_user', 'reject_user', 'change_role', 'delete_user'], true)) {
            admin_required();
            $targetId = (int)($_POST['id'] ?? 0);
            if ($targetId === (int)$_SESSION['user']['id']) {
                throw new Exception('You cannot change or delete your own administrator account here.');
            }
            if ($action === 'approve_user' || $action === 'reject_user') {
                $status = $action === 'approve_user' ? 'approved' : 'rejected';
                $stmt = $pdo->prepare("UPDATE users SET status=? WHERE id=? AND role='user'");
                $stmt->execute([$status, $targetId]);
                $msg = 'User status updated.';
            } elseif ($action === 'change_role') {
                $role = $_POST['role'] ?? '';
                if (!in_array($role, ['admin', 'user'], true)) throw new Exception('Invalid role selected.');
                $stmt = $pdo->prepare('UPDATE users SET role=? WHERE id=?');
                $stmt->execute([$role, $targetId]);
                $msg = 'User role updated.';
            } else {
                $stmt = $pdo->prepare('SELECT role FROM users WHERE id=?');
                $stmt->execute([$targetId]);
                $target = $stmt->fetch();
                if ($target && $target['role'] === 'admin') {
                    $admins = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='admin'")->fetchColumn();
                    if ($admins <= 1) throw new Exception('The last administrator cannot be deleted.');
                }
                $stmt = $pdo->prepare('DELETE FROM users WHERE id=?');
                $stmt->execute([$targetId]);
                $msg = $stmt->rowCount() ? 'User account deleted.' : 'User was not found.';
            }
        }
    } catch (Throwable $e) {
        $err = $e instanceof PDOException ? 'The database could not complete that action.' : $e->getMessage();
    }
}

$edit = null;
if ($isAdmin && isset($_GET['edit'])) {
    $stmt = $pdo->prepare('SELECT * FROM products WHERE id=?');
    $stmt->execute([(int)$_GET['edit']]);
    $edit = $stmt->fetch();
}
$products = $pdo->query('SELECT p.*,u.username AS creator FROM products p LEFT JOIN users u ON u.id=p.created_by ORDER BY p.id DESC')->fetchAll();
$pending = $isAdmin ? $pdo->query("SELECT id,username,created_at FROM users WHERE status='pending' AND role='user' ORDER BY id")->fetchAll() : [];
$users = $isAdmin ? $pdo->query('SELECT id,username,status,role,created_at FROM users ORDER BY id DESC')->fetchAll() : [];
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Dashboard | Inventory System</title><link rel="stylesheet" href="assets/style.css"></head><body>
<header><b>▣ Inventory System</b><span><?=e($_SESSION['user']['username'])?> · <?=e($isAdmin ? 'Admin' : 'User')?> <a href="logout.php">Logout</a></span></header>
<main class="wrap"><h1>Dashboard</h1><p>Manage and view inventory records.</p>
<?php if ($msg): ?><div class="success"><?=e($msg)?></div><?php endif; ?><?php if ($err): ?><div class="alert"><?=e($err)?></div><?php endif; ?>
<?php if ($isAdmin): ?>
<section><h2>Pending registrations</h2>
<?php if (!$pending): ?><p>No pending users.</p><?php else: ?><div class="scroll"><table><tr><th>Username</th><th>Registered</th><th>Actions</th></tr>
<?php foreach ($pending as $u): ?><tr><td><?=e($u['username'])?></td><td><?=e($u['created_at'])?></td><td>
<form class="inline" method="post"><input type="hidden" name="csrf" value="<?=e(csrf())?>"><input type="hidden" name="id" value="<?=$u['id']?>"><button name="action" value="approve_user">Approve</button><button class="red" name="action" value="reject_user">Reject</button></form>
</td></tr><?php endforeach; ?></table></div><?php endif; ?></section>

<section><h2>User management</h2><p>Change account roles or remove accounts. Your current admin account and the last admin account are protected.</p><div class="scroll"><table><tr><th>Username</th><th>Role</th><th>Status</th><th>Created</th><th>Actions</th></tr>
<?php foreach ($users as $u): ?><tr><td><?=e($u['username'])?><?=((int)$u['id'] === (int)$_SESSION['user']['id']) ? ' (you)' : ''?></td><td><?=e($u['role'])?></td><td><?=e($u['status'])?></td><td><?=e($u['created_at'])?></td><td>
<?php if ((int)$u['id'] !== (int)$_SESSION['user']['id']): ?>
<form class="inline" method="post"><input type="hidden" name="csrf" value="<?=e(csrf())?>"><input type="hidden" name="id" value="<?=$u['id']?>"><select name="role"><option value="user" <?=$u['role']==='user'?'selected':''?>>User</option><option value="admin" <?=$u['role']==='admin'?'selected':''?>>Admin</option></select><button name="action" value="change_role">Save role</button></form>
<form class="inline" method="post" onsubmit="return confirm('Permanently delete this user account?')"><input type="hidden" name="csrf" value="<?=e(csrf())?>"><input type="hidden" name="id" value="<?=$u['id']?>"><button class="red" name="action" value="delete_user">Delete</button></form>
<?php else: ?><span class="muted">Protected</span><?php endif; ?>
</td></tr><?php endforeach; ?></table></div></section>
<?php endif; ?>

<?php if ($isAdmin): ?>
<section><h2><?= $edit ? 'Edit product' : 'Add product' ?></h2>
<form method="post" class="grid"><input type="hidden" name="csrf" value="<?=e(csrf())?>"><input type="hidden" name="action" value="<?=$edit?'edit_product':'add_product'?>">
<?php if ($edit): ?><input type="hidden" name="id" value="<?=$edit['id']?>"><?php endif; ?>
<label>Product name<input name="name" maxlength="150" required value="<?=e($edit['name'] ?? '')?>"></label>
<label>Description<input name="description" value="<?=e($edit['description'] ?? '')?>"></label>
<label>Quantity<input type="number" min="0" name="quantity" required value="<?=e($edit['quantity'] ?? 0)?>"></label>
<label>Price (₱)<input type="number" min="0" step="0.01" name="price" required value="<?=e($edit['price'] ?? '0.00')?>"></label>
<button><?=$edit?'Save changes':'Add product'?></button><?php if ($edit): ?><a href="dashboard.php">Cancel</a><?php endif; ?></form></section>
<?php else: ?><section><h2>Access level: User</h2><p>You can view inventory records. Product creation, editing, deletion, and user management are restricted to administrators.</p></section><?php endif; ?>

<section><h2>Products (<?=count($products)?>)</h2><div class="scroll"><table><tr><th>Name</th><th>Description</th><th>Quantity</th><th>Price</th><th>Created by</th><?php if ($isAdmin): ?><th>Actions</th><?php endif; ?></tr>
<?php foreach ($products as $p): ?><tr><td><?=e($p['name'])?></td><td><?=e($p['description'])?></td><td><?=e($p['quantity'])?></td><td>₱<?=number_format((float)$p['price'],2)?></td><td><?=e($p['creator'] ?? '—')?></td>
<?php if ($isAdmin): ?><td><a href="?edit=<?=$p['id']?>">Edit</a> <form class="inline" method="post" onsubmit="return confirm('Delete this product?')"><input type="hidden" name="csrf" value="<?=e(csrf())?>"><input type="hidden" name="id" value="<?=$p['id']?>"><button class="red" name="action" value="delete_product">Delete</button></form></td><?php endif; ?></tr><?php endforeach; ?>
<?php if (!$products): ?><tr><td colspan="<?=$isAdmin?6:5?>">No products found.</td></tr><?php endif; ?></table></div></section>
<p class="foot">Inventory System · Access-controlled dashboard</p></main></body></html>
