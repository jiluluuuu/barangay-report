<?php
require_once __DIR__ . '/icons.php';
// Admin shell (used only by /admin pages). admin_head('Title','dashboard|reports|teams'); ... admin_foot($js);
function admin_head($title, $active, $sub = '') {
    require_admin();
    $b = base_url(); ?>
<!DOCTYPE html>
<html lang="en"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow"><meta name="csrf" content="<?= e(csrf()) ?>"><meta name="base" content="<?= e($b) ?>">
<title><?= e($title) ?> - Barangay Admin Portal</title>
<link rel="stylesheet" href="<?= $b ?>assets/css/style.css?v=<?= filemtime(__DIR__ . '/../assets/css/style.css') ?>">
<link rel="stylesheet" href="<?= $b ?>assets/css/admin.css?v=<?= filemtime(__DIR__ . '/../assets/css/admin.css') ?>">
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
</head><body class="admin <?= $active === 'dashboard' ? 'fit' : '' ?>">
<?php sprite(); ?>
<header class="topbar">
  <div class="brand"><img src="<?= $b ?>images/bgy.jpg" alt="" class="logo" onerror="this.style.display='none'"><span>Barangay Accessibility &amp; Barrier Report System</span></div>
  <div class="top-right">
    <div class="bell-wrap"><button id="bell" class="bell" aria-label="Notifications"><?= ic('bell') ?><span id="bellCount" class="badge" hidden>0</span></button>
      <div id="bellPanel" class="bell-panel" hidden><div class="bell-head"><b>Notifications</b><a href="#" id="markRead">Mark all read</a></div><div id="bellList"></div></div></div>
    <span>Staff</span><span class="sep">|</span><a href="<?= $b ?>admin/logout.php" class="lo"><?= ic('logout') ?>Logout</a>
  </div>
</header>
<div class="shell">
  <nav class="side">
    <a href="<?= $b ?>admin/dashboard.php" class="<?= $active==='dashboard'?'active':'' ?>"><?= ic('dashboard') ?>Dashboard</a>
    <a href="<?= $b ?>admin/reports.php" class="<?= $active==='reports'?'active':'' ?>"><?= ic('reports') ?>Barrier Reports</a>
    <a href="<?= $b ?>admin/teams.php" class="<?= $active==='teams'?'active':'' ?>"><?= ic('teams') ?>Barangay Offices</a>
    <div class="side-note">Resident site<br><a href="<?= $b ?>index.php" target="_blank">Open public pages</a></div>
  </nav>
  <main class="content">
  <div class="pagehead"><h1 class="page"><?= e($title) ?></h1><small><?= e($sub ?: date('l, F j, Y')) ?></small></div>
<?php }
function admin_foot($js = '') { $b = base_url(); ?>
  <div class="adminfoot"><?= e(BRGY_NAME) ?> &middot; Admin Portal &middot; Updates refresh automatically</div>
  </main>
</div>
<div id="toasts" class="toasts"></div>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="<?= $b ?>assets/js/app.js"></script>
<script>initAdminLive();</script>
<script><?= $js ?></script>
</body></html>
<?php } ?>
