<?php
require_once __DIR__ . '/lib/store.php';
require_once __DIR__ . '/lib/dashboard_layout.php';

header('Cache-Control: no-store');
talk_session_start();

if (! talk_logged_in()) {
    header('Location: index.php');
    exit;
}

talk_dash_head('বোর্ড স্ট্যাটাস', ['status.css']);
talk_dash_sidebar('status', (string) $_SESSION['talk_user']);
talk_dash_main_open();
?>
<div class="page-top">
  <div>
    <h1 class="page-title">বোর্ড স্ট্যাটাস</h1>
    <p class="page-lead">AP ও STA লাইভ রিপোর্ট — Wi‑Fi/HTTP দিয়ে ড্যাশবোর্ডে আসে (HaLow একা নয়)।</p>
  </div>
</div>
<p class="note status-hint">«ড্যাশবোর্ডে খবর নেই» = কিছু সময় HTTP রিপোর্ট নেই; সুইচ ON থাকলেও Laragon/AP+STA Wi‑Fi চেক করুন।</p>
<div class="board-strip" id="boardStripTop" aria-label="বোর্ড স্ট্যাটাস">
  <div class="card bst ap" id="topApBox">
    <span class="bst-id">AP · মাইক</span>
    <span class="bst-line" id="topApLine">লোড হচ্ছে…</span>
  </div>
  <div class="card bst sta" id="topStaBox">
    <span class="bst-id">STA · স্পিকার</span>
    <span class="bst-line" id="topStaLine">লোড হচ্ছে…</span>
  </div>
</div>
<div class="status-links">
  <a href="net.php">বোর্ড Wi‑Fi →</a>
  <a href="battery.php">ব্যাটারি →</a>
</div>
<script>
function boardLiveRow(row, serverNow, winSec, graceSec) {
  if (!row) return { online: false, fresh: false, weak: false, wifi_ok: false, ago_sec: null };
  const w = winSec || 50;
  const g = graceSec || 120;
  const now = serverNow || Math.floor(Date.now() / 1000);
  const seen = Number(row.seen_at || row.live_at) || 0;
  const ago = seen > 0 ? Math.max(0, now - seen) : null;
  const online = seen > 0 && ago != null && ago <= g;
  const fresh = seen > 0 && ago != null && ago <= w;
  const wifiOk = fresh && !!row.connected;
  return { online, fresh, weak: online && !fresh, wifi_ok: wifiOk, ago_sec: ago };
}

function boardDetailLine(row) {
  if (!row) return 'ডেটা নেই';
  if (!row.online) {
    if (row.ago_sec != null) {
      return 'ড্যাশবোর্ডে খবর নেই · শেষ ' + row.ago_sec + 's আগে (সুইচ ON থাকলেও Wi-Fi/PC লাগতে পারে)';
    }
    return 'ড্যাশবোর্ডে খবর নেই';
  }
  let s;
  if (row.weak) {
    s = 'চালু · রিপোর্ট দেরি (' + row.ago_sec + 's) — HaLow চলতে পারে, Wi-Fi/লারাগন চেক করুন';
  } else if (row.wifi_ok) {
    s = 'চালু · নেট ✓ ' + (row.live_ssid || '') + (row.ip ? ' · ' + row.ip : '');
  } else {
    s = 'চালু · Wi-Fi সংযুক্ত নয় (অ্যাডমিন)';
  }
  return s;
}

function paintBoards(boards, meta) {
  const win = (meta && meta.live_window) || 50;
  const grace = (meta && meta.live_grace) || 120;
  const now = (meta && meta.now) || Math.floor(Date.now() / 1000);
  for (const id of ['AP', 'STA']) {
    const raw = boards[id];
    const row = raw ? { ...raw, ...boardLiveRow(raw, now, win, grace) } : null;
    const topLine = document.getElementById(id === 'AP' ? 'topApLine' : 'topStaLine');
    const topBox = document.getElementById(id === 'AP' ? 'topApBox' : 'topStaBox');
    if (!topLine || !row) continue;
    topLine.textContent = boardDetailLine(row);
    if (topBox) topBox.classList.toggle('on', !!row.online);
  }
}

async function refresh() {
  try {
    const res = await fetch('api/net.php', { cache: 'no-store', credentials: 'same-origin' });
    if (res.status === 401) { window.location.href = 'index.php'; return; }
    const data = await res.json();
    if (!data.ok || !data.boards) return;
    paintBoards(data.boards, data);
  } catch (e) {}
}

refresh();
setInterval(refresh, 4000);
</script>
<?php talk_dash_main_close(); talk_dash_end(); ?>
