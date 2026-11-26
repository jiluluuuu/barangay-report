<?php require_once __DIR__ . '/config/server.php'; require_once __DIR__ . '/includes/icons.php'; ?>
<!DOCTYPE html>
<html lang="en" data-base="./"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Track Report</title><link rel="stylesheet" href="assets/css/style.css?v=<?= filemtime(__DIR__."/assets/css/style.css") ?>"></head>
<body class="form-page">
<?php sprite(); gov_strip(); ?>
<header class="topbar"><div class="brand"><img src="images/bgy.jpg" class="logo" alt="" onerror="this.style.display='none'"><a href="index.php" style="text-decoration:none">Barangay Accessibility &amp; Barrier Report System</a></div>
  <div class="top-right"><a class="backbtn" href="index.php" onclick="if(history.length>1){history.back();return false}"><?= ic('back') ?>Back</a></div></header>
<div class="track-card">
  <h2>Track Your Report</h2>
  <p class="sub">Enter the code you received after submitting. This page updates by itself.</p>
  <form class="search" id="sf"><input id="code" placeholder="Tracking code e.g. BR-00001-A1B2" value="<?= e($_GET['code'] ?? '') ?>"><button class="btn">Track</button></form>
  <div id="mine" class="hint" style="margin-top:8px"></div>
  <div id="res"></div>
</div>
<?php public_footer(); ?>
<div id="toasts" class="toasts"></div>
<script src="assets/js/app.js"></script>
<script>
const FLOW = [['Received','Received'],['Acknowledged','Heard by barangay'],['Action Ongoing','Action ongoing'],['Resolved','Resolved']]; let cur = '';
async function load(code){ cur = code.trim().toUpperCase(); if (!cur) return;
  const d = await api('api/status.php?full=1&code='+encodeURIComponent(cur));
  if (!d.length) { $('#res').innerHTML = '<p class="empty">No report found for that code.</p>'; return; }
  const r = d[0], i = FLOW.findIndex(f => f[0] === r.status); saveCode(r.code);
  const target = r.target_date ? `<div class="when">${ic('clock')} Barangay target date: <b>${new Date(r.target_date).toLocaleDateString('en-PH',{dateStyle:'medium'})}</b></div>` : '';
  let fb = '';
  if (r.status === 'Resolved' && !r.feedback) fb = `<div class="fbbox"><b>Was your concern really addressed?</b><div class="hint2">Your answer goes straight to the barangay.</div>
     <textarea id="fbNote" rows="2" placeholder="Optional comment"></textarea>
     <div style="display:flex;gap:8px;margin-top:8px"><button class="btn" id="fbYes">Yes, it was addressed</button><button class="btn light" id="fbNo">No, not yet</button></div></div>`;
  else if (r.feedback === 'Yes') fb = '<div class="fbbox ok">You confirmed this concern was addressed. Thank you.</div>';
  $('#res').innerHTML = `<h3 style="margin-bottom:2px">${esc(r.type)}</h3>
   <div class="hint" style="color:#666"><span class="live"></span>${esc(r.location)} &middot; Filed ${fmtDate(r.created_at)}</div>
   ${r.description ? `<p class="said">${esc(r.description)}</p>` : ''}
   <div class="when">${ic('teams')} Handled by: <b>${esc(r.team || 'Awaiting assignment')}</b></div>${target}
   <div class="steps">${FLOW.map((f,k)=>`<div class="step ${k<=i?'done':''}">${f[1]}</div>`).join('')}</div>
   ${fb}
   <ul class="tl">${r.timeline.map(t=>`<li><b>${esc(t.status)}</b> &mdash; ${esc(t.note)}<small>${fmtDate(t.created_at)}</small></li>`).join('')}</ul>`;
  const seen = JSON.parse(localStorage.getItem('br_seen')||'{}'); seen[r.code] = r.status+'|'+r.updated_at; localStorage.setItem('br_seen', JSON.stringify(seen));
  if ($('#fbYes')) { const send = a => async () => { try { await post('api/feedback.php',{code:r.code,answer:a,note:$('#fbNote').value}); toast(a==='Yes'?'Thank you for confirming.':'Your concern was reopened. The barangay has been told.','ok'); load(cur); } catch(e){ toast(e.message,'err'); } };
    $('#fbYes').onclick = send('Yes'); $('#fbNo').onclick = send('No'); }
}
$('#sf').onsubmit = e => { e.preventDefault(); load($('#code').value); };
if ($('#code').value) load($('#code').value);
if (myCodes().length) $('#mine').innerHTML = 'Your recent reports: ' + myCodes().slice(0,5).map(c=>`<a href="?code=${c}">${c}</a>`).join(' &middot; ');
mountResidentBell();
let sig = '';
watchMyReports(rows => { const r = rows.find(x => x.code === cur); if (!r) return; const n = r.status+'|'+r.updated_at; if (sig && sig !== n) load(cur); sig = n; });
</script></body></html>
