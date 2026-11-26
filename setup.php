<?php
/* SYSTEM CHECK & INSTALLER
 * Open http://barrier-report-system.test/setup.php  (works only on the same computer).
 * On web hosting: set SETUP_KEY in config/server.php, open setup.php?key=YOUR_KEY, then DELETE this file. */
define('SKIP_DB', true);
require_once __DIR__ . '/config/server.php';

$local = in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true);
$key = (string)($_GET['key'] ?? $_POST['key'] ?? '');
if (!$local && !(SETUP_KEY !== '' && hash_equals(SETUP_KEY, $key))) {
    http_response_code(403);
    exit('Setup is only available on the same computer, or with ?key=... when SETUP_KEY is set in config/server.php.');
}

function conn($dbname = null) {
    global $host, $port, $db_user, $db_pass;
    $dsn = "mysql:host=$host;port=$port;charset=utf8mb4" . ($dbname ? ";dbname=$dbname" : '');
    $p = new PDO($dsn, $db_user, $db_pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
    try { $p->exec("SET time_zone = '+08:00'"); } catch (Exception $e) {}
    return $p;
}
function run_sql_file($pdo, $file) {
    $sql = preg_replace('/^\s*--.*$/m', '', file_get_contents($file));
    $n = 0;
    foreach (preg_split('/;\s*(?:\r?\n|$)/', $sql) as $stmt) {
        $stmt = trim($stmt);
        if ($stmt === '' || preg_match('/^(CREATE DATABASE|USE)\b/i', $stmt)) continue;
        $pdo->exec($stmt); $n++;
    }
    return $n;
}

$msg = ''; $err = '';
$pdo = null; $server = null; $dbExists = false; $connErr = '';
// Connect straight to the database first: shared hosts (InfinityFree etc.) create it for you
// and do not allow SHOW DATABASES / CREATE DATABASE.
try {
    $pdo = conn($db_name); $server = $pdo; $dbExists = true;
} catch (Throwable $e) {
    try {
        $server = conn();   // local: database may simply not exist yet
        $dbExists = (bool)$server->query("SHOW DATABASES LIKE " . $server->quote($db_name))->fetch();
        if ($dbExists) $pdo = conn($db_name);
    } catch (Throwable $e2) { $connErr = $e->getMessage(); }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $server) {
    $do = $_POST['do'] ?? '';
    try {
        if (!$dbExists) { $server->exec("CREATE DATABASE IF NOT EXISTS `$db_name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"); $dbExists = true; }
        $pdo = conn($db_name);
        if ($do === 'create') { $msg = 'Tables created or checked (' . run_sql_file($pdo, __DIR__ . '/database.sql') . ' statements).'; }
        elseif ($do === 'reset') {
            if (empty($_POST['sure'])) throw new Exception('Tick the box to confirm. Reset deletes every report.');
            $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
            foreach (['notifications', 'report_updates', 'reports', 'teams', 'users'] as $t) $pdo->exec("DROP TABLE IF EXISTS `$t`");
            $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
            $msg = 'All tables were deleted and rebuilt (' . run_sql_file($pdo, __DIR__ . '/database.sql') . ' statements).';
        }
        elseif ($do === 'demo') { $msg = 'Demo data loaded (' . run_sql_file($pdo, __DIR__ . '/sample-data.sql') . ' statements).'; }
        elseif ($do === 'adminpw') { $pdo->exec("UPDATE users SET password_hash='SETUP' WHERE username='admin'"); $msg = 'Admin password reset. Log in with admin / admin123, then change it.'; }
    } catch (Throwable $e) { $err = $e->getMessage(); }
}

// ---------- checks ----------
$checks = [];
$add = function ($ok, $label, $detail = '') use (&$checks) { $checks[] = [$ok, $label, $detail]; };
$add(version_compare(PHP_VERSION, '7.4', '>='), 'PHP version', PHP_VERSION . ' (needs 7.4 or newer)');
foreach (['pdo_mysql', 'fileinfo', 'mbstring', 'json'] as $x) $add(extension_loaded($x), "PHP extension: $x", extension_loaded($x) ? 'loaded' : 'NOT loaded. Enable it in php.ini');
$up = __DIR__ . '/uploads';
if (!is_dir($up)) @mkdir($up, 0775, true);
$add(is_dir($up) && is_writable($up), 'Folder uploads/ is writable', is_writable($up) ? 'yes' : 'No. Give the web server write permission to uploads/');
$add(true, 'PHP upload limits', 'upload_max_filesize ' . ini_get('upload_max_filesize') . ', post_max_size ' . ini_get('post_max_size') . ' (photos are shrunk in the browser, so small limits still work)');
$add(true, 'Logo images/bgy.jpg', file_exists(__DIR__ . '/images/bgy.jpg') ? 'found' : 'missing (a round "B" shows instead). Add your logo');
$add(!$connErr, "MySQL server $host:$port as '$db_user'", $connErr ?: 'connected');
$add($dbExists, "Database `$db_name`", $dbExists ? 'exists' : 'does not exist yet. Press "Create database and tables"');

$expect = [
    'users' => ['id', 'username', 'password_hash', 'full_name'],
    'teams' => ['id', 'name', 'status', 'note'],
    'reports' => ['id', 'tracking_code', 'category', 'type', 'description', 'location', 'lat', 'lng', 'pwd_related', 'reported_for', 'affected_groups', 'urgency', 'status', 'reporter_name', 'contact', 'photo', 'team_id', 'target_date', 'acknowledged_at', 'feedback_rating', 'feedback_note', 'created_at', 'updated_at', 'resolved_at'],
    'report_updates' => ['id', 'report_id', 'status', 'note', 'created_at'],
    'notifications' => ['id', 'report_id', 'message', 'created_at'],
];
$schemaOk = false; $counts = [];
if ($pdo) {
    $schemaOk = true;
    foreach ($expect as $t => $cols) {
        $exists = (bool)$pdo->query("SHOW TABLES LIKE " . $pdo->quote($t))->fetch();
        if (!$exists) { $add(false, "Table `$t`", 'missing'); $schemaOk = false; continue; }
        $have = array_column($pdo->query("SHOW COLUMNS FROM `$t`")->fetchAll(), 'Field');
        $miss = array_diff($cols, $have);
        if ($miss) { $add(false, "Table `$t`", 'old version, missing columns: ' . implode(', ', $miss) . '. Use "Reset tables"'); $schemaOk = false; continue; }
        $counts[$t] = (int)$pdo->query("SELECT COUNT(*) FROM `$t`")->fetchColumn();
        $add(true, "Table `$t`", $counts[$t] . ' rows');
    }
    if ($schemaOk) {
        $c = $pdo->query("SHOW COLUMNS FROM reports LIKE 'status'")->fetch();
        if (stripos($c['Type'], 'Received') === false) { $add(false, 'reports.status values', 'old values (Pending/Investigating). Use "Reset tables"'); $schemaOk = false; }
        $c = $pdo->query("SHOW COLUMNS FROM reports LIKE 'type'")->fetch();
        if (stripos($c['Type'], 'enum') === 0) { $add(false, 'reports.type column', 'old fixed list. Use "Reset tables"'); $schemaOk = false; }
    }
    if ($schemaOk) {
        try { // the same inserts the report form makes, rolled back so nothing is saved
            $pdo->beginTransaction();
            $pdo->prepare("INSERT INTO reports (category,type,description,location,lat,lng,pwd_related,reported_for,affected_groups,urgency,reporter_name,contact,photo,team_id) VALUES ('Physical','Broken Sidewalk','test','Setup test',NULL,NULL,0,NULL,NULL,'High','Setup',NULL,NULL,1)")->execute();
            $id = $pdo->lastInsertId();
            $pdo->prepare("UPDATE reports SET tracking_code=? WHERE id=?")->execute(['BR-TEST-0000', $id]);
            $pdo->prepare("INSERT INTO report_updates (report_id,status,note) VALUES (?,?,?)")->execute([$id, 'Received', 'test']);
            $pdo->prepare("INSERT INTO notifications (report_id,message) VALUES (?,?)")->execute([$id, 'test']);
            $pdo->rollBack();
            $add(true, 'Test: submit a report', 'works (nothing was saved)');
        } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); $add(false, 'Test: submit a report', $e->getMessage() . ($counts['teams'] === 0 ? ' (the offices table is empty: press "Create database and tables")' : '')); $schemaOk = false; }
        $u = $pdo->query("SELECT password_hash FROM users WHERE username='admin'")->fetchColumn();
        $add($u !== false, 'Admin account', $u === false ? 'missing. Press "Create database and tables"' : ($u === 'SETUP' ? 'ready: log in with admin / admin123 (it is hashed on first login)' : 'exists (password already set)'));
    }
}
$allOk = !array_filter($checks, fn($c) => !$c[0]);
$k = $key !== '' ? '<input type="hidden" name="key" value="' . htmlspecialchars($key) . '">' : '';
?>
<!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow">
<title>System check</title>
<style>
body{font-family:'Segoe UI',system-ui,sans-serif;background:#eef2f7;margin:0;color:#1a1f2b}
.w{max-width:820px;margin:30px auto;padding:0 16px}
h1{margin:0 0 4px;font-size:24px}.sub{color:#5b6778;margin:0 0 18px}
.card{background:#fff;border-radius:8px;box-shadow:0 2px 8px rgba(20,40,70,.1);padding:6px 16px;margin-bottom:16px}
.row{display:flex;gap:12px;padding:10px 0;border-bottom:1px solid #eef0f4;font-size:14px}.row:last-child{border:0}
.tag{flex:none;width:44px;text-align:center;border-radius:4px;font-size:12px;font-weight:700;padding:2px 0;height:max-content}
.ok{background:#dff3e3;color:#17612a}.bad{background:#fde2de;color:#a02318}
.row b{display:block}.row span{color:#5b6778;word-break:break-word}
.box{padding:12px 16px;border-radius:8px;margin-bottom:14px;font-size:14px}.m{background:#dff3e3;color:#17612a}.e{background:#fde2de;color:#a02318}
form{display:inline-block;margin:4px 8px 4px 0}
button{background:#1d4e79;color:#fff;border:0;border-radius:6px;padding:10px 16px;font:inherit;cursor:pointer}button.warn{background:#b42318}button.alt{background:#2e7d32}
.acts{background:#fff;border-radius:8px;box-shadow:0 2px 8px rgba(20,40,70,.1);padding:14px 16px}.acts p{margin:0 0 8px;font-size:13px;color:#5b6778}
a{color:#1d4e79}
</style></head><body><div class="w">
<h1>System check</h1><p class="sub">Barangay Accessibility &amp; Barrier Report System. Checks PHP, MySQL, the tables and a test report.</p>
<?php if ($msg): ?><div class="box m"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="box e"><?= htmlspecialchars($err) ?></div><?php endif; ?>
<div class="box <?= $allOk ? 'm' : 'e' ?>"><b><?= $allOk ? 'Everything is working.' : 'Some checks failed.' ?></b>
<?= $allOk ? ' Open the <a href="index.php">resident site</a> or the <a href="admin/login.php">admin portal</a>. Delete setup.php before going public.' : ' Fix the red items below, then reload this page.' ?></div>
<div class="card"><?php foreach ($checks as [$ok, $label, $detail]): ?>
  <div class="row"><span class="tag <?= $ok ? 'ok' : 'bad' ?>"><?= $ok ? 'OK' : 'FIX' ?></span><div><b><?= htmlspecialchars($label) ?></b><span><?= htmlspecialchars($detail) ?></span></div></div>
<?php endforeach; ?></div>
<?php if ($server): ?><div class="acts"><p>Actions</p>
  <form method="post"><?= $k ?><input type="hidden" name="do" value="create"><button>Create database and tables</button></form>
  <form method="post"><?= $k ?><input type="hidden" name="do" value="demo"><button class="alt">Load demo data (12 reports)</button></form>
  <form method="post"><?= $k ?><input type="hidden" name="do" value="adminpw"><button class="alt">Reset admin password</button></form>
  <form method="post" onsubmit="return confirm('This deletes ALL reports and rebuilds the tables. Continue?')"><?= $k ?><input type="hidden" name="do" value="reset">
    <label style="font-size:13px"><input type="checkbox" name="sure" value="1"> I understand this deletes all data</label> <button class="warn">Reset tables</button></form>
</div><?php endif; ?>
</div></body></html>
