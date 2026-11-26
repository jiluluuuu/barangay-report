<?php
// One SVG sprite for the whole site. In PHP call ic('bell'); in JS call ic('bell').
function sprite() { echo '<svg width="0" height="0" style="position:absolute" aria-hidden="true"><defs>
<symbol id="i-dashboard" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="9"/><rect x="14" y="3" width="7" height="5"/><rect x="14" y="12" width="7" height="9"/><rect x="3" y="16" width="7" height="5"/></symbol>
<symbol id="i-reports" viewBox="0 0 24 24"><path d="M14 3H6a1 1 0 0 0-1 1v16a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V8z"/><path d="M14 3v5h5M9 13h6M9 17h6"/></symbol>
<symbol id="i-teams" viewBox="0 0 24 24"><circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c0-3.6 2.9-6 6.5-6s6.5 2.4 6.5 6"/><path d="M16 4.6a3.5 3.5 0 0 1 0 6.8M18 14.3c2 .7 3.5 2.6 3.5 5.7"/></symbol>
<symbol id="i-bell" viewBox="0 0 24 24"><path d="M6 8a6 6 0 1 1 12 0c0 7 3 8 3 8H3s3-1 3-8"/><path d="M10 20a2 2 0 0 0 4 0"/></symbol>
<symbol id="i-pin" viewBox="0 0 24 24"><path d="M12 21s7-6.2 7-11.5A7 7 0 0 0 5 9.5C5 14.8 12 21 12 21z"/><circle cx="12" cy="9.5" r="2.5"/></symbol>
<symbol id="i-camera" viewBox="0 0 24 24"><path d="M4 8h3l2-3h6l2 3h3a1 1 0 0 1 1 1v10a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V9a1 1 0 0 1 1-1z"/><circle cx="12" cy="13.5" r="3.5"/></symbol>
<symbol id="i-search" viewBox="0 0 24 24"><circle cx="11" cy="11" r="6.5"/><path d="M16 16l5 5"/></symbol>
<symbol id="i-check" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M8 12.5l3 3 5-6"/></symbol>
<symbol id="i-clock" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></symbol>
<symbol id="i-alert" viewBox="0 0 24 24"><path d="M12 3l10 18H2z"/><path d="M12 10v5M12 18v.01"/></symbol>
<symbol id="i-logout" viewBox="0 0 24 24"><path d="M9 4H5a1 1 0 0 0-1 1v14a1 1 0 0 0 1 1h4M15 8l4 4-4 4M19 12H9"/></symbol>
<symbol id="i-phone" viewBox="0 0 24 24"><path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a1 1 0 0 1-1 1A16 16 0 0 1 4 5a1 1 0 0 1 1-1z"/></symbol>
<symbol id="i-send" viewBox="0 0 24 24"><path d="M12 16V4M7 9l5-5 5 5M4 20h16"/></symbol>
<symbol id="i-tool" viewBox="0 0 24 24"><path d="M14.5 6.5a4 4 0 0 0-5 5L3.5 17.5a1.8 1.8 0 0 0 3 3l6-6a4 4 0 0 0 5-5l-2.5 2.5-2.5-.5-.5-2.5z"/></symbol>
<symbol id="i-chat" viewBox="0 0 24 24"><path d="M4 5h16v11H9l-5 4z"/></symbol>
<symbol id="i-back" viewBox="0 0 24 24"><path d="M15 5l-7 7 7 7"/></symbol>
</defs></svg>'; }
function ic($n, $c = '') { return '<svg class="ic ' . $c . '"><use href="#i-' . $n . '"/></svg>'; }
function gov_strip() { /* removed: the prototype has no top strip */ }
function public_footer() { ?>
<footer class="foot">
  <span><?= ic('pin') ?><?= e(BRGY_ADDR) ?></span>
  <span><?= ic('phone') ?>Hotline: <?= e(BRGY_HOTLINE) ?></span>
  <span><?= ic('clock') ?>Office hours: <?= e(BRGY_HOURS) ?></span>
</footer>
<?php } ?>
