<?php
require_once __DIR__ . '/../config/server.php';
require_once __DIR__ . '/../includes/layout.php';
admin_head('Concern Reports', 'reports'); ?>
<div class="tabs" id="tabs"></div>
<div class="filters">
  <div class="searchbox"><?= ic('search') ?><input type="search" id="fQ" placeholder="Search ID, location, type, reporter"></div>
  <select id="fCat"><option value="">Type of Concern (All)</option><?php foreach (CATEGORIES as $k => $l): ?><option value="<?= e($k) ?>"><?= e($l) ?></option><?php endforeach; ?></select>
  <select id="fUrg"><option value="">Report Urgency (All)</option><option>High</option><option>Medium</option><option>Low</option></select>
  <select id="fSt" hidden><option value="">Status (All)</option><?php foreach (STATUSES as $s): ?><option><?= e($s) ?></option><?php endforeach; ?></select>
  <select id="fTeam"><option value="">Filter By Office (All)</option></select>
  <label class="chk"><input type="checkbox" id="fPwd"> PWD / senior related only</label>
</div>
<details class="ov" id="ov"><summary><span class="ov-t">Offices &amp; Responders</span><span id="ovSum" class="ov-sum"></span><span class="ov-more"></span></summary><div class="ov-chips" id="teams"></div></details>
<div class="cards" id="cards"></div>
<div class="modal" id="modal" hidden><div class="modal-box" id="mbox"></div></div>
<?php admin_foot(str_replace('%DAYS%', (string)RESPONSE_DAYS, <<<'JS'
let known = new Set(), first = true, teamsCache = [];
const DAYS = %DAYS%;
const thumb = r => r.photo ? `<img src="${BASE}uploads/${esc(r.photo)}" alt="" onerror="this.outerHTML='<div class=ph></div>'">` : `<div class="ph cat">${ic(CAT_ICON[r.category]||'alert')}</div>`;
const ageDays = r => (Date.now() - new Date(r.created_at.replace(' ','T'))) / 864e5;
async function load(){
  const q = new URLSearchParams({action:'reports', urgency:$('#fUrg').value, team:$('#fTeam').value, category:$('#fCat').value, pwd:$('#fPwd').checked?1:0});
  const d = await api(BASE+'admin/api.php?'+q); teamsCache = d.teams;
  if(!$('#fTeam').dataset.ready){ $('#fTeam').innerHTML='<option value="">Filter By Office (All)</option>'+d.teams.map(t=>`<option value="${t.id}">${esc(t.name)} (${t.status})</option>`).join(''); $('#fTeam').dataset.ready=1; }
  $('#teams').innerHTML = d.teams.map(t=>`<span class="ov-chip ${t.status}" title="${t.status}${t.note?' - '+esc(t.note):''} · ${t.jobs} open concern${t.jobs==1?'':'s'}"><i></i>${esc(t.name)}<em>${t.jobs}</em></span>`).join('');
  $('#ovSum').innerHTML = ['Available','Busy','Resting'].map(s=>`<span class="ov-chip ${s}"><i></i>${d.teams.filter(t=>t.status===s).length} ${s}</span>`).join('');
  const qs=$('#fQ').value.toLowerCase(), fs=$('#fSt').value, all=d.reports;
  const cnt=v=>all.filter(r=>r.status===v).length;
  $('#tabs').innerHTML=[['','All',all.length],['Received','Received',cnt('Received')],['Acknowledged','Acknowledged',cnt('Acknowledged')],['Action Ongoing','Action Ongoing',cnt('Action Ongoing')],['Resolved','Resolved',cnt('Resolved')]].map(([v,l,n])=>`<button data-v="${v}" class="${fs===v?'on':''}">${l}<em>${n}</em></button>`).join('');
  const rows = all.filter(r=>(!fs||r.status===fs)&&(!qs||[r.rid,r.location,r.type,r.reporter_name].join(' ').toLowerCase().includes(qs)));
  $('.pagehead small').textContent='Showing '+rows.length+' of '+all.length+' concerns';
  $('#cards').innerHTML = rows.length ? rows.map(r=>{ const late = r.status==='Received' && ageDays(r)>DAYS;
    return `<div class="card ${!first&&!known.has(r.id)?'flash':''}" data-id="${r.id}" style="border-top:3px solid ${ST_HEX[r.status]}">${thumb(r)}
    <div><b>ID:</b> ${esc(r.rid)} <span class="urg ${r.urgency}">${r.urgency}</span></div>
    <div class="tagrow"><span class="tag2">${esc(r.category)}</span>${r.pwd_related==1?'<span class="tag2 pwd">PWD / Senior</span>':''}${late?'<span class="tag2 late">Not yet heard</span>':''}</div>
    <div><b>Location:</b> ${esc(r.location)}</div><div><b>Type:</b> ${esc(r.type)}</div>
    <div><b>Status:</b> <b style="color:${ST_HEX[r.status]}">${esc(r.status)}</b></div><div><b>Reporter:</b> ${esc(r.reporter_name)}</div>
    <div class="when">${ic('clock')}Filed ${timeAgo(r.created_at)}</div></div>`; }).join('') : '<div class="empty">No concerns match.</div>';
  all.forEach(r=>known.add(r.id)); first=false;
}
$('#cards').onclick = e => { const c=e.target.closest('.card'); if(c) openModal(c.dataset.id); };
async function openModal(id){
  const d = await api(BASE+'admin/api.php?action=detail&id='+id), r=d.report;
  const opt=(arr,v)=>arr.map(x=>`<option ${x==v?'selected':''}>${x}</option>`).join('');
  $('#mbox').innerHTML = `${r.photo?`<img src="${BASE}uploads/${esc(r.photo)}" alt="">`:''}
   <h2>${esc(r.rid)} &mdash; ${esc(r.type)}</h2>
   <div class="tagrow"><span class="tag2">${esc(r.category)}</span>${r.pwd_related==1?'<span class="tag2 pwd">PWD / Senior</span>':''}</div>
   ${r.description?`<div class="desc-box">${esc(r.description)}</div>`:''}
   <div class="kv">${ic('pin')} <b>${esc(r.location)}</b>${r.lat?` <small>(${(+r.lat).toFixed(4)}, ${(+r.lng).toFixed(4)})</small>`:''}</div>
   ${r.pwd_related==1?`<div class="kv">Affected: <b>${esc(r.affected_groups||'Not specified')}</b> &middot; Reported by: <b>${r.reported_for==='Someone else'?'someone else':'the affected person'}</b></div>`:''}
   <div class="kv">Reporter: <b>${esc(r.reporter_name)}</b>${r.contact?` &middot; Contact: <b>${esc(r.contact)}</b>`:''} &middot; ${fmtDate(r.created_at)}</div>
   <div class="kv">Tracking code: <b>${esc(r.tracking_code)}</b>${r.feedback_rating?` &middot; Reporter feedback: <b>${r.feedback_rating==='Yes'?'confirmed addressed':'says NOT addressed'}</b>${r.feedback_note?` &ldquo;${esc(r.feedback_note)}&rdquo;`:''}`:''}</div>
   <div class="grid2">
    <div><label>STATUS</label><select id="mSt">${opt(['Received','Acknowledged','Action Ongoing','Resolved'],r.status)}</select></div>
    <div><label>URGENCY</label><select id="mUr">${opt(['High','Medium','Low'],r.urgency)}</select></div>
    <div><label>RESPONSIBLE OFFICE</label><select id="mTm"><option value="">&mdash; Unassigned &mdash;</option>${teamsCache.map(t=>`<option value="${t.id}" ${t.id==r.team_id?'selected':''}>${esc(t.name)} (${t.status})</option>`).join('')}</select></div>
    <div><label>TARGET ACTION DATE</label><input type="date" id="mDt" value="${esc(r.target_date||'')}" style="width:100%;padding:8px;border:1px solid #c9d0db;border-radius:4px;margin-top:3px"></div>
   </div>
   <label style="font-size:12px;font-weight:700;color:#4b5563">RESPONSE TO THE REPORTER (they see this live)</label>
   <textarea id="mNote" rows="3" placeholder="Tell them what was heard and what the barangay will do or has done."></textarea>
   <div style="margin:12px 0;display:flex;gap:8px"><button class="btn" id="mSave">Save &amp; Notify Reporter</button><button class="btn light" id="mClose">Close</button></div>
   <b>Tracking Timeline</b><ul class="tl">${d.timeline.map(t=>`<li><b>${esc(t.status)}</b> &mdash; ${esc(t.note)}<small>${fmtDate(t.created_at)}</small></li>`).join('')}</ul>`;
  $('#modal').hidden=false;
  $('#mClose').onclick=()=>$('#modal').hidden=true;
  $('#mSave').onclick=async()=>{ try{ await post(BASE+'admin/api.php?action=update',{id,status:$('#mSt').value,urgency:$('#mUr').value,team_id:$('#mTm').value,target_date:$('#mDt').value,note:$('#mNote').value}); toast('Saved. The reporter has been notified.','ok'); $('#modal').hidden=true; load(); }catch(e){toast(e.message,'err');} };
}
$('#modal').onclick=e=>{ if(e.target.id==='modal') $('#modal').hidden=true; };
['#fUrg','#fTeam','#fSt','#fCat','#fPwd'].forEach(s=>$(s).onchange=load); $('#fQ').oninput=load;
$('#tabs').onclick=e=>{ const b=e.target.closest('button'); if(b){ $('#fSt').value=b.dataset.v; load(); } };
load().then(()=>{ const o=new URLSearchParams(location.search).get('open'); if(o) openModal(o); });
document.addEventListener('br:update',load); setInterval(load,15000);
JS)); ?>
