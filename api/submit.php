<?php
require_once __DIR__ . '/../config/server.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(['error' => 'POST only'], 405);

if (empty($_POST) && empty($_FILES) && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0)
    json_out(['error' => 'The photo is too large for this server (limit ' . ini_get('post_max_size') . '). Please use a smaller photo.'], 413);

$category = $_POST['category'] ?? '';
$type = $_POST['type'] ?? '';
if (!isset(TYPES[$category][$type])) json_out(['error' => 'Please choose what kind of concern this is.'], 422);

$location = trim($_POST['location'] ?? '');
$desc = trim($_POST['description'] ?? '');
$name = trim($_POST['name'] ?? '') ?: 'Anonymous';
$contact = trim($_POST['contact'] ?? '') ?: null;
$lat = is_numeric($_POST['lat'] ?? null) ? (float)$_POST['lat'] : null;
$lng = is_numeric($_POST['lng'] ?? null) ? (float)$_POST['lng'] : null;

if ($location === '') json_out(['error' => 'Please tell us where this happened.'], 422);
if ($category !== 'Physical' && mb_strlen($desc) < 10) json_out(['error' => 'Please describe what happened in a sentence or two.'], 422);
if (mb_strlen($location) > 160 || mb_strlen($name) > 80 || mb_strlen($desc) > 2000 || mb_strlen((string)$contact) > 80) json_out(['error' => 'Some text is too long.'], 422);

// PWD / affected person
$pwd = ($_POST['pwd'] ?? '') === '1';
$groups = array_values(array_intersect((array)($_POST['groups'] ?? []), DISABILITIES));
$forWhom = $pwd ? (($_POST['for_whom'] ?? '') === 'Someone else' ? 'Someone else' : 'Myself') : null;

$photo = null;
if (!empty($_FILES['photo']['name']) && $_FILES['photo']['error'] !== UPLOAD_ERR_OK)
    json_out(['error' => in_array($_FILES['photo']['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE]) ? 'The photo is too large for this server. Please use a smaller photo.' : 'The photo could not be uploaded. Please try again.'], 422);
if (!empty($_FILES['photo']['name'])) {
    if (!is_dir(__DIR__ . '/../uploads')) @mkdir(__DIR__ . '/../uploads', 0775, true);
    if ($_FILES['photo']['size'] > 5 * 1024 * 1024) json_out(['error' => 'Photo must be 5MB or smaller.'], 422);
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($_FILES['photo']['tmp_name']);
    $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$mime] ?? null;
    if (!$ext) json_out(['error' => 'Only JPG, PNG or WEBP photos are allowed.'], 422);
    $photo = 'rep_' . bin2hex(random_bytes(8)) . '.' . $ext;
    if (!move_uploaded_file($_FILES['photo']['tmp_name'], __DIR__ . '/../uploads/' . $photo)) json_out(['error' => 'Could not save photo.'], 500);
}

[$urgency, $team] = TYPES[$category][$type];
if ($pwd && $urgency === 'Low') $urgency = 'Medium';   // PWD-related concerns are never "low"

try {
$pdo->beginTransaction();
$pdo->prepare("INSERT INTO reports (category,type,description,location,lat,lng,pwd_related,reported_for,affected_groups,urgency,reporter_name,contact,photo,team_id)
               VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
    ->execute([$category, $type, $desc ?: null, $location, $lat, $lng, $pwd ? 1 : 0, $forWhom, $groups ? implode(', ', $groups) : null, $urgency, $name, $contact, $photo, $team]);
$id = (int)$pdo->lastInsertId();
$code = rid($id) . '-' . strtoupper(bin2hex(random_bytes(2)));
$pdo->prepare("UPDATE reports SET tracking_code=? WHERE id=?")->execute([$code, $id]);
$pdo->prepare("INSERT INTO report_updates (report_id,status,note) VALUES (?,?,?)")->execute([$id, 'Received', 'Your concern was received. The barangay will acknowledge it within ' . RESPONSE_DAYS . ' days.']);
$pdo->prepare("INSERT INTO notifications (report_id,message) VALUES (?,?)")
    ->execute([$id, "New $urgency concern " . rid($id) . ': ' . $type . ' - ' . $location . ($pwd ? ' (PWD-related)' : '')]);
$pdo->commit();
} catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
$u = $pdo->prepare("SELECT updated_at FROM reports WHERE id=?"); $u->execute([$id]);
json_out(['ok' => true, 'code' => $code, 'sig' => 'Received|' . $u->fetchColumn()]);
