/* Shared helpers + live notifications (polling) */
const $ = (s, r = document) => r.querySelector(s);
const $$ = (s, r = document) => [...r.querySelectorAll(s)];
const BASE = (document.querySelector('meta[name=base]') || {}).content || document.documentElement.dataset.base || './';
const ic = (n, c = '') => `<svg class="ic ${c}"><use href="#i-${n}"/></svg>`;
const ST_HEX = { Received: '#dc2626', Acknowledged: '#2563eb', 'Action Ongoing': '#d97706', Resolved: '#16a34a' };
const CAT_ICON = { Physical: 'pin', Communication: 'chat', Service: 'reports', Other: 'alert' };
const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
const csrfToken = () => (document.querySelector('meta[name=csrf]') || {}).content || '';

/* fetch JSON. Errors say what really happened instead of a vague "Request failed" */
async function api(url, opts = {}) {
  let r;
  try { r = await fetch(url, { cache: 'no-store', ...opts }); }
  catch (e) { throw new Error('Cannot reach the server. Check that Laragon (Apache and MySQL) is running.'); }
  const text = await r.text();
  let data;
  try { data = JSON.parse(text); }
  catch (e) { throw new Error('Server error (HTTP ' + r.status + '). ' + text.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim().slice(0, 180) + ' Open setup.php to check the system.'); }
  if (!r.ok) throw new Error(data.error || 'Request failed (HTTP ' + r.status + ')');
  return data;
}
function post(url, obj) {
  const fd = new FormData();
  Object.entries(obj).forEach(([k, v]) => fd.append(k, v ?? ''));
  return api(url, { method: 'POST', body: fd, headers: { 'X-CSRF': csrfToken() } });
}
function toast(msg, type = '') {
  let box = $('#toasts');
  if (!box) { box = document.createElement('div'); box.id = 'toasts'; box.className = 'toasts'; document.body.appendChild(box); }
  const t = document.createElement('div');
  t.className = 'toast ' + type; t.textContent = msg; box.appendChild(t);
  setTimeout(() => t.remove(), 7000);
}
function osNotify(title, body) {
  try { if ('Notification' in window && Notification.permission === 'granted') new Notification(title, { body }); } catch (e) { }
}
function beep() { // short soft chime when something new arrives (browsers may block it until the page was clicked once)
  try {
    const A = window.AudioContext || window.webkitAudioContext; if (!A) return;
    const c = new A(), o = c.createOscillator(), g = c.createGain();
    o.type = 'sine'; o.frequency.value = 880; g.gain.value = 0.06; o.connect(g); g.connect(c.destination);
    o.start(); g.gain.exponentialRampToValueAtTime(0.0001, c.currentTime + 0.35); o.stop(c.currentTime + 0.4);
  } catch (e) { }
}
const parseDt = s => new Date(String(s).replace(' ', 'T'));
function agoMs(ms) {
  const n = Math.floor((Date.now() - ms) / 1000);
  if (n < 60) return 'just now'; if (n < 3600) return Math.floor(n / 60) + ' min ago';
  if (n < 86400) return Math.floor(n / 3600) + ' hr ago'; return new Date(ms).toLocaleDateString();
}
const timeAgo = s => agoMs(parseDt(s).getTime());
const fmtDate = s => parseDt(s).toLocaleString('en-PH', { dateStyle: 'medium', timeStyle: 'short' });

/* ---------- ADMIN: live bell + pop-ups ---------- */
function initAdminLive() {
  const bell = $('#bell'), panel = $('#bellPanel'), list = $('#bellList'), count = $('#bellCount');
  if (!bell) return;
  let lastId = null, items = [], readId = +(localStorage.getItem('br_read_id') || 0);
  const render = () => {
    const unread = items.filter(i => i.id > readId).length;
    count.hidden = !unread; count.textContent = unread;
    list.innerHTML = items.length
      ? items.map(i => `<a class="bell-item ${i.id > readId ? 'new' : ''}" href="${BASE}admin/reports.php${i.report_id ? '?open=' + i.report_id : ''}">${esc(i.message)}<small>${timeAgo(i.created_at)}</small></a>`).join('')
      : '<div class="bell-item">No notifications yet. New reports and reporter feedback will appear here.</div>';
  };
  bell.onclick = e => { e.stopPropagation(); panel.hidden = !panel.hidden; if ('Notification' in window && Notification.permission === 'default') Notification.requestPermission(); };
  document.addEventListener('click', e => { if (!panel.contains(e.target)) panel.hidden = true; });
  $('#markRead').onclick = e => { e.preventDefault(); readId = items[0]?.id || readId; localStorage.setItem('br_read_id', readId); render(); document.dispatchEvent(new Event('br:read')); };
  document.addEventListener('br:read', () => { readId = +(localStorage.getItem('br_read_id') || 0); render(); });
  async function poll() {
    try {
      const d = await api(BASE + 'admin/api.php?action=poll');
      items = d.items;
      if (lastId !== null) {
        const fresh = items.filter(i => i.id > lastId).reverse();
        fresh.forEach(i => { toast(i.message); osNotify('Barangay report', i.message); });
        if (fresh.length) { beep(); document.dispatchEvent(new CustomEvent('br:update')); }
      }
      lastId = d.latest; render();
    } catch (e) {
      if (/Unauthorized/i.test(e.message)) { location.href = BASE + 'admin/login.php'; return; }
      list.innerHTML = `<div class="bell-item">Notifications are not loading. ${esc(e.message)}</div>`;
    }
  }
  poll(); setInterval(poll, 5000);
  document.addEventListener('visibilitychange', () => { if (!document.hidden) poll(); });
}

/* ---------- RESIDENT: updates on my own reports ---------- */
const myCodes = () => { try { return JSON.parse(localStorage.getItem('br_codes') || '[]'); } catch (e) { return []; } };
function saveCode(code, sig) {
  const c = myCodes().filter(x => x !== code); c.unshift(code); localStorage.setItem('br_codes', JSON.stringify(c.slice(0, 20)));
  if (sig) { const s = JSON.parse(localStorage.getItem('br_seen') || '{}'); s[code] = sig; localStorage.setItem('br_seen', JSON.stringify(s)); } // baseline: later changes are "new"
}
const feedGet = () => { try { return JSON.parse(localStorage.getItem('br_feed') || '[]'); } catch (e) { return []; } };
function pushFeed(code, msg) {
  const f = feedGet(); f.unshift({ code, msg, at: Date.now(), read: false });
  localStorage.setItem('br_feed', JSON.stringify(f.slice(0, 30))); document.dispatchEvent(new Event('br:feed'));
}
function watchMyReports(onData) {
  async function poll() {
    const codes = myCodes(); if (!codes.length) return;
    try {
      const rows = await api(BASE + 'api/status.php?codes=' + encodeURIComponent(codes.join(',')));
      const seen = JSON.parse(localStorage.getItem('br_seen') || '{}');
      rows.forEach(r => {
        const sig = r.status + '|' + r.updated_at;
        if (seen[r.code] && seen[r.code] !== sig) {
          const msg = r.code + ' is now "' + r.status + '"' + (r.note ? ': ' + r.note : '');
          toast(msg, 'ok'); osNotify('Update on your report', msg); pushFeed(r.code, msg); beep();
        }
        seen[r.code] = sig;
      });
      localStorage.setItem('br_seen', JSON.stringify(seen));
      if (onData) onData(rows);
    } catch (e) { }
  }
  poll(); setInterval(poll, 6000);
  document.addEventListener('visibilitychange', () => { if (!document.hidden) poll(); });
}
function mountResidentBell() {
  const host = $('.top-right') || $('.lbar nav'); if (!host || $('.rb')) return;
  const w = document.createElement('div'); w.className = 'bell-wrap rb';
  w.innerHTML = `<button type="button" class="bell" aria-label="Updates on my reports">${ic('bell')}<span class="badge" hidden>0</span></button>
    <div class="bell-panel" hidden><div class="bell-head"><b>Updates on my reports</b><a href="#">Mark all read</a></div><div class="rb-list"></div></div>`;
  host.prepend(w);
  const btn = $('button', w), panel = $('.bell-panel', w), list = $('.rb-list', w), badge = $('.badge', w);
  const draw = () => {
    const f = feedGet(), unread = f.filter(x => !x.read).length, codes = myCodes();
    badge.hidden = !unread; badge.textContent = unread;
    list.innerHTML = (f.length
      ? f.map(x => `<a class="bell-item ${x.read ? '' : 'new'}" href="${BASE}track.php?code=${encodeURIComponent(x.code)}">${esc(x.msg)}<small>${agoMs(x.at)}</small></a>`).join('')
      : '<div class="bell-item">No updates yet. When the barangay answers your report, it will show here.</div>')
      + (codes.length ? '<div class="bell-item"><small>Your reports</small>' + codes.slice(0, 4).map(c => `<a href="${BASE}track.php?code=${encodeURIComponent(c)}" style="display:block;color:#1d4e79">${esc(c)}</a>`).join('') + '</div>' : '');
  };
  btn.onclick = e => { e.stopPropagation(); panel.hidden = !panel.hidden; draw(); };
  document.addEventListener('click', e => { if (!w.contains(e.target)) panel.hidden = true; });
  $('.bell-head a', w).onclick = e => { e.preventDefault(); localStorage.setItem('br_feed', JSON.stringify(feedGet().map(x => ({ ...x, read: true })))); draw(); };
  document.addEventListener('br:feed', draw); draw();
}
