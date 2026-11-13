<?php
require_once __DIR__ . '/lib/boardnet.php';
require_once __DIR__ . '/lib/store.php';
require_once __DIR__ . '/lib/dashboard_layout.php';
header('Cache-Control: no-store');
talk_session_start();
$boardApi = talk_device_upload_url();

if (! talk_logged_in()) {
    header('Location: index.php');
    exit;
}

talk_dash_head('বোর্ড Wi‑Fi', ['net.css']);
talk_dash_sidebar('net', (string) $_SESSION['talk_user']);
talk_dash_main_open();
?>
<div class="page-top">
  <div>
    <h1 class="page-title">বোর্ড Wi‑Fi</h1>
    <p class="page-lead">বোর্ড যেখানেই থাকুক — নিজে স্ক্যান করে তালিকা সার্ভারে পাঠায় (প্রতি ~৪ মিনিট + সংযোগের সময়)। ল্যাপটপের Wi‑Fi list নয়।</p>
  </div>
</div>
<div class="info-stack">
  <p class="note">নেটওয়ার্ক বেছে নিন → পাসওয়ার্ড → Connect। শুধু সেই বোর্ডে Wi‑Fi সেট হবে (AP ও STA আলাদা)।</p>
  <p class="note warn">তালিকা <strong>ল্যাপটপের Wi‑Fi নয়</strong> — বোর্ড যেখানে দাঁড়িয়ে সেখানকার নেট স্ক্যান করে সার্ভারে পাঠায়। বোর্ড PC-র নেটে না থাকলে তালিকা খালি থাকে (HaLow কথা আলাদা)।</p>
  <p class="note warn">অন্য জায়গায় গেলে: PC+বোর্ড একই Wi‑Fi (ফোন হটস্পট)। <code>localhost</code> / <code>T-hallow.test</code> দিয়ে ড্যাশবোর্ড খুললে LAN IP + <code>servers.json</code> নিজে ঠিক হয়; IP বদলালে <code>shared/net_config.local.h</code> আপডেট দেখে AP/STA re-flash করুন।</p>
  <p class="note warn">পুরনো SSID মুছতে: নেট «থাকা» নেই দেখালে Disconnect → ২ মিনিট পর firmware-এর ডিফল্ট SSID চেষ্টা; নাম লিখে সংযোগ = online হলে apply।</p>
  <p class="note">API: <code><?php echo htmlspecialchars($boardApi, ENT_QUOTES, 'UTF-8'); ?></code></p>
</div>
<div class="boards" id="boards"></div>
<script>
const labels = { AP: 'AP · মাইক বোর্ড', STA: 'STA · স্পিকার বোর্ড' };

function bars(rssi) {
  if (rssi >= -55) return 4;
  if (rssi >= -65) return 3;
  if (rssi >= -75) return 2;
  return 1;
}

function sigHtml(rssi) {
  const b = bars(rssi);
  return `<div class="sig b${b}"><i></i><i></i><i></i><i></i></div><div class="lock" title="Secured"></div>`;
}

function fmtAgo(ts) {
  if (!ts) return 'এখনো খবর নেই';
  const sec = Math.max(0, Math.floor(Date.now() / 1000 - ts));
  if (sec < 60) return sec + ' সেকেন্ড আগে';
  if (sec < 3600) return Math.floor(sec / 60) + ' মিনিট আগে';
  return Math.floor(sec / 3600) + ' ঘণ্টা আগে';
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

function boardStatusLine(row, meta) {
  const live = boardLiveRow(row, meta && meta.now, meta && meta.live_window, meta && meta.live_grace);
  const on = live.online;
  const wifi = live.wifi_ok;
  const power = document.createElement('span');
  power.className = on ? 'on' : 'off';
  power.innerHTML = '<i class="dot"></i>' + (on ? (live.weak ? 'চালু · রিপোর্ট দেরি' : 'বোর্ড চালু') : 'ড্যাশবোর্ডে খবর নেই (≥২ মিনিট)');
  const net = document.createElement('span');
  if (!on) {
    net.textContent = 'নেট: —';
    net.className = 'wifi-bad';
  } else if (wifi) {
    net.className = 'wifi-ok';
    net.textContent = 'নেট যুক্ত · ' + (row.live_ssid || '') + (row.ip ? ' · ' + row.ip : '');
  } else {
    net.className = 'wifi-bad';
    net.textContent = 'চালু, কিন্তু Wi-Fi সংযুক্ত নয়';
  }
  const bar = document.createElement('div');
  bar.className = 'board-status';
  bar.appendChild(power);
  bar.appendChild(net);
  return bar;
}

function buildPane(id, row) {
  const wrap = document.createElement('div');
  const title = document.createElement('p');
  title.className = 'board-title';
  title.textContent = labels[id] || id;
  wrap.appendChild(title);
  wrap.appendChild(boardStatusLine(row, window.__boardMeta || null));

  const win = document.createElement('div');
  win.className = 'wifi-win';
  win.dataset.board = id;

  const head = document.createElement('div');
  head.className = 'wifi-head';
  head.innerHTML = '<span>Wi-Fi</span><div class="tog" title="চালু"></div>';
  win.appendChild(head);

  const scroll = document.createElement('div');
  scroll.className = 'wifi-scroll';

  if (row.wifi_ok && row.live_ssid) {
    const cr = document.createElement('div');
    cr.className = 'row connected-row';
    cr.innerHTML = sigHtml(row.rssi) + `<div class="row-text"><strong></strong><span>Connected, secured · ${row.ip || ''}</span></div>`;
    cr.querySelector('strong').textContent = row.live_ssid;
    const disc = document.createElement('button');
    disc.type = 'button';
    disc.className = 'btn-link';
    disc.textContent = 'Disconnect';
    disc.addEventListener('click', () => api(id, { action: 'disconnect' }));
    cr.appendChild(disc);
    scroll.appendChild(cr);
  }

  const seen = new Set();
  if (row.wifi_ok && row.live_ssid) seen.add(row.live_ssid);

  if (row.pick_ssid && !(row.wifi_ok && row.live_ssid === row.pick_ssid)) {
    seen.add(row.pick_ssid);
    const pr = document.createElement('div');
    pr.className = 'row pending-row';
    pr.innerHTML = sigHtml(-55) + '<div class="row-text"><strong></strong><span>সেভ করা — password দিয়ে Connect, বোর্ড online হলে যুক্ত</span></div>';
    pr.querySelector('strong').textContent = row.pick_ssid;
    pr.addEventListener('click', () => pickNetwork(win, row.pick_ssid));
    scroll.appendChild(pr);
  }

  const list = row.scan || [];
  if (list.length === 0 && seen.size === 0) {
    const em = document.createElement('div');
    em.className = 'empty';
    em.textContent = 'বোর্ড scan list এখনো নেই। «নাম লিখে সংযোগ» → SSID → নিচে password → Connect (শুধু নাম লিখলে save হয় না)।';
    scroll.appendChild(em);
  } else if (list.length > 0) {
    list.forEach((n) => {
      if (seen.has(n.ssid)) return;
      seen.add(n.ssid);
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'row';
      btn.innerHTML = sigHtml(n.rssi) + '<div class="row-text"><strong></strong><span>Secured</span></div>';
      btn.querySelector('strong').textContent = n.ssid;
      btn.addEventListener('click', () => pickNetwork(win, n.ssid));
      scroll.appendChild(btn);
    });
  }

  win.appendChild(scroll);

  const join = document.createElement('div');
  join.className = 'join';
  join.innerHTML = `
    <label>Wi-Fi নাম (SSID)</label>
    <input type="text" class="ssid" maxlength="32" autocomplete="off" placeholder="Redmi 13C">
    <label>পাসওয়ার্ড</label>
    <input type="password" class="pass" maxlength="64" autocomplete="off" placeholder="হটস্পট/নেটের পাস">
    <button type="button" class="go">Connect — সেভ করুন</button>`;
  if (row.pick_ssid) {
    join.classList.add('show');
    join.querySelector('.ssid').value = row.pick_ssid;
  }
  win.appendChild(join);

  const toast = document.createElement('div');
  toast.className = 'toast';
  win.appendChild(toast);

  const stale = document.createElement('div');
  stale.className = 'stale';
  let st = 'স্ক্যান: ' + fmtAgo(row.scan_at) + ' · বোর্ড: ' + fmtAgo(row.live_at);
  if (row.pick_ssid) st += ' · সেট: ' + row.pick_ssid;
  stale.textContent = st;
  win.appendChild(stale);

  const foot = document.createElement('div');
  foot.className = 'wifi-foot';
  const manual = document.createElement('button');
  manual.type = 'button';
  manual.textContent = 'নাম লিখে সংযোগ';
  manual.addEventListener('click', () => {
    const name = prompt('Wi-Fi নাম (SSID):', row.pick_ssid || '');
    if (name && name.trim()) pickNetwork(win, name.trim());
  });
  const rescan = document.createElement('button');
  rescan.type = 'button';
  rescan.textContent = 'আবার স্ক্যান';
  rescan.addEventListener('click', () => api(id, { action: 'rescan' }, toast));
  foot.appendChild(manual);
  foot.appendChild(rescan);
  win.appendChild(foot);

  join.querySelector('.go').addEventListener('click', () => {
    const ssid = join.querySelector('.ssid').value.trim();
    const pass = join.querySelector('.pass').value;
    if (!ssid) {
      toast.textContent = 'SSID লিখুন।';
      return;
    }
    if (!pass) {
      toast.textContent = 'পাসওয়ার্ড লিখে Connect চাপুন।';
      return;
    }
    api(id, { action: 'connect', ssid, pass }, toast, ssid);
  });

  wrap.appendChild(win);
  return wrap;
}

function pickNetwork(win, ssid) {
  win.querySelectorAll('.row.on').forEach((r) => r.classList.remove('on'));
  win.querySelectorAll('.row').forEach((r) => {
    if (r.querySelector('strong')?.textContent === ssid) r.classList.add('on');
  });
  const join = win.querySelector('.join');
  join.classList.add('show');
  join.querySelector('.ssid').value = ssid;
  join.querySelector('.pass').value = '';
  join.querySelector('.pass').focus();
}

async function api(board, body, toast, ssidLabel) {
  try {
    const res = await fetch('api/net.php', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ board, ...body })
    });
    let data = {};
    try { data = await res.json(); } catch { /* ignore */ }
    if (res.status === 401 || data.err === 'login') {
      if (toast) toast.textContent = 'Login শেষ — index.php থেকে আবার ঢুকুন।';
      setTimeout(() => { window.location.href = 'index.php'; }, 1500);
      return;
    }
    if (!res.ok || !data.ok) throw new Error('x');
    if (toast) {
      if (body.action === 'connect') {
        const n = ssidLabel || body.ssid || '';
        toast.textContent = n ? ('সেভ: «' + n + '» — বোর্ড online হলে যুক্ত হবে।') : 'সেভ হয়েছে।';
      } else {
        toast.textContent = 'পাঠানো হয়েছে।';
      }
    }
    refreshPauseUntil = Date.now() + 5000;
    if (data.boards) {
      paintBoards(data.boards, data);
    } else {
      await refresh(true);
    }
  } catch {
    if (toast) toast.textContent = 'সেভ হয়নি — SSID+password দিয়ে Connect চাপুন; Laragon Apache চালু আছে কিনা দেখুন।';
  }
}

let refreshPaused = false;
let refreshPauseUntil = 0;

function userIsTypingWifi() {
  const el = document.activeElement;
  if (!el || !el.closest) return false;
  return el.closest('.join') && (el.classList.contains('pass') || el.classList.contains('ssid'));
}

function paintBoards(boards, meta) {
  if (userIsTypingWifi() || Date.now() < refreshPauseUntil) {
    return;
  }
  window.__boardMeta = meta || window.__boardMeta || null;
  const root = document.getElementById('boards');
  root.innerHTML = '';
  for (const [id, row] of Object.entries(boards)) {
    const live = boardLiveRow(row, meta && meta.now, meta && meta.live_window, meta && meta.live_grace);
    root.appendChild(buildPane(id, { ...row, ...live }));
  }
}

async function refresh(force) {
  if (!force && (refreshPaused || userIsTypingWifi() || Date.now() < refreshPauseUntil)) {
    return;
  }
  const res = await fetch('api/net.php', { credentials: 'same-origin' });
  if (res.status === 401) {
    window.location.href = 'index.php';
    return;
  }
  const data = await res.json();
  if (!data.ok) return;
  paintBoards(data.boards, data);
}

document.addEventListener('focusin', (e) => {
  if (e.target && e.target.closest && e.target.closest('.join')) {
    refreshPaused = true;
  }
});
document.addEventListener('focusout', (e) => {
  if (e.target && e.target.closest && e.target.closest('.join')) {
    refreshPauseUntil = Date.now() + 8000;
    setTimeout(() => { refreshPaused = false; }, 8000);
  }
});

refresh(true);
setInterval(() => refresh(false), 8000);
</script>
<?php talk_dash_main_close(); talk_dash_end(); ?>
