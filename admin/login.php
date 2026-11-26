<?php
require_once __DIR__ . '/../config/server.php';
if (is_admin()) { header('Location: dashboard.php'); exit; }
$error = $_SESSION['login_error'] ?? ''; unset($_SESSION['login_error']);
?>
<!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow"><title>Barangay Admin Portal - Login</title><link rel="stylesheet" href="../assets/css/style.css"></head>
<body class="login">
<div class="login-box">
  <div class="login-l"><img src="../images/bgy.jpg" alt="Barangay logo" onerror="this.replaceWith(Object.assign(document.createElement('span'),{className:'login-seal',textContent:'B'}))"><h2>Barangay Admin Portal</h2></div>
  <form class="login-r" method="post" action="login_process.php">
    <h2>Login</h2>
    <?php if ($error): ?><div class="err"><?= e($error) ?></div><?php endif; ?>
    <input type="hidden" name="csrf" value="<?= e(csrf()) ?>">
    <label for="u">Username/E-mail</label><input type="text" id="u" name="username" required autocomplete="username">
    <label for="p">Password</label><input type="password" id="p" name="password" required autocomplete="current-password">
    <div class="terms"><input type="checkbox" id="t" required><label for="t" style="margin:0;font-weight:400">I agree to the <a href="#">Terms and Conditions</a> and the data privacy policy.</label></div>
    <button type="submit">LOGIN</button>
    <p style="text-align:center;margin:12px 0 0;font-size:13px"><a href="../index.php" style="color:#1d4e79">Back to site</a></p>
  </form>
</div></body></html>
