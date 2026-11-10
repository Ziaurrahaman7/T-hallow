<?php
require_once __DIR__ . '/lib/store.php';
require_once __DIR__ . '/lib/dashboard_layout.php';
require_once __DIR__ . '/lib/battery.php';

header('Cache-Control: no-store');
talk_session_start();

if (! talk_logged_in()) {
    header('Location: index.php');
    exit;
}

$capMah = talk_battery_capacity_mah();
$avgMa = talk_battery_avg_ma();

talk_dash_head('ব্যাটারি', ['battery.css']);
talk_dash_sidebar('battery', (string) $_SESSION['talk_user']);
talk_dash_main_open();
?>
<div class="page-top">
  <div>
    <h1 class="page-title">ব্যাটারি</h1>
    <p class="page-lead">AP ও STA থেকে আসা ADC রিডিং — Wi‑Fi রিপোর্ট থাকলে আপডেট হয়।</p>
  </div>
</div>
<div class="info-stack">
  <p class="note">USB/Laragon চার্জে % ভুল দেখাতে পারে। সঠিক মাপের জন্য USB খুলে 18650 দিয়ে চালান।</p>
  <p class="note warn">Runtime ≈ <?php echo (int) $capMah; ?> mAh / ~<?php echo (int) $avgMa; ?> mA — শুধু আনুমানিক।</p>
</div>
<div class="bat-grid" id="batGrid">
  <div class="card bat-card" id="batAp"><p class="bat-empty">লোড হচ্ছে…</p></div>
  <div class="card bat-card" id="batSta"><p class="bat-empty">লোড হচ্ছে…</p></div>
</div>
<script>
const BAT_CAP = <?php echo (int) $capMah; ?>;
const BAT_AVG = <?php echo (int) $avgMa; ?>;

function banglaNum(n) {
  const map = '০১২৩৪৫৬৭৮৯';
  return String(n).replace(/\d/g, (d) => map[d]);
}

function boardLiveRow(row, serverNow, winSec, graceSec) {
  const w = winSec || 50;
  const g = graceSec || 120;
  const now = serverNow || Math.floor(Date.now() / 1000);
  const seen = Number(row.seen_at || row.live_at) || 0;
  const ago = seen > 0 ? Math.max(0, now - seen) : null;
  const online = seen > 0 && ago != null && ago <= g;
  const fresh = seen > 0 && ago != null && ago <= w;
  return { online, fresh, weak: online && !fresh, wifi_ok: fresh && !!row.connected, ago_sec: ago };
}

function runtimeMin(pct) {
  if (pct <= 0 || BAT_CAP <= 0 || BAT_AVG <= 0) return null;
  return Math.round((pct / 100) * BAT_CAP / BAT_AVG * 60);
}

function runtimeLabel(min) {
  if (!min || min <= 0) return '—';
  if (min < 90) return banglaNum(min) + ' মিনিট (আনুমানিক)';
  const h = Math.floor(min / 60);
  const m = min % 60;
  if (m === 0) return banglaNum(h) + ' ঘণ্টা (আনুমানিক)';
  return banglaNum(h) + ' ঘ ' + banglaNum(m) + ' মি';
}

function pctClass(pct, usb) {
  if (usb) return 'charge';
  if (pct <= 0) return '';
  if (pct <= 20) return 'low';
  if (pct <= 45) return 'mid';
  return '';
}

function batterySvg(level) {
  const w = Math.max(4, Math.min(100, level));
  return `<svg class="bat-icon ${pctClass(level, false)}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
    <rect x="2" y="7" width="18" height="10" rx="2"/>
    <path d="M22 11v2"/>
    <rect x="4" y="9" width="${(14 * w / 100).toFixed(2)}" height="6" rx="1" fill="currentColor" stroke="none" opacity="0.35"/>
  </svg>`;
}

function renderCard(el, id, row, meta) {
  if (!row) {
    el.innerHTML = '<p class="bat-empty">ডেটা নেই</p>';
    return;
  }
  const live = boardLiveRow(row, meta && meta.now, meta && meta.live_window, meta && meta.live_grace);
  const pct = Number(row.bat_pct) || 0;
  const mv = Number(row.bat_mv) || 0;
  const usb = !!row.bat_usb;
  const pin = row.bat_pin != null && row.bat_pin >= 0 ? ('GPIO ' + row.bat_pin) : '—';
  const min = runtimeMin(usb ? 0 : pct);
  const title = id === 'AP' ? 'AP · মাইক' : 'STA · স্পিকার';
  const role = id === 'AP' ? 'মাইক বোর্ড' : 'স্পিকার বোর্ড';

  let pctHtml;
  let barW = 0;
  let hint = '';

  if (!live.online) {
    pctHtml = '—';
    hint = live.ago_sec != null
      ? 'শেষ রিপোর্ট ' + live.ago_sec + ' সেকেন্ড আগে — বোর্ড online হলে আবার দেখাবে।'
      : 'এখনো রিপোর্ট নেই।';
  } else if (usb) {
    pctHtml = 'USB';
    barW = 100;
    hint = 'চার্জ/USB সংযুক্ত — ADC দিয়ে সঠিক % পাওয়া যাবে না।';
  } else if (pct <= 0 && mv <= 0) {
    pctHtml = '—';
    hint = 'ADC ডেটা নেই · USB খুলুন · firmware + GPIO ' + pin + ' চেক করুন।';
  } else {
    pctHtml = banglaNum(pct) + '<span>%</span>';
    barW = Math.min(100, Math.max(0, pct));
    hint = mv > 0 ? (mv + ' mV · ' + pin) : pin;
  }

  const barCls = pctClass(pct, usb);
  const statusCls = live.online ? 'on' : 'off';
  const statusTxt = live.online ? (live.weak ? 'চালু · দেরি' : 'চালু') : 'অফলাইন';

  el.innerHTML = `
    <div class="bat-card-head">
      <div><h2>${title}</h2><div class="role">${role}</div></div>
      <span class="bat-status ${statusCls}"><i class="dot"></i>${statusTxt}</span>
    </div>
    <div class="bat-meter">
      ${batterySvg(usb ? 50 : barW)}
      <div class="bat-pct ${barCls}">${pctHtml}</div>
    </div>
    <div class="bat-bar ${barCls}"><i style="width:${barW}%"></i></div>
    <div class="bat-meta">
      <div><strong>Runtime</strong> ${live.online && !usb && pct > 0 ? runtimeLabel(min) : '—'}</div>
      <div>${hint}</div>
      ${live.wifi_ok ? '<div><strong>Wi‑Fi</strong> ' + (row.live_ssid || '') + (row.ip ? ' · ' + row.ip : '') + '</div>' : ''}
    </div>`;
}

async function refresh() {
  try {
    const res = await fetch('api/net.php', { cache: 'no-store', credentials: 'same-origin' });
    if (res.status === 401) { window.location.href = 'index.php'; return; }
    const data = await res.json();
    if (!data.ok || !data.boards) return;
    renderCard(document.getElementById('batAp'), 'AP', data.boards.AP, data);
    renderCard(document.getElementById('batSta'), 'STA', data.boards.STA, data);
  } catch (e) {}
}

refresh();
setInterval(refresh, 4000);
</script>
<?php talk_dash_main_close(); talk_dash_end(); ?>
