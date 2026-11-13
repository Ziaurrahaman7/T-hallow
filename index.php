<?php
require_once __DIR__ . '/lib/store.php';
require_once __DIR__ . '/lib/dashboard_layout.php';
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
    talk_dash_login_render(
        $loginError,
        $bad,
        $bad ? trim((string) ($_POST['user'] ?? '')) : ''
    );
    exit;
}

$cfg = talk_config();
$uploadUrls = talk_upload_urls();
$upload = $uploadUrls[0] ?? $cfg['upload_url'];
$upload2 = $uploadUrls[1] ?? '';
$localSyncNote = talk_local_sync_note();
$lanBase = talk_lan_base();
talk_dash_head('কথোপকথন', ['talk-index.css']);
talk_dash_sidebar('talk', (string) $_SESSION['talk_user']);
talk_dash_main_open('talk-main');
?>
<header class="dash-header card" id="dashHeader">
    <div class="dash-header-main">
      <span class="dash-header-icon" id="headerIcon"><?php echo talk_dash_icon('chat'); ?></span>
      <div class="dash-header-text">
        <h1 class="page-title" id="pageTitle">কথোপকথন</h1>
        <p class="page-lead" id="pageSub">গ্রুপ চ্যাট — বোর্ড Wi‑Fi পেলে কথা এখানে দেখা যায়।</p>
      </div>
    </div>
    <div class="dash-header-meta is-talk-only">
      <span class="stat-pill live" id="headerLivePill">
        <span class="stat-ico"><?php echo talk_dash_icon('live'); ?></span>
        <span class="stat-label">লাইভ</span>
      </span>
      <span id="status" class="header-status"></span>
      <div class="stat-pill" title="মোট কথা">
        <span class="stat-ico"><?php echo talk_dash_icon('messages'); ?></span>
        <span class="stat-label">কথা <b id="count">০</b></span>
      </div>
      <div class="stat-pill" title="শেষ কথার সময়">
        <span class="stat-ico"><?php echo talk_dash_icon('clock'); ?></span>
        <span class="stat-label">শেষ <b id="seen">—</b></span>
      </div>
    </div>
  </header>
  <?php if ($localSyncNote !== ''): ?>
  <p class="note" role="status">লোকাল স্বয়ংক্রিয় আপডেট: বোর্ডের ঠিকানা <code><?php echo htmlspecialchars($localSyncNote, ENT_QUOTES, 'UTF-8'); ?></code> (localhost থেকে খুললেও Wi‑Fi বোর্ড PC-র LAN IP ব্যবহার করে)।</p>
  <?php elseif ($lanBase !== null): ?>
  <p class="note" role="status">বোর্ড ল্যান: <code><?php echo htmlspecialchars($lanBase, ENT_QUOTES, 'UTF-8'); ?></code> · ব্রাউজার: <code><?php echo htmlspecialchars((string) ($_SERVER['HTTP_HOST'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></code></p>
  <?php endif; ?>
  <div class="view" id="panelTalk">
    <div class="talk-toolbar">
      <button type="button" id="clearPick" class="btn-icon" disabled aria-label="নির্বাচিত সরান" title="নির্বাচিত সরান">
        <span class="ico"><?php echo talk_dash_icon('trash'); ?></span>
        <span class="btn-badge" id="clearPickBadge" hidden aria-hidden="true"></span>
      </button>
      <button type="button" id="clearAll" class="btn-icon danger" aria-label="সব সরান" title="সব সরান">
        <span class="ico"><?php echo talk_dash_icon('trash'); ?></span>
      </button>
      <span class="hint">পাঠানোর ঠিকানা:
        <?php foreach ($uploadUrls as $u): ?>
          <code class="url-chip"><?php echo htmlspecialchars($u, ENT_QUOTES, 'UTF-8'); ?></code>
        <?php endforeach; ?>
      </span>
    </div>
    <div class="talk-scroll wa-chat" id="main">
      <div class="empty" id="empty">
        <div class="pair"><span class="chip ap">AP</span><span class="chip sta">STA</span></div>
        <h2>এখনো কোনো কথা আসেনি</h2>
        <p>দুই বোর্ডে কথা বলার পর Wi‑Fi পেলে এখানে দেখা যাবে। AP বাঁ দিকে, STA ডান দিকে।</p>
      </div>
      <div class="log" id="log" hidden></div>
    </div>
  </div>
  <div class="view" id="panelRange" hidden>
    <div class="range-actions">
      <button type="button" class="btn-primary-solid" id="rangeGeoBtn">আমার অবস্থান ব্যবহার করুন</button>
      <button type="button" class="btn-secondary" id="rangeSiteBothBtn">এই অবস্থান = দুই বোর্ড</button>
    </div>
    <p id="rangeMsg"></p>
    <div class="range-grid">
      <div class="card range-card link">
        <h3>AP ↔ STA (HaLow)</h3>
        <div class="big" id="rangeLinkM">—</div>
        <div class="meta" id="rangeLinkMeta">HaLow RSSI — আনুমানিক। Wi‑Fi রিপোর্ট থাকলে আপডেট হয়।</div>
      </div>
      <div class="card range-card ap">
        <h3>আপনি → AP</h3>
        <div class="big" id="rangeApM">—</div>
        <div class="meta" id="rangeApMeta"></div>
      </div>
      <div class="card range-card sta">
        <h3>আপনি → STA</h3>
        <div class="big" id="rangeStaM">—</div>
        <div class="meta" id="rangeStaMeta"></div>
      </div>
    </div>
    <form id="rangeSiteForm" class="card sheet">
      <h2>বোর্ডের জিও অবস্থান</h2>
      <p class="lead">GPS নেই — Maps থেকে lat/lon দিন। আপনার অবস্থানের সাথে মিলিয়ে দূরত্ব দেখায়।</p>
      <div class="range-form">
        <label>AP · lat
          <input id="siteApLat" type="text" inputmode="decimal" placeholder="23.8103">
        </label>
        <label>AP · lon
          <input id="siteApLon" type="text" inputmode="decimal" placeholder="90.4125">
        </label>
        <label>STA · lat
          <input id="siteStaLat" type="text" inputmode="decimal" placeholder="23.8103">
        </label>
        <label>STA · lon
          <input id="siteStaLon" type="text" inputmode="decimal" placeholder="90.4125">
        </label>
      </div>
      <button type="submit" class="btn-primary-solid">অবস্থান সেভ</button>
    </form>
  </div>
  <div class="view" id="panelWifi" hidden>
    <form id="wifiForm" class="card sheet">
      <h2>সার্ভার ঠিকানা</h2>
      <p class="lead">কথা আপলোডের URL। <code>localhost</code> / <code>T-hallow.test</code> থেকে খুললে PC-র LAN IP দিয়ে ঠিকানা ও <code>data/local.json</code> নিজে আপডেট হয়। বোর্ড Wi‑Fi → <a href="net.php">বোর্ড Wi‑Fi</a>।</p>
      <label>ড্যাশবোর্ড ১ (কথা পাঠানোর ঠিকানা)
        <input id="uploadUrl" type="url" maxlength="220" required value="<?php echo htmlspecialchars($upload, ENT_QUOTES, 'UTF-8'); ?>">
      </label>
      <label>ড্যাশবোর্ড ২ (ঐচ্ছিক)
        <input id="uploadUrl2" type="url" maxlength="220" placeholder="https://hallow.mmgbd.com/api/upload.php" value="<?php echo htmlspecialchars($upload2, ENT_QUOTES, 'UTF-8'); ?>">
      </label>
      <button type="submit" class="btn-primary-solid">ঠিকানা সেভ</button>
      <span id="wifiMsg"></span>
    </form>
  </div>
  <div class="view" id="panelFw" hidden>
    <p class="note">প্রথম OTA: USB + Minimal SPIFFS (1.9MB APP with OTA)। পরে সংস্করণ নম্বর বাড়িয়ে আপলোড করুন।</p>
    <form id="fwAp" class="card sheet">
      <h2>AP আপডেট</h2>
      <p id="fwApStat">এখনো কোনো ফাইল নেই।</p>
      <label>সংস্করণ
        <input id="fwApVer" type="number" min="1" max="9999" required>
      </label>
      <label>.bin ফাইল
        <input id="fwApFile" type="file" accept=".bin,application/octet-stream" required>
      </label>
      <div class="fwrow">
        <button type="submit" class="btn-primary-solid">ফাইল রাখুন</button>
        <button type="button" class="btn-secondary" id="fwApPush">বোর্ডে পাঠান</button>
      </div>
      <span id="fwApMsg"></span>
    </form>
    <form id="fwSta" class="card sheet">
      <h2>STA আপডেট</h2>
      <p id="fwStaStat">এখনো কোনো ফাইল নেই।</p>
      <label>সংস্করণ
        <input id="fwStaVer" type="number" min="1" max="9999" required>
      </label>
      <label>.bin ফাইল
        <input id="fwStaFile" type="file" accept=".bin,application/octet-stream" required>
      </label>
      <div class="fwrow">
        <button type="submit" class="btn-primary-solid">ফাইল রাখুন</button>
        <button type="button" class="btn-secondary" id="fwStaPush">বোর্ডে পাঠান</button>
      </div>
      <span id="fwStaMsg"></span>
    </form>
  </div>
<script>
const ICON_TRASH = <?php echo json_encode(talk_dash_icon('trash'), JSON_UNESCAPED_UNICODE); ?>;
const ICON_PLAY = <?php echo json_encode(talk_dash_icon('play'), JSON_UNESCAPED_UNICODE); ?>;
const ICON_PAUSE = <?php echo json_encode(talk_dash_icon('pause'), JSON_UNESCAPED_UNICODE); ?>;
const HEADER_ICONS = <?php echo json_encode([
  'talk' => talk_dash_icon('chat'),
  'range' => talk_dash_icon('range'),
  'wifi' => talk_dash_icon('server'),
  'fw' => talk_dash_icon('chip'),
], JSON_UNESCAPED_UNICODE); ?>;
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
    const isFollow = prev === it.board;
    const follow = isFollow ? ' follow' : '';
    prev = it.board;
    const fresh = known.size && !known.has(it.id) ? ' fresh' : '';
    const id = encodeURIComponent(it.id);
    const on = picked.has(it.id) ? ' checked' : '';
    const pickedCls = picked.has(it.id) ? ' is-picked' : '';
    html += '<div class="row ' + side + follow + '">';
    html += '<div class="bubble wa' + fresh + pickedCls + '">';
    html += '<div class="wa-tools">';
    html += '<label class="wa-check" title="নির্বাচন"><input type="checkbox" data-id="' + id + '"' + on + '></label>';
    html += '<button type="button" class="drop wa-del" data-id="' + id + '" aria-label="সরান" title="সরান">' + ICON_TRASH + '</button>';
    html += '</div>';
    if (!isFollow) {
      html += '<div class="wa-sender">' + it.board + '</div>';
    }
    html += '<div class="note wa-voice">';
    html += '<button type="button" class="play wa-play" aria-label="চালান">' + ICON_PLAY + '</button>';
    html += '<div class="wa-body">';
    html += '<div class="track wa-track"><div class="bars wa-bars">' + bars(it.id) + '</div></div>';
    html += '<div class="wa-meta"><span class="len wa-dur">' + clock(it.sec) + '</span><span class="when">' + it.at + '</span></div>';
    html += '</div>';
    html += '<audio preload="metadata" src="api/audio.php?id=' + id + '"></audio>';
    html += '</div></div></div>';
  }
  logEl.innerHTML = html;
  bindNotes();
  known = next;
  for (const id of [...picked]) {
    if (!next.has(id)) picked.delete(id);
  }
  syncClearPick();
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
  const bubble = box.closest('.bubble.wa');
  if (bubble) bubble.classList.toggle('is-picked', box.checked);
  syncClearPick();
});

function syncClearPick() {
  clearPickBtn.disabled = picked.size === 0;
  const badge = document.getElementById('clearPickBadge');
  if (!badge) return;
  if (picked.size > 0) {
    badge.textContent = banglaNum(picked.size);
    badge.hidden = false;
    clearPickBtn.setAttribute('aria-label', 'নির্বাচিত ' + picked.size + ' টি সরান');
  } else {
    badge.textContent = '';
    badge.hidden = true;
    clearPickBtn.setAttribute('aria-label', 'নির্বাচিত সরান');
  }
}

function bindNotes() {
  logEl.querySelectorAll('audio').forEach((audio) => {
    const note = audio.closest('.note');
    const play = note.querySelector('.play');
    const len = note.querySelector('.len');
    audio.addEventListener('loadedmetadata', () => {
      if (Number.isFinite(audio.duration)) len.textContent = clock(audio.duration);
    });
    audio.addEventListener('play', () => {
      play.innerHTML = ICON_PAUSE;
      note.classList.add('on');
    });
    audio.addEventListener('pause', () => {
      play.innerHTML = ICON_PLAY;
      note.classList.remove('on');
    });
    audio.addEventListener('ended', () => {
      play.innerHTML = ICON_PLAY;
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
        upload_url: document.getElementById('uploadUrl').value,
        upload_url2: document.getElementById('uploadUrl2').value
      })
    });
    if (gone(res)) return;
    const data = await res.json();
    if (!res.ok || !data.ok) throw new Error('bad');
    wifiMsg.textContent = 'সেভ হয়েছে।';
  } catch (e) {
    wifiMsg.textContent = 'সেভ হয়নি।';
    wifiMsg.style.color = 'var(--danger)';
  }
});

const panels = {
  talk: ['কথোপকথন', 'লাইভ গ্রুপ চ্যাট — বোর্ড Wi‑Fi সংযুক্ত থাকলে কথা এখানে দেখা যায়।'],
  range: ['দূরত্ব', 'HaLow লিংক ও আপনার অবস্থান থেকে AP/STA পর্যন্ত আনুমানিক দূরত্ব।'],
  wifi: ['সার্ভার', 'কথা কোন URL-এ আপলোড হবে — Wi‑Fi বোর্ড সেটিং net.php-তে।'],
  fw: ['ফার্মওয়্যার', 'OTA .bin আপলোড ও বোর্ডে পুশ — সংস্করণ নম্বর বাড়িয়ে রাখুন।']
};

function openPanel(name, opts) {
  const pushHash = !(opts && opts.pushHash === false);
  if (!panels[name]) name = 'talk';
  document.querySelectorAll('.side-link').forEach((link) => {
    if (link.href && link.href.includes('out=1')) return;
    const panel = link.dataset.panel;
    const isTalk = !panel && link.getAttribute('href') && link.getAttribute('href').includes('index.php');
    const on = panel ? panel === name : (name === 'talk' && isTalk);
    link.classList.toggle('is-active', !!on);
  });
  document.getElementById('panelTalk').hidden = name !== 'talk';
  document.getElementById('panelRange').hidden = name !== 'range';
  document.getElementById('panelWifi').hidden = name !== 'wifi';
  document.getElementById('panelFw').hidden = name !== 'fw';
  document.getElementById('pageTitle').textContent = panels[name][0];
  document.getElementById('pageSub').textContent = panels[name][1];
  const iconEl = document.getElementById('headerIcon');
  if (iconEl && HEADER_ICONS[name]) iconEl.innerHTML = HEADER_ICONS[name];
  const livePill = document.getElementById('headerLivePill');
  const headerMeta = document.querySelector('.dash-header-meta');
  if (headerMeta) headerMeta.classList.toggle('is-talk-only', name === 'talk');
  if (pushHash && location.hash.replace('#', '') !== name && name !== 'talk') {
    history.replaceState(null, '', '#' + name);
  } else if (pushHash && name === 'talk' && location.hash) {
    history.replaceState(null, '', location.pathname + location.search);
  }
  if (name === 'talk') pinBottom();
  if (name === 'range') {
    loadRange(true);
  } else if (rangeTimer) {
    clearInterval(rangeTimer);
    rangeTimer = null;
  }
  if (name === 'fw') loadFw();
}

document.querySelectorAll('.side-link[data-panel]').forEach((link) => {
  link.addEventListener('click', (e) => {
    e.preventDefault();
    openPanel(link.dataset.panel);
  });
});

(function initPanelFromHash() {
  const h = (location.hash || '').replace('#', '');
  if (panels[h]) openPanel(h, { pushHash: false });
})();
window.addEventListener('hashchange', () => {
  const h = (location.hash || '').replace('#', '');
  openPanel(panels[h] ? h : 'talk', { pushHash: false });
});

let rangeTimer = null;
let rangeSiteDirty = false;

function rangeSiteFormActive() {
  const form = document.getElementById('rangeSiteForm');
  if (!form) return false;
  if (rangeSiteDirty) return true;
  const ae = document.activeElement;
  return !!(ae && form.contains(ae) && ae.tagName === 'INPUT');
}

function bindRangeSiteInputs() {
  ['siteApLat', 'siteApLon', 'siteStaLat', 'siteStaLon'].forEach((id) => {
    const el = document.getElementById(id);
    if (!el || el.dataset.bound) return;
    el.dataset.bound = '1';
    el.addEventListener('input', () => { rangeSiteDirty = true; });
  });
}
bindRangeSiteInputs();

function fmtMeters(m) {
  if (m == null || !Number.isFinite(m)) return '—';
  if (m >= 1000) return banglaNum((m / 1000).toFixed(1)) + ' কিমি';
  return banglaNum(Math.round(m)) + ' মি';
}

function renderRange(data, opts) {
  const syncSites = opts && opts.syncSites === true;
  const link = data.halow_link;
  document.getElementById('rangeLinkM').textContent = link && link.meters != null
    ? fmtMeters(link.meters)
    : '—';
  const apHr = data.boards && data.boards.AP ? data.boards.AP.halow_rssi : 0;
  const staHr = data.boards && data.boards.STA ? data.boards.STA.halow_rssi : 0;
  let meta = '';
  if (link && link.meters != null) {
    meta = 'AP দেখছে ' + apHr + ' dBm · STA দেখছে ' + staHr + ' dBm (আনুমানিক)';
  } else {
    meta = 'HaLow RSSI এখনো আসেনি — দুই বোর্ড চালু + Wi-Fi রিপোর্ট লাগবে।';
  }
  document.getElementById('rangeLinkMeta').textContent = meta;

  const du = data.user_distance || {};
  ['AP', 'STA'].forEach((id) => {
    const row = du[id] || {};
    const big = document.getElementById(id === 'AP' ? 'rangeApM' : 'rangeStaM');
    const sub = document.getElementById(id === 'AP' ? 'rangeApMeta' : 'rangeStaMeta');
    big.textContent = fmtMeters(row.meters);
    if (!data.user) {
      sub.textContent = 'আগে «আমার অবস্থান ব্যবহার করুন» চাপুন।';
    } else if (!row.site_set) {
      sub.textContent = 'নিচে AP/STA-র lat-lon সেট করুন।';
    } else {
      const b = data.boards[id] || {};
      const netLine = b.wifi_ok ? ('নেট ✓ ' + (b.live_ssid || '')) : (b.online ? 'চালু, নেট নেই' : 'বন্ধ');
      sub.textContent = (row.site_label || '') + ' · ' + netLine;
    }
  });

  if (syncSites && !rangeSiteFormActive()) {
    const sites = data.sites || {};
    if (sites.site_ap) {
      document.getElementById('siteApLat').value = sites.site_ap.lat != null ? sites.site_ap.lat : '';
      document.getElementById('siteApLon').value = sites.site_ap.lon != null ? sites.site_ap.lon : '';
    }
    if (sites.site_sta) {
      document.getElementById('siteStaLat').value = sites.site_sta.lat != null ? sites.site_sta.lat : '';
      document.getElementById('siteStaLon').value = sites.site_sta.lon != null ? sites.site_sta.lon : '';
    }
    rangeSiteDirty = false;
  }
}

async function loadRange(startPoll) {
  try {
    const res = await fetch('api/range.php', { cache: 'no-store' });
    if (gone(res)) return;
    const data = await res.json();
    if (!data.ok) return;
    renderRange(data, { syncSites: !!startPoll });
    if (startPoll) {
      if (rangeTimer) clearInterval(rangeTimer);
      rangeTimer = setInterval(() => loadRange(false), 12000);
    }
  } catch (e) {}
}

document.getElementById('rangeGeoBtn').addEventListener('click', () => {
  const msg = document.getElementById('rangeMsg');
  msg.textContent = '';
  msg.className = '';
  if (!navigator.geolocation) {
    msg.textContent = 'এই ব্রাউজারে অবস্থান সাপোর্ট নেই।';
    msg.className = 'err';
    return;
  }
  msg.textContent = 'অবস্থান খোঁজা হচ্ছে…';
  navigator.geolocation.getCurrentPosition(async (pos) => {
    try {
      const res = await fetch('api/range.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          action: 'user_geo',
          lat: pos.coords.latitude,
          lon: pos.coords.longitude,
          acc: pos.coords.accuracy
        })
      });
      if (gone(res)) return;
      const data = await res.json();
      if (!res.ok || !data.ok) throw new Error('bad');
      msg.textContent = 'অবস্থান সেভ হয়েছে (±' + Math.round(pos.coords.accuracy) + ' মি)।';
      renderRange(data, { syncSites: false });
    } catch (e) {
      msg.textContent = 'সেভ হয়নি।';
      msg.className = 'err';
    }
  }, () => {
    msg.textContent = 'অবস্থানের অনুমতি দিন (ব্রাউজার/Windows)।';
    msg.className = 'err';
  }, { enableHighAccuracy: true, timeout: 15000 });
});

document.getElementById('rangeSiteBothBtn').addEventListener('click', async () => {
  const msg = document.getElementById('rangeMsg');
  msg.textContent = '';
  msg.className = '';
  try {
    const res = await fetch('api/range.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'use_user_for_both', label: 'মাঠ' })
    });
    if (gone(res)) return;
    const data = await res.json();
    if (!res.ok || !data.ok) {
      msg.textContent = 'আগে আপনার অবস্থান সেট করুন।';
      msg.className = 'err';
      return;
    }
    msg.textContent = 'দুই বোর্ড একই বিন্দুতে সেট।';
    renderRange(data, { syncSites: true });
  } catch (e) {
    msg.textContent = 'সেভ হয়নি।';
    msg.className = 'err';
  }
});

document.getElementById('rangeSiteForm').addEventListener('submit', async (event) => {
  event.preventDefault();
  const msg = document.getElementById('rangeMsg');
  msg.textContent = '';
  msg.className = '';
  const parse = (v) => {
    const x = parseFloat(String(v).replace(',', '.'));
    return Number.isFinite(x) ? x : null;
  };
  try {
    const res = await fetch('api/range.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        action: 'save_sites',
        site_ap: { lat: parse(document.getElementById('siteApLat').value), lon: parse(document.getElementById('siteApLon').value) },
        site_sta: { lat: parse(document.getElementById('siteStaLat').value), lon: parse(document.getElementById('siteStaLon').value) }
      })
    });
    if (gone(res)) return;
    const data = await res.json();
    if (!res.ok || !data.ok) throw new Error('bad');
    msg.textContent = 'বোর্ড অবস্থান সেভ হয়েছে।';
    rangeSiteDirty = false;
    renderRange(data, { syncSites: true });
  } catch (e) {
    msg.textContent = 'সেভ হয়নি — lat/lon ঠিক আছে কি দেখুন।';
    msg.className = 'err';
  }
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
<?php talk_dash_main_close(); talk_dash_end(); ?>
