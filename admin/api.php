<?php
require_once __DIR__ . '/../config/server.php';
require_admin(true);
$action = $_GET['action'] ?? '';
$isPost = $_SERVER['REQUEST_METHOD'] === 'POST';
if ($isPost && !hash_equals($_SESSION['csrf'] ?? '', $_SERVER['HTTP_X_CSRF'] ?? '')) json_out(['error' => 'Bad CSRF token'], 403);

function report_rows($pdo, $where = '', $args = []) {
    $st = $pdo->prepare("SELECT r.*, t.name AS team FROM reports r LEFT JOIN teams t ON t.id=r.team_id $where ORDER BY r.id DESC");
    $st->execute($args);
    return array_map(function ($r) { $r['rid'] = rid($r['id']); return $r; }, $st->fetchAll());
}
function team_rows($pdo) {
    return $pdo->query("SELECT t.*, (SELECT COUNT(*) FROM reports r WHERE r.team_id=t.id AND r.status<>'Resolved') AS jobs FROM teams t ORDER BY t.id")->fetchAll();
}

switch ($action) {
    case 'poll':
        $items = $pdo->query("SELECT id,report_id,message,created_at FROM notifications ORDER BY id DESC LIMIT 15")->fetchAll();
        json_out(['items' => $items, 'latest' => $items ? (int)$items[0]['id'] : 0]);

    case 'stats':
        $s = $pdo->query("SELECT COUNT(*) total, COALESCE(SUM(status<>'Resolved'),0) active, COALESCE(SUM(status='Resolved'),0) resolved,
            COALESCE(SUM(status='Received'),0) waiting, COALESCE(SUM(pwd_related=1),0) pwd,
            COALESCE(SUM(urgency='High' AND status<>'Resolved'),0) high,
            COALESCE(SUM(status='Received' AND created_at < NOW() - INTERVAL " . RESPONSE_DAYS . " DAY),0) overdue,
            ROUND(AVG(TIMESTAMPDIFF(HOUR,created_at,acknowledged_at)),1) resp,
            COALESCE(SUM(feedback_rating='Yes'),0) fb_yes, COALESCE(SUM(feedback_rating='No'),0) fb_no FROM reports")->fetch();
        $cats = $pdo->query("SELECT category, COUNT(*) n FROM reports GROUP BY category")->fetchAll();
        $by = []; foreach ($pdo->query("SELECT status, COUNT(*) n FROM reports GROUP BY status") as $r) $by[$r['status']] = (int)$r['n'];
        $fl = []; foreach ($pdo->query("SELECT DATE(created_at) d, COUNT(*) n FROM reports WHERE created_at >= CURDATE() - INTERVAL 6 DAY GROUP BY d") as $r) $fl[$r['d']] = (int)$r['n'];
        $rs = []; foreach ($pdo->query("SELECT DATE(resolved_at) d, COUNT(*) n FROM reports WHERE resolved_at >= CURDATE() - INTERVAL 6 DAY GROUP BY d") as $r) $rs[$r['d']] = (int)$r['n'];
        $trend = [];
        for ($i = 6; $i >= 0; $i--) { $t = strtotime("-$i day"); $d = date('Y-m-d', $t); $trend[] = ['label' => date('D', $t), 'filed' => $fl[$d] ?? 0, 'resolved' => $rs[$d] ?? 0]; }
        $pins = $pdo->query("SELECT id,location,type,status,lat,lng FROM reports WHERE lat IS NOT NULL AND lng IS NOT NULL")->fetchAll();
        $notifs = $pdo->query("SELECT id,report_id,message,created_at FROM notifications ORDER BY id DESC LIMIT 20")->fetchAll();
        json_out(['s' => $s, 'cats' => $cats, 'by' => $by, 'trend' => $trend, 'notifs' => $notifs, 'resp_days' => RESPONSE_DAYS,
                  'pins' => array_map(fn($p) => $p + ['rid' => rid($p['id'])], $pins)]);

    case 'reports':
        $w = []; $a = [];
        if (in_array($_GET['urgency'] ?? '', ['Low', 'Medium', 'High'])) { $w[] = 'r.urgency=?'; $a[] = $_GET['urgency']; }
        if (isset(CATEGORIES[$_GET['category'] ?? ''])) { $w[] = 'r.category=?'; $a[] = $_GET['category']; }
        if (($_GET['pwd'] ?? '') === '1') { $w[] = 'r.pwd_related=1'; }
        if (!empty($_GET['team'])) { $w[] = 'r.team_id=?'; $a[] = (int)$_GET['team']; }
        json_out(['reports' => report_rows($pdo, $w ? 'WHERE ' . implode(' AND ', $w) : '', $a), 'teams' => team_rows($pdo)]);

    case 'detail':
        $r = report_rows($pdo, 'WHERE r.id=?', [(int)($_GET['id'] ?? 0)])[0] ?? null;
        if (!$r) json_out(['error' => 'Not found'], 404);
        $u = $pdo->prepare("SELECT status,note,created_at FROM report_updates WHERE report_id=? ORDER BY id DESC");
        $u->execute([$r['id']]);
        json_out(['report' => $r, 'timeline' => $u->fetchAll()]);

    case 'update': // status / team / urgency / target date + response to reporter
        $id = (int)($_POST['id'] ?? 0);
        $st = $_POST['status'] ?? '';
        $urg = $_POST['urgency'] ?? '';
        $team = (int)($_POST['team_id'] ?? 0) ?: null;
        $note = trim($_POST['note'] ?? '');
        $target = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_POST['target_date'] ?? '') ? $_POST['target_date'] : null;
        if (!in_array($st, STATUSES) || !in_array($urg, ['Low', 'Medium', 'High'])) json_out(['error' => 'Invalid values'], 422);
        $old = $pdo->prepare("SELECT * FROM reports WHERE id=?"); $old->execute([$id]); $old = $old->fetch();
        if (!$old) json_out(['error' => 'Not found'], 404);
        $pdo->prepare("UPDATE reports SET status=?, urgency=?, team_id=?, target_date=?,
              acknowledged_at=IF(?<>'Received', COALESCE(acknowledged_at,NOW()), acknowledged_at),
              resolved_at=IF(?='Resolved', COALESCE(resolved_at,NOW()), NULL) WHERE id=?")
            ->execute([$st, $urg, $team, $target, $st, $st, $id]);
        if ($st === 'Resolved' && $old['status'] !== 'Resolved') // fresh resolution: let the reporter give feedback again
            $pdo->prepare("UPDATE reports SET feedback_rating=NULL, feedback_note=NULL WHERE id=?")->execute([$id]);
        if ($st !== $old['status'] || $note !== '' || $team != $old['team_id']) {
            if ($note === '') $note = ['Received' => 'We have your concern on file.', 'Acknowledged' => 'Your concern has been heard. The barangay is reviewing it.',
                'Action Ongoing' => 'Action is being taken on your concern.', 'Resolved' => 'The barangay has acted on your concern.'][$st];
            $pdo->prepare("INSERT INTO report_updates (report_id,status,note) VALUES (?,?,?)")->execute([$id, $st, $note]);
            $pdo->prepare("INSERT INTO notifications (report_id,message) VALUES (?,?)")->execute([$id, rid($id) . " updated to $st"]);
        }
        json_out(['ok' => true]);

    case 'team_set':
        $s = $_POST['status'] ?? '';
        if (!in_array($s, ['Available', 'Busy', 'Resting'])) json_out(['error' => 'Invalid'], 422);
        $pdo->prepare("UPDATE teams SET status=?, note=? WHERE id=?")->execute([$s, trim($_POST['note'] ?? ''), (int)$_POST['id']]);
        json_out(['ok' => true]);

    case 'teams':
        json_out(['teams' => team_rows($pdo)]);
}
json_out(['error' => 'Unknown action'], 400);
