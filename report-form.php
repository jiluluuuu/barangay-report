<?php require_once __DIR__ . '/config/server.php'; require_once __DIR__ . '/includes/icons.php'; ?>
<!DOCTYPE html>
<html lang="en" data-base="./"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Report a Concern</title>
<link rel="stylesheet" href="assets/css/style.css?v=<?= filemtime(__DIR__."/assets/css/style.css") ?>"><link rel="stylesheet" href="assets/css/form.css?v=<?= filemtime(__DIR__."/assets/css/form.css") ?>"></head>
<body class="ff">
<?php sprite(); ?>
<header class="topbar"><div class="brand"><img src="images/bgy.jpg" class="logo" alt="" onerror="this.style.display='none'"><a href="index.php" style="text-decoration:none">Barangay Accessibility &amp; Barrier Report System</a></div>
  <div class="top-right"><a href="track.php">Track a report</a><a class="backbtn" href="index.php" onclick="if(history.length>1){history.back();return false}"><?= ic('back') ?>Back</a></div></header>
<main class="ff-main">
<div class="fc" id="card">
  <div class="head">
    <div><h2>Tell the barangay what happened</h2><p>Physical barriers, the way you were treated, or a service you could not use. Every concern is read and answered.</p></div>
    <span class="sla"><?= ic('clock') ?>Answered within <?= RESPONSE_DAYS ?> days</span>
  </div>
  <form id="f" enctype="multipart/form-data">
    <div class="col">
      <div class="lbl">WHAT KIND OF CONCERN?</div>
      <div class="tags" id="cats">
        <button type="button" data-v="Physical" class="on"><?= ic('pin') ?>Physical</button>
        <button type="button" data-v="Communication"><?= ic('chat') ?>Communication</button>
        <button type="button" data-v="Service"><?= ic('reports') ?>Barangay service</button>
        <button type="button" data-v="Other"><?= ic('alert') ?>Something else</button>
      </div>
      <div class="hint" id="catHint"></div>
      <input type="hidden" name="category" id="category" value="Physical">

      <div class="lbl">QUICK TAGS:</div>
      <div class="tags" id="tags"></div>
      <input type="hidden" name="type" id="type">

      <div class="lbl"><span id="descLbl">WHAT HAPPENED?</span><span class="opt" id="cnt">0 / 2000</span></div>
      <textarea name="description" id="desc" maxlength="2000" placeholder="Describe it in your own words. Any language is fine."></textarea>

      <div class="lbl">LOCATION:</div>
      <div class="row"><input type="text" id="loc" name="location" placeholder="Street, landmark, or barangay office" required maxlength="160"><button type="button" class="gps" id="gps"><?= ic('pin') ?>Use my location</button></div>
      <div class="hint" id="gpsHint">Pin your location so it shows on the barangay map (optional).</div>
      <input type="hidden" name="lat" id="lat"><input type="hidden" name="lng" id="lng">
    </div>

    <div class="col">
      <div class="lbl">PHOTO <span class="opt">(optional)</span></div>
      <div class="drop" id="drop"><span id="dropTxt"><?= ic('camera', 'big') ?><br>Click to add a photo</span><img id="prev" hidden alt=""><button type="button" class="change" id="chg" hidden>Change Photo</button></div>
      <input type="file" id="photo" name="photo" accept="image/jpeg,image/png,image/webp" hidden>

      <label class="switch-row">
        <span><b>This affects a person with disability (PWD) or a senior citizen</b><small>Turn on so the right barangay desk can respond.</small></span>
        <input type="checkbox" id="pwd" class="sw">
      </label>
      <input type="hidden" name="pwd" id="pwdVal" value="0">
      <div id="pwdBox" hidden>
        <div class="lbl">WHO IS AFFECTED? <span class="opt">(choose all that apply)</span></div>
        <div class="tags" id="groups"><?php foreach (DISABILITIES as $d): ?><button type="button" data-v="<?= e($d) ?>"><?= e($d) ?></button><?php endforeach; ?></div>
        <div class="lbl">I AM:</div>
        <div class="tags" id="who"><button type="button" class="on" data-v="Myself">The person affected</button><button type="button" data-v="Someone else">Reporting for someone else</button></div>
      </div>

      <div class="pair">
        <div><div class="lbl">NAME <span class="opt">(optional)</span></div><input type="text" name="name" placeholder="Blank = anonymous" maxlength="80"></div>
        <div><div class="lbl">CONTACT <span class="opt">(optional)</span></div><input type="text" name="contact" placeholder="Phone or email" maxlength="80"></div>
      </div>
      <button class="submit" id="sub">Submit Report</button>
      <div class="fine">Your contact is only used to follow up. You can track every step after sending.</div>
    </div>
  </form>
</div>
</main>
<div id="toasts" class="toasts"></div>
<script src="assets/js/app.js"></script>
<script>
const TYPES = <?= json_encode(array_map('array_keys', TYPES)) ?>;
const HINT = {Physical:'Roads, sidewalks, ramps, buildings, lighting.', Communication:'How you were spoken to, interpreters, readable information.', Service:'Counters, waiting time, IDs, benefits, health and social help.', Other:'Anything else that keeps you from taking part.'};
const single = (box, onPick) => $$('button', box).forEach(b => b.onclick = () => { $$('button', box).forEach(x => x.classList.remove('on')); b.classList.add('on'); onPick(b.dataset.v); });
function drawTags(cat){
  $('#tags').innerHTML = TYPES[cat].map((t,i)=>`<button type="button" data-v="${esc(t)}" class="${i?'':'on'}">${esc(t)}</button>`).join('');
  $('#type').value = TYPES[cat][0]; single($('#tags'), v => $('#type').value = v);
  $('#catHint').textContent = HINT[cat];
  $('#descLbl').innerHTML = cat==='Physical' ? 'WHAT HAPPENED? <span class="opt">(optional for physical barriers)</span>' : 'WHAT HAPPENED? <span class="opt">(required)</span>';
  $('#desc').required = cat!=='Physical';
}
single($('#cats'), v => { $('#category').value = v; drawTags(v); }); drawTags('Physical');
$('#desc').oninput = e => $('#cnt').textContent = e.target.value.length + ' / 2000';
$$('#groups button').forEach(b => b.onclick = () => b.classList.toggle('on'));
single($('#who'), () => {});
$('#pwd').onchange = e => { $('#pwdBox').hidden = !e.target.checked; $('#pwdVal').value = e.target.checked ? '1' : '0'; };

let photoFile = null;
const photo = $('#photo');
async function shrink(f){          // phone photos are big: resize to 1600px JPEG before upload
  if (f.size < 1.2*1024*1024 && /jpeg|png|webp/.test(f.type)) return f;
  try { const bmp = await createImageBitmap(f), s = Math.min(1, 1600/Math.max(bmp.width,bmp.height)), c = document.createElement('canvas');
    c.width = Math.round(bmp.width*s); c.height = Math.round(bmp.height*s); c.getContext('2d').drawImage(bmp,0,0,c.width,c.height);
    return await new Promise(r => c.toBlob(r,'image/jpeg',.85)); } catch(e) { return f; }
}
$('#drop').onclick = e => { if (e.target.id !== 'chg') photo.click(); }; $('#chg').onclick = () => photo.click();
photo.onchange = async () => { const f = photo.files[0]; if (!f) return; const b = await shrink(f);
  if (b.size > 5*1024*1024) { toast('That photo is too large. Please pick a smaller one.','err'); photo.value=''; photoFile=null; return; }
  photoFile = b; $('#prev').src = URL.createObjectURL(b); $('#prev').hidden=false; $('#chg').hidden=false; $('#dropTxt').hidden=true; };
$('#gps').onclick = () => { if (!navigator.geolocation) return toast('GPS is not supported on this device.','err');
  $('#gpsHint').textContent='Getting your location...';
  navigator.geolocation.getCurrentPosition(p => { $('#lat').value=p.coords.latitude.toFixed(7); $('#lng').value=p.coords.longitude.toFixed(7);
    $('#gpsHint').textContent='Location pinned ('+p.coords.latitude.toFixed(4)+', '+p.coords.longitude.toFixed(4)+'). It will show on the barangay map.';
    if (!$('#loc').value) $('#loc').value='Pinned location'; }, () => { $('#gpsHint').textContent='Could not get GPS (it needs https or localhost). You can still type the location.'; }, {enableHighAccuracy:true,timeout:10000}); };

$('#f').onsubmit = async e => { e.preventDefault(); $('#sub').disabled=true; $('#sub').textContent='Submitting...';
  const fd = new FormData($('#f')); fd.delete('photo'); if (photoFile) fd.append('photo', photoFile, 'photo.jpg');
  $$('#groups button.on').forEach(b => fd.append('groups[]', b.dataset.v));
  fd.append('for_whom', ($('#who button.on')||{dataset:{v:'Myself'}}).dataset.v);
  try { const d = await api('api/submit.php', {method:'POST', body:fd}); saveCode(d.code, d.sig); document.dispatchEvent(new Event('br:feed'));
    $('#card').innerHTML = `<div class="success"><h2>Your concern has been received</h2><p>Keep your tracking code:</p><div class="code">${esc(d.code)}</div>
      <p class="hint">The barangay will acknowledge it within <?= RESPONSE_DAYS ?> days. You will get a live notice in this browser at every step, and you can tell us if it was really solved.</p>
      <p><a href="track.php?code=${encodeURIComponent(d.code)}">Track this report</a> &middot; <a href="index.php">Back home</a></p></div>`;
    if ('Notification' in window && Notification.permission === 'default') Notification.requestPermission();
  } catch (err) { toast(err.message,'err'); $('#sub').disabled=false; $('#sub').textContent='Submit Report'; } };
watchMyReports(); mountResidentBell();
</script></body></html>
