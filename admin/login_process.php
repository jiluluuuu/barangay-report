<?php
require_once __DIR__ . '/../config/server.php';
function back($m) { $_SESSION['login_error'] = $m; header('Location: login.php'); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) back('Invalid request. Please try again.');

// simple brute-force limit: 5 tries, then 60s lock
$_SESSION['tries'] = $_SESSION['tries'] ?? 0;
if ($_SESSION['tries'] >= 5 && time() < ($_SESSION['lock'] ?? 0)) back('Too many attempts. Wait a minute.');
if (time() >= ($_SESSION['lock'] ?? 0)) { /* lock expired */ if ($_SESSION['tries'] >= 5) $_SESSION['tries'] = 0; }

$u = trim($_POST['username'] ?? ''); $p = $_POST['password'] ?? '';
$st = $pdo->prepare("SELECT * FROM users WHERE username=?"); $st->execute([$u]); $user = $st->fetch();

$ok = false;
if ($user) {
    if ($user['password_hash'] === 'SETUP' && $u === 'admin' && $p === 'admin123') { // first-run: hash the default password
        $pdo->prepare("UPDATE users SET password_hash=? WHERE id=?")->execute([password_hash($p, PASSWORD_DEFAULT), $user['id']]);
        $ok = true;
    } else $ok = password_verify($p, $user['password_hash']);
}
if (!$ok) { $_SESSION['tries']++; $_SESSION['lock'] = time() + 60; back('Invalid username or password.'); }

session_regenerate_id(true);
$_SESSION['admin_id'] = $user['id']; $_SESSION['tries'] = 0;
header('Location: dashboard.php');
