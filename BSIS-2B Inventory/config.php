<?php
// Database connection and shared security helpers.
if (session_status() === PHP_SESSION_NONE) {
    $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    session_set_cookie_params([
        'lifetime' => 0, 'path' => '/', 'secure' => $secure,
        'httponly' => true, 'samesite' => 'Lax'
    ]);
    session_start();
}

$host = 'localhost';
$db = 'inventory_system';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$db;charset=utf8mb4",
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (PDOException $e) {
    http_response_code(500);
    exit('Database connection failed. Check config.php and import database.sql.');
}

function e($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function csrf(): string {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function verify_csrf(): void {
    $token = $_POST['csrf'] ?? '';
    if (!is_string($token) || !hash_equals($_SESSION['csrf'] ?? '', $token)) {
        http_response_code(419);
        exit('Invalid or expired form token. Refresh the page and try again.');
    }
}

function login_required(): void {
    if (empty($_SESSION['user']['id'])) {
        header('Location: index.php');
        exit;
    }
}

function admin_required(): void {
    login_required();
    if (($_SESSION['user']['role'] ?? '') !== 'admin') {
        http_response_code(403);
        exit('Access denied. Administrator permission is required.');
    }
}
