<?php
require_once __DIR__ . '/../config/server.php';
require_once __DIR__ . '/../includes/layout.php';
admin_head('System Analytics Dashboard', 'dashboard'); ?>
<div class="stats4">
  <div class="stat4"><span class="bi"><?= ic('reports') ?></span><div><small>TOTAL CONCERNS FILED</small><div class="num" id="sTotal">&ndash;</div><em id="sTotalSub">&nbsp;</em></div></div>
  <div class="stat4 red"><span class="bi"><?= ic('alert') ?></span><div><small>ACTIVE INVESTIGATIONS</small><div class="num" id="sActive">&ndash;</div><em id="sActiveSub">&nbsp;</em></div></div>
  <div class="stat4 green"><span class="bi"><?= ic('check') ?></span><div><small>RESOLVED CONCERNS</small><div class="num" id="sDone">&ndash;</div><em id="sDoneSub">&nbsp;</em></div></div>
  <div class="stat4 amber" id="waitCard"><span class="bi"><?= ic('clock') ?></span><div><small>WAITING TO BE HEARD</small><div class="num" id="sWait">&ndash;</div><em id="sWaitSub">&nbsp;</em></div></div>
</div>
<div class="dash-main">
  <div class="panel mapp">
    <div class="map-title">Barangay Accessibility Incident Map Tracker</div>
    <div id="map"></div>
    <div class="legend2" id="legend"></div>
    <div class="map-empty" id="mapEmpty" hidden><span>No pinned concerns yet. Residents who tap &ldquo;Use my location&rdquo; will appear here.</span></div>
  </div>
  <aside class="side-col">
    <div class="panel">
      <h3>Analytics</h3>
      <div class="donut-row"><svg id="donut" viewBox="0 0 100 100"></svg><ul id="dlegend"></ul></div>
      <div class="kpis3"><div><b id="kResp">&ndash;</b><span>avg. response</span></div><div><b id="kPwd">&ndash;</b><span>PWD / senior</span></div><div><b id="kFb">&ndash;</b><span>confirmed solved</span></div></div>
      <div class="bars" id="bars"></div>
      <div class="trendh"><span><i style="background:#2563eb"></i>Filed</span><span><i style="background:#16a34a"></i>Resolved</span><span>last 7 days</span></div>
      <div id="trend"></div>
    </div>
    <div class="panel notif">
      <h3><span>Notifications<span class="ncount" id="nCount"></span></span><a href="#" id="dMark">Mark all read</a></h3>
      <div id="nlist"></div>
    </div>
  </aside>
</div>
<?php admin_foot(<<<'JS'
if (typeof L === 'undefined') { const stub = new Proxy(function(){}, {get:()=>stub, apply:()=>stub, construct:()=>stub}); window.L = stub; $('#map').innerHTML = '<div style="padding:50px;text-align:center;color:#556">The map needs an internet connection (OpenStreetMap).</div>'; }
const map=L.map('map').setView([9.7392,118.7353],13);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{attribution:'&copy; OpenStreetMap'}).addTo(map);
new ResizeObserver(()=>map.invalidateSize()).observe($('#map'));
const ORDER=['Received','Acknowledged','Action Ongoing','Resolved'], groups={}, on={};
ORDER.forEach(s=>{groups[s]=L.layerGroup().addTo(map); on[s]=true;});
const pin=(c,p)=>L.divIcon({className:'pin',html:`<i style="--c:${c}" class="${p?'pulse':''}"></i>`,iconSize:[26,34],iconAnchor:[13,32],popupAnchor:[0,-30]});
let fitted=false, items=[];
$('#legend').onclick=e=>{ const b=e.target.closest('button'); if(!b) return; const s=b.dataset.s; on[s]=!on[s]; on[s]?groups[s].addTo(map):map.removeLayer(groups[s]); b.classList.toggle('off',!on[s]); };
function donut(by){
  const tot=ORDER.reduce((a,s)=>a+(+by[s]||0),0), C=2*Math.PI*38; let off=0, segs='';
  ORDER.forEach(s=>{ const n=+by[s]||0; if(!n) return; const len=n/tot*C; segs+=`<circle r="38" cx="50" cy="50" fill="none" stroke="${ST_HEX[s]}" stroke-width="14" stroke-dasharray="${len} ${C-len}" stroke-dashoffset="${-off}" transform="rotate(-90 50 50)"/>`; off+=len; });
  $('#donut').innerHTML=(tot?segs:'<circle r="38" cx="50" cy="50" fill="none" stroke="#e5e9f0" stroke-width="14"/>')+`<text x="50" y="50" text-anchor="middle" font-size="18" font-weight="700" fill="#16202e">${tot}</text><text x="50" y="63" text-anchor="middle" font-size="7.5" fill="#6b7280">concerns</text>`;
  $('#dlegend').innerHTML=ORDER.map(s=>`<li><i style="background:${ST_HEX[s]}"></i>${s}<b>${by[s]||0}</b></li>`).join('');
}
function trend(t){
  const max=Math.max(1,...t.map(x=>Math.max(x.filed,x.resolved))), W=300, H=62; let g='';
  t.forEach((x,i)=>{ const bx=i*(W/7)+6, h1=x.filed/max*(H-16), h2=x.resolved/max*(H-16);
    g+=`<rect x="${bx}" y="${H-13-h1}" width="12" height="${Math.max(h1,1)}" rx="2" fill="#2563eb"/><rect x="${bx+14}" y="${H-13-h2}" width="12" height="${Math.max(h2,1)}" rx="2" fill="#16a34a"/><text x="${bx+13}" y="${H-2}" font-size="8" text-anchor="middle" fill="#6b7280">${x.label}</text>`; });
  $('#trend').innerHTML=`<svg viewBox="0 0 ${W} ${H}" width="100%">${g}</svg>`;
}
const readId=()=>+(localStorage.getItem('br_read_id')||0);
function notifs(){ const r=readId();
  $('#nlist').innerHTML=items.length?items.map(i=>`<a class="nitem ${i.id>r?'new':''}" href="reports.php${i.report_id?'?open='+i.report_id:''}" style="--c:${/REOPENED/.test(i.message)?'#dc2626':/^New/.test(i.message)?'#2563eb':'#16a34a'}"><span class="ndot"></span><span>${esc(i.message)}<small>${timeAgo(i.created_at)}</small></span></a>`).join(''):'<div class="empty">No notifications yet.</div>';
  $('#nCount').textContent=items.filter(i=>i.id>r).length||''; }
$('#dMark').onclick=e=>{ e.preventDefault(); localStorage.setItem('br_read_id',items[0]?items[0].id:0); notifs(); document.dispatchEvent(new Event('br:read')); };
async function load(){
  const d=await api(BASE+'admin/api.php?action=stats'), s=d.s, week=d.trend.reduce((a,x)=>a+x.filed,0);
  $('#sTotal').textContent=s.total; $('#sTotalSub').textContent=week+' filed in the last 7 days';
  $('#sActive').textContent=s.active; $('#sActiveSub').textContent=s.high+' high urgency';
  $('#sDone').textContent=s.resolved; $('#sDoneSub').textContent=s.total?Math.round(s.resolved/s.total*100)+'% of all concerns':'No concerns yet';
  $('#sWait').textContent=s.waiting; $('#sWaitSub').textContent=+s.overdue?s.overdue+' past the '+d.resp_days+'-day target':'None overdue';
  $('#waitCard').classList.toggle('alert',+s.overdue>0);
  $('#kResp').textContent=s.resp!==null?(s.resp<48?s.resp+' h':(s.resp/24).toFixed(1)+' d'):'\u2013';
  $('#kPwd').textContent=s.pwd; $('#kFb').textContent=s.fb_yes+'/'+(+s.fb_yes+ +s.fb_no);
  donut(d.by); trend(d.trend);
  const max=Math.max(1,...d.cats.map(c=>+c.n)), L2={Physical:'Physical',Communication:'Communication',Service:'Services',Other:'Other'};
  $('#bars').innerHTML=d.cats.map(c=>`<div><span class="l">${esc(L2[c.category]||c.category)}</span><span class="b" style="width:${(+c.n/max)*55}%"></span><em>${c.n}</em></div>`).join('');
  ORDER.forEach(x=>groups[x].clearLayers()); const pts=[], cnt={};
  d.pins.forEach(p=>{ const ll=[+p.lat,+p.lng]; pts.push(ll); cnt[p.status]=(cnt[p.status]||0)+1;
    L.marker(ll,{icon:pin(ST_HEX[p.status],p.status==='Received')}).addTo(groups[p.status])
     .bindPopup(`<b>${esc(p.rid)}</b><br>${esc(p.type)}<br>${esc(p.location)}<br><b style="color:${ST_HEX[p.status]}">${esc(p.status)}</b><br><a href="reports.php?open=${p.id}">Open report</a>`); });
  $('#legend').innerHTML=ORDER.map(x=>`<button data-s="${x}" class="${on[x]?'':'off'}"><i style="background:${ST_HEX[x]}"></i>${x} <em>${cnt[x]||0}</em></button>`).join('');
  $('#mapEmpty').hidden=pts.length>0;
  if(pts.length&&!fitted){ map.fitBounds(pts,{padding:[50,50],maxZoom:16}); fitted=true; }
  items=d.notifs; notifs();
  $('.pagehead small').innerHTML=new Date().toLocaleDateString('en-PH',{weekday:'long',year:'numeric',month:'long',day:'numeric'})+' &middot; <span class="live"></span>Live, updated '+new Date().toLocaleTimeString('en-PH',{hour:'2-digit',minute:'2-digit',second:'2-digit'});
}
load(); document.addEventListener('br:update',load); setInterval(load,30000);
JS); ?>
