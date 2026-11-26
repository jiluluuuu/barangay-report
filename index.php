<?php
require_once __DIR__ . '/config/server.php';
require_once __DIR__ . '/includes/icons.php';
$k = ['t' => 0, 'r' => 0, 'a' => 0, 'd' => null]; $recent = [];
try {
    $k = $pdo->query("SELECT COUNT(*) t, COALESCE(SUM(status='Resolved'),0) r, COALESCE(SUM(status<>'Resolved'),0) a,
        ROUND(AVG(CASE WHEN resolved_at IS NOT NULL THEN TIMESTAMPDIFF(HOUR,created_at,resolved_at) END)/24,1) d FROM reports")->fetch();
    $recent = $pdo->query("SELECT type,location,resolved_at FROM reports WHERE status='Resolved' ORDER BY resolved_at DESC LIMIT 4")->fetchAll();
} catch (Exception $e) {}
$logo = '<img src="images/logo.png" alt="" onerror="this.replaceWith(Object.assign(document.createElement(\'span\'),{className:\'seal\',textContent:\'B\'}))">';
?>
<!DOCTYPE html>
<html lang="en" data-base="./"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Barangay Accessibility &amp; Barrier Report System</title>
<link rel="stylesheet" href="assets/css/style.css?v=<?= filemtime(__DIR__."/assets/css/style.css") ?>"><link rel="stylesheet" href="assets/css/landing.css?v=<?= filemtime(__DIR__."/assets/css/landing.css") ?>"></head>
<body class="lp">
<?php sprite(); ?>
<header class="lbar">
  <a class="brand" href="index.php"><?= $logo ?><span>Barangay Accessibility &amp; Barrier Report System</span></a>
  <nav><div class="sz" role="group" aria-label="Text size"><button type="button" data-s="" aria-pressed="true">A</button><button type="button" data-s="lg" aria-pressed="false">A+</button></div>
    <a href="track.php">Track a report</a></nav>
</header>

<main class="hero3">
  <h1>Barangay Accessibility &amp; Barrier Report System</h1>
  <p class="tag">"Tulungan ang ating Barangay na maging mas ligtas para sa lahat."</p>
  <p class="lead">Report a broken ramp, a counter you can't reach, or someone who wouldn't listen. The barangay answers, and you can watch what it does.</p>
  <div class="ba">
    <div class="bc before"><div class="ph crack"><img src="images/before.jpg" alt="Cracked road before repair" onerror="this.remove()"><span class="lab">Before</span></div>
      <div class="cap"><b>Cracks on Road</b>Reported: May 1, 2026 | San Pedro</div></div>
    <span class="arrow" aria-hidden="true">&rarr;</span>
    <div class="bc after"><div class="ph"><img src="images/after.jpg" alt="Smooth road after repair" onerror="this.remove()"><span class="lab">After</span></div>
      <div class="cap"><b>Resolved (Smooth Road)</b>Completed: May 4, 2026 | San Pedro</div></div>
  </div>
  <a class="cta" href="report-form.php">Report Now</a>
  <div class="again">Already reported? Check your tracking code.
    <form action="track.php" method="get"><input name="code" placeholder="e.g. BR-00001-A1B2" aria-label="Tracking code"><button>Check progress</button></form></div>
  <a class="more" href="#more">How it works &#8595;</a>
</main>

<section class="band" id="more"><div class="in">
  <h2>What you can report</h2>
  <div class="cols">
    <div><?= ic('pin') ?><h3>Physical barriers</h3><p>Broken sidewalks, blocked ramps, high curbs, no handrails, dark streets, restrooms nobody can use.</p></div>
    <div><?= ic('chat') ?><h3>Communication &amp; treatment</h3><p>Being ignored or treated unfairly, no sign language help, notices you can't read or understand.</p></div>
    <div><?= ic('reports') ?><h3>Barangay services</h3><p>Counters out of reach, long waits for PWD and seniors, trouble with IDs, benefits, health or social help.</p></div>
  </div>
  <p class="note">Open to persons with disability (PWD), senior citizens, parents, and anyone else. You can report for yourself or for someone who can't.</p>
</div></section>

<section class="band"><div class="in">
  <h2>Every concern gets an answer within <?= RESPONSE_DAYS ?> days</h2>
  <ol class="flow">
    <li><b>You tell us</b>In your own words. No account, name optional.</li>
    <li><b>The barangay hears it</b>A named office takes it on and sends you a message.</li>
    <li><b>You see the action</b>Every step shows live on your tracking page.</li>
    <li><b>You confirm it</b>Tell us if it was really solved. If not, we reopen it.</li>
  </ol>
</div></section>

<section class="band"><div class="in">
  <h2>What has changed so far</h2>
  <p class="ledger"><?php if ((int)$k['t'] === 0): ?>No concerns have been filed yet. The first one starts here.<?php else: ?>
    <b><?= (int)$k['t'] ?></b> concerns filed, <b><?= (int)$k['r'] ?></b> resolved, <b><?= (int)$k['a'] ?></b> being worked on.<?= $k['d'] !== null ? ' A fix takes <b>' . e($k['d']) . ' days</b> on average.' : '' ?><?php endif; ?></p>
  <?php if ($recent): ?><ul class="done2"><?php foreach ($recent as $r): ?>
    <li><?= ic('check') ?><span><b><?= e($r['type']) ?></b>, <?= e($r['location']) ?></span><small><?= e(date('M j, Y', strtotime($r['resolved_at']))) ?></small></li><?php endforeach; ?></ul><?php endif; ?>
</div></section>

<footer class="lf"><div class="in">
  <div class="brand2"><?= $logo ?><div><b><?= e(BRGY_NAME) ?></b><?= e(BRGY_ADDR) ?></div></div>
  <span class="i"><?= ic('phone') ?>Hotline: <?= e(BRGY_HOTLINE) ?></span><span class="i"><?= ic('clock') ?><?= e(BRGY_HOURS) ?></span>
</div></footer>
<div id="toasts" class="toasts"></div>
<script src="assets/js/app.js"></script>
<script>
const html=document.documentElement;
function setSize(v){ if(v) html.dataset.size=v; else delete html.dataset.size; localStorage.setItem('br_size',v);
  $$('.sz button').forEach(b=>b.setAttribute('aria-pressed', b.dataset.s===v)); }
$$('.sz button').forEach(b=>b.onclick=()=>setSize(b.dataset.s)); setSize(localStorage.getItem('br_size')||'');
watchMyReports(); mountResidentBell();
</script></body></html>
