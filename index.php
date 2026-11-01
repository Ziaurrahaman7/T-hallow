<?php
require_once __DIR__ . '/lib/store.php';
header('Cache-Control: no-store');
talk_session_start();

if (isset($_GET['out'])) {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'] ?? '', (bool) $params['secure'], (bool) $params['httponly']);
    }
    session_destroy();
    header('Location: index.php');
    exit;
}

$loginError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user'], $_POST['pass'])) {
    $name = trim((string) $_POST['user']);
    if (talk_try_login($name, (string) $_POST['pass'])) {
        session_regenerate_id(true);
        $_SESSION['talk_user'] = $name;
        header('Location: index.php');
        exit;
    }
    $loginError = 'নাম বা পাসওয়ার্ড মিলছে না।';
}

if (!talk_logged_in()) {
    $bad = $loginError !== '';
    ?>
<!DOCTYPE html>
<html lang="bn">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>ঢুকুন · হ্যালো</title>
<style>
  :root {
    --bg: #07140f;
    --ink: #e8f2e6;
    --muted: #9db5a6;
    --line: rgba(212, 177, 90, 0.35);
    --gold: #d4b15a;
    --danger: #e07a62;
  }
  * { box-sizing: border-box; }
  html, body { margin: 0; height: 100%; }
  body {
    min-height: 100%;
    display: grid;
    place-items: center;
    padding: 24px;
    color: var(--ink);
    font-family: "Nirmala UI", "Segoe UI", sans-serif;
    background:
      radial-gradient(680px 360px at 50% -8%, rgba(212, 177, 90, 0.18), transparent 62%),
      radial-gradient(520px 320px at 100% 100%, rgba(31, 122, 69, 0.2), transparent 55%),
      var(--bg);
  }
  .card {
    width: min(420px, 100%);
    padding: 36px 32px 28px;
    border-radius: 28px;
    border: 1px solid var(--line);
    background: linear-gradient(180deg, rgba(20, 48, 34, 0.96), rgba(8, 24, 16, 0.98));
    box-shadow: 0 30px 80px rgba(0, 0, 0, 0.45);
  }
  .mark {
    width: 18px;
    height: 18px;
    margin-bottom: 18px;
    background: var(--gold);
    clip-path: polygon(50% 0, 100% 50%, 50% 100%, 0 50%);
    box-shadow: 0 0 24px rgba(212, 177, 90, 0.4);
  }
  h1 { margin: 0; font-size: 32px; font-weight: 700; }
  .sub { margin: 8px 0 26px; color: var(--muted); font-size: 15px; line-height: 1.5; }
  label { display: block; margin-bottom: 14px; color: var(--muted); font-size: 13px; }
  input {
    display: block;
    width: 100%;
    margin-top: 6px;
    font: inherit;
    color: var(--ink);
    background: #0c1c14;
    border: 1px solid var(--line);
    border-radius: 14px;
    padding: 12px 14px;
    outline: none;
  }
  input:focus { border-color: rgba(212, 177, 90, 0.75); }
  button {
    width: 100%;
    margin-top: 6px;
    font: inherit;
    font-weight: 700;
    color: #142216;
    background: linear-gradient(180deg, #e6c97a, #c6a15b);
    border: 0;
    border-radius: 14px;
    padding: 12px 14px;
    cursor: pointer;
  }
  button:hover { filter: brightness(1.05); }
  .err {
    min-height: 22px;
    margin: 12px 0 0;
    color: var(--danger);
    font-size: 13px;
  }
</style>
</head>
<body>
  <form class="card" method="post" action="index.php" autocomplete="on">
    <div class="mark"></div>
    <h1>হ্যালো</h1>
    <p class="sub">কথোপকথন দেখতে নাম আর পাসওয়ার্ড দিয়ে ঢুকুন।</p>
    <label>নাম
      <input name="user" maxlength="40" required autocomplete="username" value="<?php echo $bad ? htmlspecialchars(trim((string) ($_POST['user'] ?? '')), ENT_QUOTES, 'UTF-8') : ''; ?>">
    </label>
    <label>পাসওয়ার্ড
      <input name="pass" type="password" maxlength="80" required autocomplete="current-password">
    </label>
    <button type="submit">সাইন ইন</button>
    <p class="err"><?php echo htmlspecialchars($loginError, ENT_QUOTES, 'UTF-8'); ?></p>
  </form>
</body>
</html>
    <?php
    exit;
}

$cfg = talk_config();
$uploadUrls = talk_upload_urls();
$upload = $uploadUrls[0] ?? $cfg['upload_url'];
$upload2 = $uploadUrls[1] ?? '';
$who = htmlspecialchars((string) $_SESSION['talk_user'], ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="bn">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>হ্যালো কথোপকথন</title>
<style>
  :root {
    --bg: #07140f;
    --ink: #e8f2e6;
    --muted: #9db5a6;
    --line: rgba(212, 177, 90, 0.28);
    --gold: #d4b15a;
    --army: #1f7a45;
    --ap: #e6c97a;
    --ap-bg: #1a3324;
    --sta: #8fbf9a;
    --sta-bg: #123528;
    --danger: #e07a62;
  }
  * { box-sizing: border-box; }
  html, body { margin: 0; height: 100%; }
  body {
    color: var(--ink);
    font-family: "Nirmala UI", "Segoe UI", sans-serif;
    background: var(--bg);
  }
  .dash {
    height: 100%;
    display: flex;
    min-height: 0;
  }
  .nav {
    width: 248px;
    flex: none;
    display: flex;
    flex-direction: column;
    gap: 18px;
    padding: 22px 16px 16px;
    background: linear-gradient(180deg, #123524 0%, #0c2418 100%);
    border-right: 1px solid var(--line);
  }
  .brand { display: flex; gap: 12px; align-items: center; padding: 0 8px 8px; }
  .brand strong { display: block; font-size: 20px; }
  .brand span { color: var(--gold); font-size: 13px; }
  .mark {
    width: 16px;
    height: 16px;
    flex: none;
    background: linear-gradient(135deg, #e6c97a, var(--gold));
    clip-path: polygon(50% 0, 100% 50%, 50% 100%, 0 50%);
  }
  .nav nav { display: flex; flex-direction: column; gap: 6px; }
  button.menu {
    width: 100%;
    text-align: left;
    color: var(--muted);
    background: transparent;
    border: 1px solid transparent;
    border-radius: 12px;
    padding: 12px 14px;
    font-weight: 650;
  }
  button.menu:hover { color: var(--ink); background: rgba(212, 177, 90, 0.08); }
  button.menu.on {
    color: #142216;
    background: linear-gradient(180deg, #e6c97a, #c6a15b);
    border-color: transparent;
  }
  .navfoot {
    margin-top: auto;
    display: flex;
    flex-direction: column;
    gap: 8px;
    padding-top: 12px;
    border-top: 1px solid var(--line);
  }
  .user, .out {
    border-radius: 12px;
    padding: 8px 12px;
    font-size: 13px;
  }
  .user { color: var(--ink); background: rgba(0, 0, 0, 0.2); }
  .out {
    color: var(--gold);
    text-decoration: none;
    text-align: center;
    border: 1px solid var(--line);
  }
  .out:hover { color: var(--ink); }
  .work {
    flex: 1;
    min-width: 0;
    display: flex;
    flex-direction: column;
    background:
      radial-gradient(700px 280px at 100% -10%, rgba(212, 177, 90, 0.12), transparent 55%),
      #0a1912;
  }
  .top {
    display: flex;
    justify-content: space-between;
    gap: 16px;
    align-items: flex-start;
    padding: 22px 28px 14px;
  }
  h1 { margin: 0; font-size: 28px; font-weight: 700; }
  .sub { margin: 4px 0 0; color: var(--muted); font-size: 14px; }
  .topmeta { display: flex; align-items: center; gap: 10px; }
  .live {
    border-radius: 999px;
    padding: 5px 10px;
    font-size: 12px;
    color: #d7ecc0;
    background: #163528;
    border: 1px solid rgba(143, 191, 154, 0.35);
  }
  .live::before {
    content: "";
    display: inline-block;
    width: 7px;
    height: 7px;
    margin-right: 6px;
    border-radius: 50%;
    background: var(--army);
    box-shadow: 0 0 0 0 rgba(31, 122, 69, 0.7);
    animation: pulse 1.8s ease-out infinite;
  }
  @keyframes pulse {
    70% { box-shadow: 0 0 0 6px rgba(31, 122, 69, 0); }
    100% { box-shadow: 0 0 0 0 rgba(31, 122, 69, 0); }
  }
  .stats { display: flex; gap: 8px; }
  .stats div {
    min-width: 84px;
    padding: 8px 12px;
    border-radius: 14px;
    background: #123024;
    border: 1px solid var(--line);
    color: var(--muted);
    font-size: 12px;
  }
  .stats b { display: block; margin-top: 2px; color: var(--ink); font-size: 16px; font-weight: 700; }
  .view { flex: 1; min-height: 0; display: flex; flex-direction: column; }
  .view[hidden] { display: none !important; }
  button {
    font: inherit;
    color: var(--ink);
    background: #163528;
    border: 1px solid var(--line);
    border-radius: 999px;
    padding: 8px 14px;
    cursor: pointer;
  }
  button:hover { border-color: var(--gold); }
  button:disabled { opacity: 0.45; cursor: default; }
  button.danger { color: #ffd7cf; background: #4a2a24; border-color: #7a463c; }
  .sheet {
    width: min(560px, calc(100% - 48px));
    margin: 12px auto 28px;
    padding: 22px;
    border-radius: 20px;
    border: 1px solid var(--line);
    background: #10281d;
  }
  .sheet h2 { margin: 0 0 8px; font-size: 20px; }
  .sheet p { margin: 0 0 16px; color: var(--muted); line-height: 1.55; }
  .wifi { display: flex; flex-direction: column; gap: 12px; }
  .wifi label { display: flex; flex-direction: column; gap: 6px; color: var(--muted); font-size: 13px; }
  .wifi input {
    font: inherit;
    color: var(--ink);
    background: #0c1c14;
    border: 1px solid var(--line);
    border-radius: 12px;
    padding: 12px 14px;
    outline: none;
  }
  .wifi input:focus { border-color: var(--gold); }
  .wifi button {
    align-self: flex-start;
    color: #142216;
    background: linear-gradient(180deg, #e6c97a, #c6a15b);
    border: 0;
    font-weight: 700;
    margin-top: 4px;
  }
  #wifiMsg { color: var(--sta); font-size: 14px; min-height: 20px; }
  .url-chip { display: inline-block; margin: 2px 4px 2px 0; font-size: 11px; word-break: break-all; }
  .fwrow { display: flex; flex-wrap: wrap; gap: 8px; }
  #fwApMsg, #fwStaMsg { color: var(--sta); font-size: 14px; min-height: 20px; }
  .talkbar {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 8px;
    padding: 0 28px 10px;
  }
  .talkbar .hint { color: var(--muted); font-size: 12px; }
  main {
    flex: 1;
    overflow: auto;
    display: flex;
    flex-direction: column;
    padding: 8px 28px 28px;
  }
  .log {
    max-width: 680px;
    width: 100%;
    margin: auto auto 0;
    display: flex;
    flex-direction: column;
  }
  .day {
    align-self: center;
    color: var(--muted);
    font-size: 12px;
    background: #163528;
    border-radius: 999px;
    padding: 4px 12px;
    margin: 16px 0 6px;
  }
  .row { display: flex; align-items: flex-end; gap: 8px; margin-top: 12px; }
  .row.follow { margin-top: 4px; }
  .row.sta { flex-direction: row-reverse; }
  .ava {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    display: grid;
    place-items: center;
    font-size: 11px;
    font-weight: 700;
    flex: none;
  }
  .row.follow .ava { visibility: hidden; }
  .row.ap .ava { background: #2a4030; color: var(--ap); }
  .row.sta .ava { background: #1a4a34; color: var(--sta); }
  .bubble {
    width: min(360px, 78%);
    border: 1px solid var(--line);
    border-radius: 18px 18px 18px 6px;
    padding: 8px 10px 6px;
    background: #10281d;
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.18);
  }
  .row.sta .bubble { border-radius: 18px 18px 6px 18px; }
  .row.ap .bubble { background: var(--ap-bg); border-color: #6d5a2e; }
  .row.sta .bubble { background: var(--sta-bg); border-color: #2f6b45; }
  .name { font-size: 13px; font-weight: 700; margin-bottom: 4px; }
  .row.ap .name { color: var(--ap); }
  .row.sta .name { color: var(--sta); }
  .row.follow .name { display: none; }
  .note { display: flex; align-items: center; gap: 8px; }
  .play {
    width: 36px;
    height: 36px;
    padding: 0;
    border-radius: 50%;
    background: #0c1c14;
    flex: none;
  }
  .track { flex: 1; min-width: 0; }
  .bars { display: flex; align-items: center; gap: 2px; height: 28px; }
  .bars i {
    width: 3px;
    border-radius: 2px;
    background: currentColor;
    opacity: 0.45;
    display: block;
    transform-origin: center;
  }
  .row.ap .bars { color: var(--ap); }
  .row.sta .bars { color: var(--sta); }
  .note.on .bars i { opacity: 1; animation: eq 0.7s ease-in-out infinite alternate; }
  .note.on .bars i:nth-child(odd) { animation-duration: 0.5s; }
  @keyframes eq {
    from { transform: scaleY(0.4); }
    to { transform: scaleY(1); }
  }
  .len { font-size: 12px; color: var(--muted); min-width: 34px; text-align: right; }
  .meta {
    display: flex;
    justify-content: flex-end;
    align-items: center;
    gap: 8px;
    margin-top: 2px;
  }
  .when { font-size: 11px; color: var(--muted); }
  audio { display: none; }
  .fresh { animation: rise 0.45s ease; }
  @keyframes rise {
    from { opacity: 0; transform: translateY(8px); }
    to { opacity: 1; transform: none; }
  }
  .empty {
    max-width: 460px;
    margin: 10vh auto 0;
    text-align: center;
    color: var(--muted);
  }
  .empty h2 { color: var(--ink); font-size: 22px; margin: 0 0 8px; }
  .pair { display: flex; justify-content: center; gap: 14px; margin-bottom: 18px; }
  .chip {
    min-width: 92px;
    padding: 10px 14px;
    border-radius: 999px;
    border: 1px solid var(--line);
    font-weight: 700;
  }
  .chip.ap { color: var(--ap); background: var(--ap-bg); }
  .chip.sta { color: var(--sta); background: var(--sta-bg); }
  .pick { display: inline-flex; align-items: center; gap: 6px; color: var(--muted); font-size: 12px; }
  .drop { padding: 4px 10px; font-size: 12px; }
  footer {
    padding: 12px 24px 16px;
    color: var(--muted);
    font-size: 13px;
    line-height: 1.55;
    border-top: 1px solid var(--line);
  }
  code {
    color: var(--ink);
    background: #163528;
    padding: 1px 6px;
    border-radius: 6px;
  }
  .err { color: var(--danger); }
  @media (max-width: 760px) {
    .dash { flex-direction: column; }
    .nav {
      width: 100%;
      flex-direction: row;
      align-items: center;
      gap: 8px;
      padding: 10px 12px;
      border-right: 0;
      border-bottom: 1px solid var(--line);
    }
    .brand span, .navfoot .user { display: none; }
    .navfoot { margin: 0; padding: 0; border: 0; }
    .brand { padding: 0; }
    .nav nav { flex-direction: row; flex: 1; overflow: auto; }
    button.menu { width: auto; white-space: nowrap; padding: 8px 12px; }
    .top { flex-direction: column; padding: 16px; }
    h1 { font-size: 24px; }
    .sheet { width: auto; margin: 8px 12px 20px; }
    .talkbar { padding: 0 12px 10px; }
    main { padding: 8px 12px 20px; }
  }
</style>
</head>
<body>
<div class="dash">
<aside class="nav">
  <div class="brand">
    <div class="mark"></div>
    <div>
      <strong>হ্যালো</strong>
      <span>ড্যাশবোর্ড</span>
    </div>
  </div>
  <nav>
    <button type="button" class="menu on" data-panel="talk">কথোপকথন</button>
    <button type="button" class="menu" data-panel="wifi">Wi-Fi সেটিংস</button>
    <button type="button" class="menu" data-panel="fw">বোর্ড আপডেট</button>
  </nav>
  <div class="navfoot">
    <span class="user"><?php echo $who; ?></span>
    <a class="out" href="index.php?out=1">বের হন</a>
  </div>
</aside>
<section class="work">
  <header class="top">
    <div>
      <h1 id="pageTitle">কথোপকথন</h1>
      <p class="sub" id="pageSub">গ্রুপ চ্যাট। যে যখন ভয়েস দেয়, সেই সময়ে এখানে আসে।</p>
    </div>
    <div class="topmeta">
      <span class="live">লাইভ</span>
      <span id="status"></span>
      <div class="stats">
        <div>কথা<b id="count">০</b></div>
        <div>শেষ দেখা<b id="seen">—</b></div>
      </div>
    </div>
  </header>
  <div class="view" id="panelTalk">
    <div class="talkbar">
      <button type="button" id="clearPick" disabled>নির্বাচিত মুছুন</button>
      <button type="button" id="clearAll" class="danger">সব মুছুন</button>
      <span class="hint">পাঠানোর ঠিকানা:
        <?php foreach ($uploadUrls as $u): ?>
          <code class="url-chip"><?php echo htmlspecialchars($u, ENT_QUOTES, 'UTF-8'); ?></code>
        <?php endforeach; ?>
      </span>
    </div>
    <main id="main">
      <div class="empty" id="empty">
        <div class="pair"><span class="chip ap">AP</span><span class="chip sta">STA</span></div>
        <h2>এখনো কোনো কথা আসেনি</h2>
        <p>দুই বোর্ডে কথা বলার পর সেগুলো Wi-Fi পেলে এই পাতায় চলে আসবে। AP বাঁ দিকে, STA ডান দিকে।</p>
      </div>
      <div class="log" id="log" hidden></div>
    </main>
  </div>
  <div class="view" id="panelWifi" hidden>
    <form id="wifiForm" class="sheet wifi">
      <h2>Wi-Fi সেটিংস</h2>
      <p>নাম আর পাসওয়ার্ড সেভ করুন। বোর্ড নেটে থাকলে কথা থামার পর নিজে নিয়ে নেবে। পাসওয়ার্ড খালি রাখলে আগেরটাই থাকবে।</p>
      <label>Wi-Fi নাম
        <input id="wifiSsid" maxlength="32" required value="<?php echo htmlspecialchars(talk_wifi_read()['ssid'], ENT_QUOTES, 'UTF-8'); ?>">
      </label>
      <label>পাসওয়ার্ড
        <input id="wifiPass" type="password" maxlength="64" placeholder="খালি রাখলে আগেরটা থাকবে" autocomplete="new-password">
      </label>
      <label>ড্যাশবোর্ড ১ (কথা পাঠানোর ঠিকানা)
        <input id="uploadUrl" type="url" maxlength="220" required value="<?php echo htmlspecialchars($upload, ENT_QUOTES, 'UTF-8'); ?>">
      </label>
      <label>ড্যাশবোর্ড ২ (ঐচ্ছিক — লোকাল + সার্ভার দুটো)
        <input id="uploadUrl2" type="url" maxlength="220" placeholder="https://hallow.mmgbd.com/api/upload.php" value="<?php echo htmlspecialchars($upload2, ENT_QUOTES, 'UTF-8'); ?>">
      </label>
      <button type="submit">Wi-Fi সেভ</button>
      <span id="wifiMsg"></span>
    </form>
  </div>
  <div class="view" id="panelFw" hidden>
    <p class="talkbar hint">প্রথমবার USB দিয়ে দিতে হবে, পার্টিশন Minimal SPIFFS (1.9MB APP with OTA)। তারপর এখান থেকে। সংস্করণ আগের চেয়ে বড় রাখুন।</p>
    <form id="fwAp" class="sheet wifi">
      <h2>AP আপডেট</h2>
      <p id="fwApStat">এখনো কোনো ফাইল নেই।</p>
      <label>সংস্করণ
        <input id="fwApVer" type="number" min="1" max="9999" required>
      </label>
      <label>Arduino থেকে Export compiled Binary করা .bin
        <input id="fwApFile" type="file" accept=".bin,application/octet-stream" required>
      </label>
      <div class="fwrow">
        <button type="submit">ফাইল রাখুন</button>
        <button type="button" id="fwApPush">বোর্ডে পাঠান</button>
      </div>
      <span id="fwApMsg"></span>
    </form>
    <form id="fwSta" class="sheet wifi">
      <h2>STA আপডেট</h2>
      <p id="fwStaStat">এখনো কোনো ফাইল নেই।</p>
      <label>সংস্করণ
        <input id="fwStaVer" type="number" min="1" max="9999" required>
      </label>
      <label>Arduino থেকে Export compiled Binary করা .bin
        <input id="fwStaFile" type="file" accept=".bin,application/octet-stream" required>
      </label>
      <div class="fwrow">
        <button type="submit">ফাইল রাখুন</button>
        <button type="button" id="fwStaPush">বোর্ডে পাঠান</button>
      </div>
      <span id="fwStaMsg"></span>
    </form>
  </div>
</section>
</div>
<script>
const logEl = document.getElementById('log');
const emptyEl = document.getElementById('empty');
const countEl = document.getElementById('count');
const seenEl = document.getElementById('seen');
const statusEl = document.getElementById('status');
const mainEl = document.getElementById('main');
const bn = ['০','১','২','৩','৪','৫','৬','৭','৮','৯'];
let known = new Set();
let picked = new Set();
let stick = true;
let pinning = false;
let pinFrame = 0;

function pinBottom() {
  if (!stick) return;
  pinning = true;
  mainEl.scrollTop = mainEl.scrollHeight;
  if (pinFrame) return;
  pinFrame = requestAnimationFrame(() => {
    pinFrame = 0;
    if (stick) mainEl.scrollTop = mainEl.scrollHeight;
    requestAnimationFrame(() => { pinning = false; });
  });
}
const clearPickBtn = document.getElementById('clearPick');
const clearAllBtn = document.getElementById('clearAll');

function banglaNum(n) {
  return String(n).replace(/\d/g, d => bn[d]);
}

function clock(sec) {
  let s = Number(sec) || 0;
  if (s > 0 && s < 1) s = 1;
  else s = Math.round(s);
  const m = Math.floor(s / 60);
  return m + ':' + String(s % 60).padStart(2, '0');
}

function bars(id) {
  let n = 0;
  for (let i = 0; i < id.length; i++) n = (n * 33 + id.charCodeAt(i)) % 997;
  let html = '';
  for (let i = 0; i < 18; i++) {
    n = (n * 17 + 9) % 97;
    html += '<i style="height:' + (8 + (n % 16)) + 'px"></i>';
  }
  return html;
}

function gone(res) {
  if (res.status === 401) {
    location.reload();
    return true;
  }
  return false;
}

mainEl.addEventListener('scroll', () => {
  if (pinning) return;
  stick = mainEl.scrollHeight - mainEl.scrollTop - mainEl.clientHeight < 120;
});

new ResizeObserver(() => {
  if (stick) pinBottom();
}).observe(logEl);

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
  if (logEl.dataset.ids === ids) {
    pinBottom();
    return;
  }
  logEl.dataset.ids = ids;
  const next = new Set(items.map(it => it.id));
  let html = '';
  let day = '';
  let prev = '';
  for (const it of items) {
    if (it.day !== day) {
      day = it.day;
      prev = '';
      html += '<div class="day">' + day + '</div>';
    }
    const side = it.board === 'STA' ? 'sta' : 'ap';
    const follow = prev === it.board ? ' follow' : '';
    prev = it.board;
    const fresh = known.size && !known.has(it.id) ? ' fresh' : '';
    const id = encodeURIComponent(it.id);
    const on = picked.has(it.id) ? ' checked' : '';
    html += '<div class="row ' + side + follow + '">';
    html += '<div class="ava">' + it.board + '</div>';
    html += '<div class="bubble' + fresh + '">';
    html += '<div class="name">' + it.board + '</div>';
    html += '<div class="note">';
    html += '<button type="button" class="play" aria-label="চালান">▶</button>';
    html += '<div class="track"><div class="bars">' + bars(it.id) + '</div></div>';
    html += '<span class="len">' + clock(it.sec) + '</span>';
    html += '<audio preload="metadata" src="api/audio.php?id=' + id + '"></audio>';
    html += '</div>';
    html += '<div class="meta"><label class="pick"><input type="checkbox" data-id="' + id + '"' + on + '> বেছে নিন</label>';
    html += '<button type="button" class="drop" data-id="' + id + '">মুছুন</button>';
    html += '<span class="when">' + it.at + '</span></div>';
    html += '</div></div>';
  }
  logEl.innerHTML = html;
  bindNotes();
  known = next;
  for (const id of [...picked]) {
    if (!next.has(id)) picked.delete(id);
  }
  clearPickBtn.disabled = picked.size === 0;
  clearPickBtn.textContent = picked.size ? 'নির্বাচিত মুছুন (' + banglaNum(picked.size) + ')' : 'নির্বাচিত মুছুন';
  pinBottom();
  const last = items[items.length - 1];
  seenEl.textContent = last ? last.at : '—';
}

async function load() {
  try {
    const res = await fetch('api/list.php', { cache: 'no-store' });
    if (gone(res)) return;
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

logEl.addEventListener('change', (event) => {
  const box = event.target;
  if (!box.dataset || !box.dataset.id) return;
  if (box.checked) picked.add(box.dataset.id);
  else picked.delete(box.dataset.id);
  clearPickBtn.disabled = picked.size === 0;
  clearPickBtn.textContent = picked.size ? 'নির্বাচিত মুছুন (' + banglaNum(picked.size) + ')' : 'নির্বাচিত মুছুন';
});

function bindNotes() {
  logEl.querySelectorAll('audio').forEach((audio) => {
    const note = audio.closest('.note');
    const play = note.querySelector('.play');
    const len = note.querySelector('.len');
    audio.addEventListener('loadedmetadata', () => {
      if (Number.isFinite(audio.duration)) len.textContent = clock(audio.duration);
    });
    audio.addEventListener('play', () => {
      play.textContent = '❚❚';
      note.classList.add('on');
    });
    audio.addEventListener('pause', () => {
      play.textContent = '▶';
      note.classList.remove('on');
    });
    audio.addEventListener('ended', () => {
      play.textContent = '▶';
      note.classList.remove('on');
    });
  });
}

logEl.addEventListener('click', (event) => {
  const playBtn = event.target.closest('button.play');
  if (playBtn) {
    const audio = playBtn.closest('.note').querySelector('audio');
    if (audio.paused) {
      logEl.querySelectorAll('audio').forEach((other) => {
        if (other !== audio) other.pause();
      });
      audio.play();
    } else {
      audio.pause();
    }
    return;
  }
  const btn = event.target.closest('button.drop');
  if (!btn) return;
  const id = btn.dataset.id;
  if (!id) return;
  if (!confirm('এই কথা মুছে যাবে।')) return;
  clearIds([id]).catch(() => {
    statusEl.textContent = ' মুছা যায়নি।';
    statusEl.className = 'err';
  });
});

async function clearIds(ids) {
  const res = await fetch('api/clear.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ ids })
  });
  if (gone(res)) return;
  if (!res.ok) throw new Error('bad');
  ids.forEach(id => picked.delete(id));
  logEl.dataset.ids = '';
  await load();
}

clearPickBtn.addEventListener('click', async () => {
  const ids = [...picked];
  if (!ids.length) return;
  if (!confirm(ids.length + ' টি কথা মুছে যাবে।')) return;
  try {
    await clearIds(ids);
  } catch (e) {
    statusEl.textContent = ' মুছা যায়নি।';
    statusEl.className = 'err';
  }
});

clearAllBtn.addEventListener('click', async () => {
  if (!confirm('সব কথা মুছে যাবে।')) return;
  try {
    const res = await fetch('api/clear.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ all: true })
    });
    if (gone(res)) return;
    if (!res.ok) throw new Error('bad');
    picked.clear();
    logEl.dataset.ids = '';
    await load();
  } catch (e) {
    statusEl.textContent = ' মুছা যায়নি।';
    statusEl.className = 'err';
  }
});

const wifiForm = document.getElementById('wifiForm');
const wifiMsg = document.getElementById('wifiMsg');
wifiForm.addEventListener('submit', async (event) => {
  event.preventDefault();
  wifiMsg.textContent = '';
  wifiMsg.style.color = '';
  try {
    const res = await fetch('api/wifi.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        ssid: document.getElementById('wifiSsid').value,
        pass: document.getElementById('wifiPass').value,
        upload_url: document.getElementById('uploadUrl').value,
        upload_url2: document.getElementById('uploadUrl2').value
      })
    });
    if (gone(res)) return;
    const data = await res.json();
    if (!res.ok || !data.ok) throw new Error('bad');
    document.getElementById('wifiPass').value = '';
    wifiMsg.textContent = 'সেভ হয়েছে। বোর্ড নেটে থাকলে নিজে নিয়ে নেবে।';
  } catch (e) {
    wifiMsg.textContent = 'সেভ হয়নি।';
    wifiMsg.style.color = 'var(--danger)';
  }
});

const panels = {
  talk: ['কথোপকথন', 'গ্রুপ চ্যাট। যে যখন ভয়েস দেয়, সেই সময়ে এখানে আসে।'],
  wifi: ['Wi-Fi সেটিংস', 'Wi-Fi ও ড্যাশবোর্ড ঠিকানা। বোর্ড কথা দুটো সার্ভারে পাঠাতে পারে।'],
  fw: ['বোর্ড আপডেট', 'ফাইল রাখুন, তারপর বোর্ডে পাঠান। বোর্ড কথা থামলে নিজে নেবে।']
};
document.querySelectorAll('button.menu').forEach((btn) => {
  btn.addEventListener('click', () => {
    const name = btn.dataset.panel;
    document.querySelectorAll('button.menu').forEach((other) => {
      other.classList.toggle('on', other === btn);
    });
    document.getElementById('panelTalk').hidden = name !== 'talk';
    document.getElementById('panelWifi').hidden = name !== 'wifi';
    document.getElementById('panelFw').hidden = name !== 'fw';
    document.getElementById('pageTitle').textContent = panels[name][0];
    document.getElementById('pageSub').textContent = panels[name][1];
    btn.scrollIntoView({ inline: 'center', block: 'nearest' });
    if (name === 'talk') pinBottom();
    if (name === 'fw') loadFw();
  });
});

function fwStat(row) {
  if (!row || !row.has) return 'এখনো কোনো ফাইল নেই।';
  const pub = row.published ? 'বোর্ডে পাঠানো আছে' : 'রাখা আছে, এখনো পাঠানো হয়নি';
  return 'সংস্করণ ' + banglaNum(row.version) + ' · ' + pub;
}

async function loadFw() {
  try {
    const res = await fetch('api/fw.php', { cache: 'no-store' });
    if (gone(res)) return;
    const data = await res.json();
    if (!data.ok) return;
    document.getElementById('fwApStat').textContent = fwStat(data.AP);
    document.getElementById('fwStaStat').textContent = fwStat(data.STA);
    const apNext = Math.max(2, (data.AP && data.AP.version ? data.AP.version : 1) + 1);
    const staNext = Math.max(2, (data.STA && data.STA.version ? data.STA.version : 1) + 1);
    if (!document.getElementById('fwApVer').value) document.getElementById('fwApVer').value = apNext;
    if (!document.getElementById('fwStaVer').value) document.getElementById('fwStaVer').value = staNext;
  } catch (e) {}
}

function bindFw(formId, board, fileId, verId, msgId, pushId) {
  const form = document.getElementById(formId);
  const msg = document.getElementById(msgId);
  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    msg.textContent = '';
    msg.style.color = '';
    const fd = new FormData();
    fd.append('board', board);
    fd.append('version', document.getElementById(verId).value);
    fd.append('file', document.getElementById(fileId).files[0]);
    try {
      const res = await fetch('api/fw.php', { method: 'POST', body: fd });
      if (gone(res)) return;
      const data = await res.json();
      if (!res.ok || !data.ok) throw new Error(data.err || 'bad');
      msg.textContent = 'ফাইল রাখা হয়েছে। বোর্ডে পাঠান চাপলে বোর্ড নেবে।';
      document.getElementById(fileId).value = '';
      await loadFw();
    } catch (e) {
      msg.textContent = 'ফাইল রাখা যায়নি। .bin ফাইল এবং সংস্করণ দেখুন।';
      msg.style.color = 'var(--danger)';
    }
  });
  document.getElementById(pushId).addEventListener('click', async () => {
    msg.textContent = '';
    msg.style.color = '';
    try {
      const res = await fetch('api/fw.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ board: board, publish: 1 })
      });
      if (gone(res)) return;
      const data = await res.json();
      if (!res.ok || !data.ok) throw new Error('bad');
      msg.textContent = 'পাঠানো হয়েছে। বোর্ড কথা থামলে নিজে আপডেট নেবে।';
      await loadFw();
    } catch (e) {
      msg.textContent = 'আগে ফাইল রাখুন।';
      msg.style.color = 'var(--danger)';
    }
  });
}
bindFw('fwAp', 'AP', 'fwApFile', 'fwApVer', 'fwApMsg', 'fwApPush');
bindFw('fwSta', 'STA', 'fwStaFile', 'fwStaVer', 'fwStaMsg', 'fwStaPush');

load();
setInterval(load, 4000);
</script>
</body>
</html>
