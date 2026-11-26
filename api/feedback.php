<?php
// Reporter tells the barangay whether the concern was really addressed.
require_once __DIR__ . '/../config/server.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(['error' => 'POST only'], 405);
$code = strtoupper(trim($_POST['code'] ?? ''));
$ans = $_POST['answer'] ?? '';
$note = mb_substr(trim($_POST['note'] ?? ''), 0, 150);
if (!in_array($ans, ['Yes', 'No'])) json_out(['error' => 'Invalid answer'], 422);
$st = $pdo->prepare("SELECT * FROM reports WHERE tracking_code=?"); $st->execute([$code]); $r = $st->fetch();
if (!$r || $r['status'] !== 'Resolved' || $r['feedback_rating']) json_out(['error' => 'Feedback is not available for this report.'], 409);

if ($ans === 'Yes') {
    $pdo->prepare("UPDATE reports SET feedback_rating='Yes', feedback_note=? WHERE id=?")->execute([$note ?: null, $r['id']]);
    $pdo->prepare("INSERT INTO report_updates (report_id,status,note) VALUES (?,?,?)")->execute([$r['id'], 'Resolved', 'Reporter confirmed the concern was addressed.' . ($note ? " \"$note\"" : '')]);
    $pdo->prepare("INSERT INTO notifications (report_id,message) VALUES (?,?)")->execute([$r['id'], rid($r['id']) . ' - reporter confirmed it was addressed']);
} else {
    $pdo->prepare("UPDATE reports SET status='Acknowledged', resolved_at=NULL, feedback_rating='No', feedback_note=? WHERE id=?")->execute([$note ?: null, $r['id']]);
    $pdo->prepare("INSERT INTO report_updates (report_id,status,note) VALUES (?,?,?)")->execute([$r['id'], 'Acknowledged', 'Reporter says this is not yet addressed' . ($note ? ": \"$note\"" : '.') . ' Case reopened.']);
    $pdo->prepare("INSERT INTO notifications (report_id,message) VALUES (?,?)")->execute([$r['id'], rid($r['id']) . ' REOPENED - reporter says it is not addressed']);
}
json_out(['ok' => true]);
