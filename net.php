<?php
require_once __DIR__ . '/lib/boardnet.php';
require_once __DIR__ . '/lib/store.php';
header('Cache-Control: no-store');
talk_session_start();
$boardApi = talk_device_upload_url();
$lanHint = talk_lan_base() ?? talk_base_url();

if (! talk_logged_in()) {
    header('Location: index.php');
    exit;
}

$who = htmlspecialchars((string) $_SESSION['talk_user'], ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="bn">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Wi-Fi · বোর্ড</title>
<style>
  :root {
    --page: #0c1812;
    --ink: #e8f2e6;
    --muted: #9db5a6;
    --gold: #d4b15a;
    --win-bg: #f3f3f3;
    --win-ink: #1a1a1a;
    --win-muted: #5c5c5c;
    --win-line: #e0e0e0;
    --win-hover: #ebebeb;
    --win-accent: #0067c0;
  }
  * { box-sizing: border-box; }
  body {
    margin: 0;
    min-height: 100vh;
    font-family: "Segoe UI", system-ui, sans-serif;
    background: var(--page);
    color: var(--ink);
  }
  a { color: var(--gold); }
  .top {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 16px 20px;
    flex-wrap: wrap;
    gap: 10px;
  }
  .top h1 { margin: 0; font-size: 20px; font-weight: 600; }
  .top p { margin: 4px 0 0; color: var(--muted); font-size: 13px; max-width: 520px; }
  .boards {
    display: grid;
    gap: 24px;
    padding: 0 16px 32px;
    max-width: 1100px;
    margin: 0 auto;
  }
  @media (min-width: 780px) {
    .boards { grid-template-columns: 1fr 1fr; align-items: start; }
  }
  .board-title {
    color: var(--gold);
    font-size: 14px;
    font-weight: 600;
    margin: 0 0 8px 4px;
  }
  .wifi-win {
    background: var(--win-bg);
    color: var(--win-ink);
    border-radius: 8px;
    box-shadow: 0 8px 32px rgba(0,0,0,.45);
    overflow: hidden;
    min-height: 420px;
    display: flex;
    flex-direction: column;
  }
  .wifi-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px 16px;
    border-bottom: 1px solid var(--win-line);
    font-weight: 600;
    font-size: 15px;
  }
  .wifi-head .tog {
    width: 40px;
    height: 22px;
    border-radius: 11px;
    background: var(--win-accent);
    position: relative;
  }
  .wifi-head .tog::after {
    content: "";
    position: absolute;
    width: 16px;
    height: 16px;
    background: #fff;
    border-radius: 50%;
    top: 3px;
    right: 3px;
  }
  .wifi-scroll {
    flex: 1;
    overflow: auto;
    max-height: 520px;
  }
  .row {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 16px;
    border-bottom: 1px solid var(--win-line);
    cursor: pointer;
    text-align: left;
    width: 100%;
    border-left: 0;
    border-right: 0;
    border-top: 0;
    background: transparent;
    font: inherit;
    color: inherit;
  }
  .row:hover { background: var(--win-hover); }
  .row.on { background: #e5f1fb; }
  .row.connected-row { cursor: default; }
  .row.connected-row:hover { background: transparent; }
  .sig { width: 22px; flex-shrink: 0; display: flex; align-items: flex-end; gap: 2px; height: 16px; }
  .sig i {
    display: block;
    width: 3px;
    background: var(--win-ink);
    border-radius: 1px;
  }
  .sig.b1 i:nth-child(1) { height: 5px; opacity: .35; }
  .sig.b1 i:nth-child(n+2) { height: 5px; opacity: .15; }
  .sig.b2 i:nth-child(-n+2) { height: 8px; }
  .sig.b2 i:nth-child(n+3) { height: 5px; opacity: .2; }
  .sig.b3 i:nth-child(-n+3) { height: 11px; }
  .sig.b3 i:last-child { height: 5px; opacity: .25; }
  .sig.b4 i { height: 14px; }
  .sig i:nth-child(1) { height: 5px; }
  .sig i:nth-child(2) { height: 8px; }
  .sig i:nth-child(3) { height: 11px; }
  .sig i:nth-child(4) { height: 14px; }
  .lock {
    width: 14px;
    height: 14px;
    flex-shrink: 0;
    opacity: .65;
    background: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%231a1a1a'%3E%3Cpath d='M17 8h-1V6a4 4 0 00-8 0v2H7a2 2 0 00-2 2v8a2 2 0 002 2h10a2 2 0 002-2v-8a2 2 0 00-2-2zm-3 0H10V6a2 2 0 114 0v2z'/%3E%3C/svg%3E") center/contain no-repeat;
  }
  .row-text { flex: 1; min-width: 0; }
  .row-text strong {
    display: block;
    font-size: 14px;
    font-weight: 600;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }
  .row-text span { font-size: 12px; color: var(--win-muted); }
  .btn-link {
    background: none;
    border: 0;
    color: var(--win-accent);
    font: inherit;
    font-size: 13px;
    cursor: pointer;
    padding: 4px 8px;
  }
  .btn-link:hover { text-decoration: underline; }
  .join {
    display: none;
    padding: 14px 16px 16px;
    border-top: 1px solid var(--win-line);
    background: #fafafa;
  }
  .join.show { display: block; }
  .row.pending-row { background: #e8f4fc; cursor: default; }
  .row.pending-row .row-text span { color: #0067c0; }
  .join label { display: block; font-size: 12px; color: var(--win-muted); margin-bottom: 6px; }
  .join input {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid #ccc;
    border-radius: 4px;
    font: inherit;
    margin-bottom: 10px;
  }
  .join button {
    width: 100%;
    padding: 10px;
    border: 0;
    border-radius: 4px;
    background: var(--win-accent);
    color: #fff;
    font-weight: 600;
    cursor: pointer;
  }
  .wifi-foot {
    padding: 10px 16px;
    border-top: 1px solid var(--win-line);
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 12px;
    color: var(--win-muted);
  }
  .wifi-foot button {
    background: none;
    border: 0;
    color: var(--win-accent);
    cursor: pointer;
    font: inherit;
  }
  .empty {
    padding: 20px 16px;
    font-size: 13px;
    color: var(--win-muted);
    line-height: 1.5;
  }
  .toast {
    min-height: 18px;
    font-size: 12px;
    color: var(--win-accent);
    padding: 0 16px 8px;
  }
  .stale { color: #a16207; font-size: 11px; padding: 0 16px 8px; }
</style>
</head>
<body>
<div class="top">
  <div>
    <h1>বোর্ড Wi-Fi</h1>
    <p><strong>ফোন/PC Wi-Fi list-এর মতো</strong>, কিন্তু তালিকা = <strong>ওই বোর্ডের কাছে</strong> যে নেট আছে (ল্যাপটপের list নয়)।</p>
    <p style="font-size:13px;color:var(--muted);max-width:640px;line-height:1.55;margin-top:8px">
      উদাহরণ: এক বোর্ড <strong>বাড়িতে</strong>, এক বোর্ড <strong>ঢাকায়</strong> — AP প্যানেলে বাড়ির আশেপাশের নাম, STA প্যানেলে ঢাকার আশেপাশের নাম।
      নামে ক্লিক → পাস → Connect = শুধু <strong>সেই বোর্ডে</strong> Wi-Fi সেট হবে।
    </p>
    <p style="font-size:12px;color:#a16207;max-width:640px;line-height:1.5;margin-top:8px">
      তালিকায় শুধু <strong>বোর্ড scan</strong> — আগে থেকে কোনো নাম লাগে না (ফোনের মতো)। বোর্ড একবার অ্যাডমিন সার্ভারে পৌঁছালেই তালিকা আসে; না হলে «নাম লিখে সংযোগ» (SSID+pass) → online হলে apply।
    </p>
    <p style="margin-top:8px;font-size:12px;color:#9db5a6">লোকাল API: <?php echo htmlspecialchars($boardApi, ENT_QUOTES, 'UTF-8'); ?></p>
  </div>
  <div><?php echo $who; ?> · <a href="index.php">ড্যাশবোর্ড</a></div>
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

function buildPane(id, row) {
  const wrap = document.createElement('div');
  const title = document.createElement('p');
  title.className = 'board-title';
  title.textContent = labels[id] || id;
  wrap.appendChild(title);

  const win = document.createElement('div');
  win.className = 'wifi-win';
  win.dataset.board = id;

  const head = document.createElement('div');
  head.className = 'wifi-head';
  head.innerHTML = '<span>Wi-Fi</span><div class="tog" title="চালু"></div>';
  win.appendChild(head);

  const scroll = document.createElement('div');
  scroll.className = 'wifi-scroll';

  if (row.connected && row.live_ssid) {
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
  if (row.connected && row.live_ssid) seen.add(row.live_ssid);

  if (row.pick_ssid && !(row.connected && row.live_ssid === row.pick_ssid)) {
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
      paintBoards(data.boards);
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

function paintBoards(boards) {
  if (userIsTypingWifi() || Date.now() < refreshPauseUntil) {
    return;
  }
  const root = document.getElementById('boards');
  root.innerHTML = '';
  for (const [id, row] of Object.entries(boards)) {
    root.appendChild(buildPane(id, row));
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
  paintBoards(data.boards);
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
setInterval(() => refresh(false), 12000);
</script>
</body>
</html>
