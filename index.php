<?php
require_once __DIR__ . '/lib/store.php';
$cfg = talk_config();
$upload = $cfg['upload_url'];
?>
<!DOCTYPE html>
<html lang="bn">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>হ্যালো কথোপকথন</title>
<style>
  :root {
    --bg: #14110e;
    --paper: #221c16;
    --ink: #f4ecdf;
    --muted: #b3a28c;
    --line: #3c3228;
    --ap: #8eae6a;
    --ap-bg: #24301c;
    --sta: #e09a55;
    --sta-bg: #3a2718;
    --danger: #e07a62;
  }
  * { box-sizing: border-box; }
  html, body { margin: 0; height: 100%; }
  body {
    background:
      radial-gradient(1200px 500px at 10% -10%, #2a241c 0%, transparent 55%),
      var(--bg);
    color: var(--ink);
    font-family: "Nirmala UI", "Segoe UI", sans-serif;
    display: flex;
    flex-direction: column;
    min-height: 100%;
  }
  header {
    display: flex;
    justify-content: space-between;
    gap: 16px;
    align-items: flex-end;
    padding: 22px 28px 16px;
    border-bottom: 1px solid var(--line);
  }
  .mark {
    width: 14px;
    height: 14px;
    margin-bottom: 8px;
    background: var(--sta);
    clip-path: polygon(50% 0, 100% 50%, 50% 100%, 0 50%);
  }
  h1 {
    margin: 0;
    font-size: 28px;
    font-weight: 700;
    letter-spacing: 0;
  }
  .sub { margin: 4px 0 0; color: var(--muted); font-size: 14px; }
  .stats { display: flex; gap: 18px; color: var(--muted); font-size: 13px; }
  .stats b { color: var(--ink); font-weight: 650; }
  main {
    flex: 1;
    overflow: auto;
    padding: 18px 28px 28px;
  }
  .log { max-width: 860px; margin: 0 auto; display: flex; flex-direction: column; gap: 12px; }
  .day {
    align-self: center;
    color: var(--muted);
    font-size: 12px;
    letter-spacing: 0.08em;
    padding: 8px 0 2px;
  }
  .row { display: flex; }
  .row.ap { justify-content: flex-start; }
  .row.sta { justify-content: flex-end; }
  .bubble {
    width: min(460px, 100%);
    border: 1px solid var(--line);
    border-radius: 16px;
    padding: 12px 14px 10px;
    background: var(--paper);
  }
  .row.ap .bubble { background: var(--ap-bg); border-color: #3d5230; }
  .row.sta .bubble { background: var(--sta-bg); border-color: #6a4528; }
  .who { display: flex; justify-content: space-between; gap: 12px; font-size: 13px; }
  .who strong { font-size: 15px; }
  .row.ap .who strong { color: var(--ap); }
  .row.sta .who strong { color: var(--sta); }
  .when { color: var(--muted); }
  audio { width: 100%; margin-top: 8px; height: 36px; }
  .fresh { animation: rise 0.45s ease; }
  @keyframes rise {
    from { opacity: 0; transform: translateY(8px); }
    to { opacity: 1; transform: none; }
  }
  .empty {
    max-width: 520px;
    margin: 12vh auto 0;
    text-align: center;
    color: var(--muted);
  }
  .empty h2 { color: var(--ink); font-size: 22px; margin: 0 0 8px; }
  .pair { display: flex; justify-content: center; gap: 18px; margin-bottom: 18px; }
  .chip {
    min-width: 92px;
    padding: 10px 14px;
    border-radius: 999px;
    border: 1px solid var(--line);
    font-weight: 700;
  }
  .chip.ap { color: var(--ap); background: var(--ap-bg); }
  .chip.sta { color: var(--sta); background: var(--sta-bg); }
  footer {
    border-top: 1px solid var(--line);
    padding: 12px 28px 16px;
    color: var(--muted);
    font-size: 13px;
    line-height: 1.55;
  }
  @media (max-width: 760px) {
    header {
      flex-direction: column;
      align-items: flex-start;
      padding: 16px 16px 12px;
    }
    h1 { font-size: 24px; }
    main { padding: 14px 16px 24px; }
    footer { padding: 12px 16px 16px; }
  }
  code {
    color: var(--ink);
    background: #2c261f;
    padding: 1px 6px;
    border-radius: 6px;
  }
  .err { color: var(--danger); }
</style>
</head>
<body>
<header>
  <div>
    <div class="mark"></div>
    <h1>হ্যালো কথোপকথন</h1>
    <p class="sub">বোর্ড দুটি নেট পেলে যে কথা হয়েছিল, সেটা এখানে শোনা যাবে।</p>
  </div>
  <div class="stats">
    <div>কথা<br><b id="count">০</b></div>
    <div>শেষ দেখা<br><b id="seen">—</b></div>
  </div>
</header>
<main id="main">
  <div class="empty" id="empty">
    <div class="pair"><span class="chip ap">AP</span><span class="chip sta">STA</span></div>
    <h2>এখনো কোনো কথা আসেনি</h2>
    <p>দুই বোর্ডে কথা বলার পর সেগুলো Wi-Fi পেলে এই পাতায় চলে আসবে। AP বাঁ দিকে, STA ডান দিকে।</p>
  </div>
  <div class="log" id="log" hidden></div>
</main>
<footer>
  Laragon চালু রাখুন। বোর্ড আর এই পিসি একই Wi-Fi তে থাকবে।
  <code>shared/net_config.h</code> ফাইলে Wi-Fi নাম, পাসওয়ার্ড লিখে <code>NET_ON</code> কে <code>1</code> করুন, তারপর দুই স্কেচ আবার আপলোড দিন।
  পাঠানোর ঠিকানা: <code id="url"><?php echo htmlspecialchars($upload, ENT_QUOTES, 'UTF-8'); ?></code>
  <span id="status"></span>
</footer>
<script>
const logEl = document.getElementById('log');
const emptyEl = document.getElementById('empty');
const countEl = document.getElementById('count');
const seenEl = document.getElementById('seen');
const statusEl = document.getElementById('status');
const mainEl = document.getElementById('main');
const bn = ['০','১','২','৩','৪','৫','৬','৭','৮','৯'];
let known = new Set();
let stick = true;

function banglaNum(n) {
  return String(n).replace(/\d/g, d => bn[d]);
}

mainEl.addEventListener('scroll', () => {
  stick = mainEl.scrollHeight - mainEl.scrollTop - mainEl.clientHeight < 80;
});

function render(items) {
  countEl.textContent = banglaNum(items.length);
  if (!items.length) {
    emptyEl.hidden = false;
    logEl.hidden = true;
    logEl.innerHTML = '';
    known = new Set();
    return;
  }
  emptyEl.hidden = true;
  logEl.hidden = false;
  const ids = items.map(it => it.id).join('|');
  if (logEl.dataset.ids === ids) return;
  logEl.dataset.ids = ids;
  const next = new Set(items.map(it => it.id));
  let html = '';
  let day = '';
  for (const it of items) {
    if (it.day !== day) {
      day = it.day;
      html += '<div class="day">' + day + '</div>';
    }
    const side = it.board === 'STA' ? 'sta' : 'ap';
    const fresh = known.size && !known.has(it.id) ? ' fresh' : '';
    const who = it.board === 'STA' ? 'STA বলেছে' : 'AP বলেছে';
    html += '<div class="row ' + side + '"><div class="bubble' + fresh + '">';
    html += '<div class="who"><strong>' + who + '</strong><span class="when">' + it.at + ' · ' + it.sec + ' সে.</span></div>';
    html += '<audio controls preload="metadata" src="api/audio.php?id=' + encodeURIComponent(it.id) + '"></audio>';
    html += '</div></div>';
  }
  logEl.innerHTML = html;
  known = next;
  if (stick) mainEl.scrollTop = mainEl.scrollHeight;
  const last = items[items.length - 1];
  seenEl.textContent = last ? last.at : '—';
}

async function load() {
  try {
    const res = await fetch('api/list.php', { cache: 'no-store' });
    if (!res.ok) throw new Error('bad');
    const data = await res.json();
    render(Array.isArray(data.utterances) ? data.utterances : []);
    statusEl.textContent = '';
    statusEl.className = '';
  } catch (e) {
    statusEl.textContent = ' তালিকা পড়া যায়নি।';
    statusEl.className = 'err';
  }
}

load();
setInterval(load, 4000);
</script>
</body>
</html>
