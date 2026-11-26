<?php
// ---- Local MySQL settings (Laragon uses port 3307) ----
$host = "127.0.0.1";
$port = "3307";
$db_user = "root";
$db_pass = "";
$db_name = "barrier_report_db";
$debug = true;       // shows errors while testing
$setup_key = '';     // empty = setup.php only works on this computer

// ---- Online hosting: config/hosting.php (not in git) overrides the values above ----
$is_local = (bool)preg_match('/(^|\.)(localhost|test)$|^127\.0\.0\.1$|^\[?::1\]?$/', preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST'] ?? 'localhost'));
if (!$is_local && is_file(__DIR__ . '/hosting.php')) require __DIR__ . '/hosting.php';

define('DEBUG', $debug);
define('SETUP_KEY', $setup_key);

$IS_API = (bool)preg_match('#/(api/|admin/api\.php)#', str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? ''));
error_reporting(E_ALL);
ini_set('display_errors', ($IS_API || !DEBUG) ? '0' : '1');   // never print PHP notices into JSON replies
ini_set('log_errors', '1');

function fail_page($msg, $code = 500) {
    global $IS_API;
    http_response_code($code);
    if ($IS_API) { header('Content-Type: application/json'); echo json_encode(['error' => $msg]); exit; }
    echo '<!DOCTYPE html><meta charset="utf-8"><title>Error</title><body style="font-family:system-ui,sans-serif;max-width:640px;margin:60px auto;padding:0 20px">'
       . '<h2>Something went wrong</h2><p>' . htmlspecialchars($msg) . '</p><p><a href="' . htmlspecialchars(base_url()) . 'setup.php">Run the system check</a></p></body>';
    exit;
}
set_exception_handler(function ($e) { error_log((string)$e); fail_page(DEBUG ? $e->getMessage() : 'Server error. Please try again.'); });

if (session_status() === PHP_SESSION_NONE) session_start();
date_default_timezone_set('Asia/Manila');

if (!defined('SKIP_DB')) {
    try {
        $pdo = new PDO("mysql:host=$host;port=$port;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        try { $pdo->exec("SET time_zone = '+08:00'"); } catch (Exception $e) {}   // keep NOW() in Philippine time on any host
    } catch (PDOException $e) {
        fail_page('Cannot connect to the database: ' . $e->getMessage() . ' (check host, port, user, password and database name in config/server.php)');
    }
}

// ---- Edit these to your barangay ----
const BRGY_NAME   = 'Barangay Government';
const BRGY_HOTLINE = '(000) 000-0000';
const BRGY_HOURS  = 'Mon-Fri, 8:00 AM - 5:00 PM';
const BRGY_ADDR   = 'Barangay Hall';

const RESPONSE_DAYS = 3; // barangay promise: acknowledge every concern within this many days

const STATUSES = ['Received', 'Acknowledged', 'Action Ongoing', 'Resolved'];
const CATEGORIES = [
    'Physical'      => 'Physical (roads, ramps, buildings)',
    'Communication' => 'Communication & treatment',
    'Service'       => 'Barangay services',
    'Other'         => 'Something else',
];
// category => type => [default urgency, default team id]
const TYPES = [
    'Physical' => [
        'Broken Sidewalk' => ['High', 1], 'Blocked Ramp' => ['High', 3], 'Uneven Path' => ['Medium', 2],
        'High Curb' => ['Medium', 2], 'Dim Lighting' => ['Low', 4], 'No Ramp or Handrail' => ['High', 2],
        'Inaccessible Restroom' => ['Medium', 2],
    ],
    'Communication' => [
        'Disrespectful or Discriminating Treatment' => ['High', 8], 'No Sign Language or Interpreter' => ['Medium', 5],
        'Information Not in Accessible Format' => ['Medium', 6], 'Not Listened To or Ignored' => ['Medium', 8],
    ],
    'Service' => [
        'Service Counter Not Accessible' => ['Medium', 6], 'Long Wait for PWD or Senior' => ['Medium', 6],
        'Benefit or ID Concern' => ['Medium', 5], 'Health or Social Service Concern' => ['Medium', 7],
    ],
    'Other' => ['Other Concern' => ['Medium', 6]],
];
const DISABILITIES = ['Mobility / Physical', 'Visual', 'Hearing / Speech', 'Intellectual / Learning', 'Psychosocial', 'Chronic Illness', 'Senior Citizen', 'Other'];

function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function rid($id) { return 'BR-' . str_pad((string)$id, 5, '0', STR_PAD_LEFT); }
function json_out($data, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json');
    header('Cache-Control: no-store');
    echo json_encode($data);
    exit;
}
function is_admin() { return !empty($_SESSION['admin_id']); }
function base_url() {
    $dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
    $dir = preg_replace('#/(auth|api|includes|admin)$#', '', $dir);
    return rtrim($dir, '/') . '/';
}
function require_admin($json = false) {
    if (is_admin()) return;
    if ($json) json_out(['error' => 'Unauthorized'], 401);
    header('Location: ' . base_url() . 'admin/login.php');
    exit;
}
function csrf() {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
    return $_SESSION['csrf'];
}
?>
