<?php
require_once __DIR__ . '/../config/server.php';
require_once __DIR__ . '/../includes/layout.php';
admin_head('Barangay Offices & Responders', 'teams'); ?>
<div class="panel"><div class="team-title">SET OFFICE AVAILABILITY. THE BAR SHOWS OPEN CONCERNS (FULL = 6 OR MORE).</div><div id="list" class="tgrid"></div></div>
<?php admin_foot(<<<'JS'
async function load(){
  const d = await api(BASE+'admin/api.php?action=teams');
  $('#list').innerHTML = d.teams.map(t=>`<div class="tcard ${t.status}">
   <div class="tc-head"><span class="tc-ic">${ic('teams')}</span><span class="tc-pill">${t.status}</span></div>
   <b>${esc(t.name)}</b>
   <div class="tc-load"><span><strong>${t.jobs}</strong> open concern${t.jobs==1?'':'s'}</span><span>${Math.min(100,Math.round(t.jobs/6*100))}%</span></div>
   <div class="load"><i class="${t.jobs>=5?'hot':''}" style="width:${Math.min(100,t.jobs/6*100)}%"></i></div>
   <div class="tc-form"><input type="text" data-n="${t.id}" value="${esc(t.note)}" placeholder="Note (e.g. Shift Ends)" aria-label="Note for ${esc(t.name)}">
   <div class="tc-row"><select data-t="${t.id}" aria-label="Status for ${esc(t.name)}">${['Available','Busy','Resting'].map(s=>`<option ${s==t.status?'selected':''}>${s}</option>`).join('')}</select>
   <button class="btn" data-s="${t.id}">Save</button></div></div></div>`).join('');
}
$('#list').onclick = async e => { const id=e.target.dataset.s; if(!id) return;
  try{ await post(BASE+'admin/api.php?action=team_set',{id,status:$(`[data-t="${id}"]`).value,note:$(`[data-n="${id}"]`).value}); toast('Office updated.','ok'); load(); }catch(err){toast(err.message,'err');} };
load(); document.addEventListener('br:update',load);
JS); ?>
