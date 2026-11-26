<?php
// Public tracking endpoint: ?codes=A,B (list) or ?code=A&full=1 (with timeline)
require_once __DIR__ . '/../config/server.php';
$codes = array_filter(array_map('trim', explode(',', $_GET['codes'] ?? ($_GET['code'] ?? ''))));
$codes = array_slice(array_unique(array_map('strtoupper', $codes)), 0, 20);
if (!$codes) json_out([]);
$in = implode(',', array_fill(0, count($codes), '?'));
$st = $pdo->prepare("SELECT r.id,r.tracking_code,r.category,r.location,r.type,r.description,r.status,r.target_date,r.feedback_rating,r.created_at,r.updated_at,t.name AS team,
  (SELECT note FROM report_updates u WHERE u.report_id=r.id ORDER BY u.id DESC LIMIT 1) AS note
  FROM reports r LEFT JOIN teams t ON t.id=r.team_id WHERE r.tracking_code IN ($in)");
$st->execute($codes);
$out = [];
foreach ($st->fetchAll() as $r) {
    $row = ['code' => $r['tracking_code'], 'category' => $r['category'], 'location' => $r['location'], 'type' => $r['type'], 'description' => $r['description'],
            'status' => $r['status'], 'team' => $r['team'], 'note' => $r['note'], 'target_date' => $r['target_date'],
            'feedback' => $r['feedback_rating'], 'created_at' => $r['created_at'], 'updated_at' => $r['updated_at']];
    if (!empty($_GET['full'])) {
        $u = $pdo->prepare("SELECT status,note,created_at FROM report_updates WHERE report_id=? ORDER BY id DESC");
        $u->execute([$r['id']]);
        $row['timeline'] = $u->fetchAll();
    }
    $out[] = $row;
}
json_out($out);
