<?php

require_once __DIR__ . '/store.php';

function talk_dash_base(): string
{
    $sn = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $dir = dirname($sn);
    if ($dir === '/' || $dir === '.' || $dir === '') {
        return '';
    }

    return rtrim($dir, '/');
}

function talk_dash_asset(string $file): string
{
    return talk_dash_base() . '/assets/' . ltrim($file, '/');
}

/** @return array<string, array{href: string, label: string, icon: string}> */
function talk_dash_nav_items(): array
{
    $b = talk_dash_base();

    return [
        'talk' => ['href' => $b . '/index.php', 'label' => 'কথোপকথন', 'icon' => 'chat'],
        'status' => ['href' => $b . '/status.php', 'label' => 'বোর্ড স্ট্যাটাস', 'icon' => 'board'],
        'range' => ['href' => $b . '/index.php#range', 'label' => 'দূরত্ব', 'icon' => 'range'],
        'net' => ['href' => $b . '/net.php', 'label' => 'বোর্ড Wi‑Fi', 'icon' => 'wifi'],
        'battery' => ['href' => $b . '/battery.php', 'label' => 'ব্যাটারি', 'icon' => 'battery'],
        'wifi' => ['href' => $b . '/index.php#wifi', 'label' => 'সার্ভার', 'icon' => 'server'],
        'fw' => ['href' => $b . '/index.php#fw', 'label' => 'ফার্মওয়্যার', 'icon' => 'chip'],
    ];
}

function talk_dash_icon(string $name): string
{
    $icons = [
        'logo' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><path d="M12 3l8 4.5v9L12 21l-8-4.5v-9L12 3z"/><path d="M12 12l8-4.5M12 12v9M12 12L4 7.5"/></svg>',
        'chat' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><path d="M7 9h10M7 13h6"/><path d="M5 4h14a2 2 0 012 2v9a2 2 0 01-2 2H9l-4 3v-3H5a2 2 0 01-2-2V6a2 2 0 012-2z"/></svg>',
        'range' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><circle cx="12" cy="10" r="3"/><path d="M12 21s7-4.5 7-10a7 7 0 10-14 0c0 5.5 7 10 7 10z"/></svg>',
        'wifi' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><path d="M5 12.5a11 11 0 0114 0"/><path d="M8.5 15.5a6.5 6.5 0 017 0"/><path d="M12 19h.01"/><circle cx="12" cy="19" r="1" fill="currentColor" stroke="none"/></svg>',
        'server' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><rect x="3" y="4" width="18" height="6" rx="2"/><rect x="3" y="14" width="18" height="6" rx="2"/><circle cx="7" cy="7" r="1" fill="currentColor"/><circle cx="7" cy="17" r="1" fill="currentColor"/></svg>',
        'chip' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><rect x="7" y="7" width="10" height="10" rx="2"/><path d="M9 3v4M12 3v4M15 3v4M9 17v4M12 17v4M15 17v4M3 9h4M3 12h4M3 15h4M17 9h4M17 12h4M17 15h4"/></svg>',
        'logout' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><path d="M10 17l5-5-5-5"/><path d="M15 12H4"/><path d="M19 4v16"/></svg>',
        'board' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><rect x="4" y="4" width="16" height="16" rx="3"/><path d="M8 9h8M8 13h5"/></svg>',
        'battery' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><rect x="2" y="7" width="18" height="10" rx="2"/><path d="M22 11v2"/><path d="M6 10v4M9 10v4M12 10v3"/></svg>',
        'trash' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><path d="M4 7h16"/><path d="M9 7V5a1 1 0 011-1h4a1 1 0 011 1v2"/><path d="M6 7l1 14a1 1 0 001 1h8a1 1 0 001-1l1-14"/><path d="M10 11v5M14 11v5"/></svg>',
        'play' => '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M8 5.14v13.72L19 12 8 5.14z"/></svg>',
        'pause' => '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M7 5h4v14H7V5zm6 0h4v14h-4V5z"/></svg>',
        'mic' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><path d="M12 14a3 3 0 003-3V7a3 3 0 10-6 0v4a3 3 0 003 3z"/><path d="M19 11a7 7 0 01-14 0M12 18v3"/></svg>',
        'messages' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><path d="M21 11.5a8.38 8.38 0 01-.9 3.8 8.5 8.5 0 01-7.6 4.7 8.38 8.38 0 01-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 01-.9-3.8 8.5 8.5 0 014.7-7.6 8.38 8.38 0 013.8-.9h.5a8.48 8.48 0 018 8v.5z"/></svg>',
        'clock' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>',
        'live' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><path d="M4.5 16.5c3-4.5 7.5-4.5 10.5 0"/><path d="M7 14a4 4 0 015.5 0"/><circle cx="12" cy="17" r="1" fill="currentColor"/></svg>',
    ];

    return $icons[$name] ?? $icons['board'];
}

function talk_dash_use_tailwind(): bool
{
    return ! empty($GLOBALS['talk_use_tailwind']);
}

function talk_dash_tailwind_script(): void
{
    $local = talk_dash_asset('vendor/tailwind-playcdn.js');
    echo '<script src="' . htmlspecialchars($local, ENT_QUOTES, 'UTF-8') . '" onerror="this.onerror=null;this.src=\'https://cdn.tailwindcss.com\'"></script>';
    echo '<script>
if (typeof tailwind === "undefined") { /* offline: use local CSS only */ } else {
tailwind.config = {
  corePlugins: { preflight: false },
  theme: {
    extend: {
      fontFamily: {
        sans: ["Inter", "Noto Sans Bengali", "Segoe UI", "system-ui", "sans-serif"]
      },
      boxShadow: {
        glow: "0 0 0 1px rgba(148, 163, 184, 0.12), 0 4px 24px rgba(15, 23, 42, 0.06)",
        "glow-lg": "0 0 0 1px rgba(148, 163, 184, 0.14), 0 12px 40px rgba(15, 23, 42, 0.08)"
      },
      animation: {
        "fade-in": "fadeIn 0.4s ease-out",
        "live-pulse": "livePulse 2s ease-in-out infinite"
      },
      keyframes: {
        fadeIn: {
          from: { opacity: "0", transform: "translateY(8px)" },
          to: { opacity: "1", transform: "translateY(0)" }
        },
        livePulse: {
          "0%, 100%": { opacity: "1", transform: "scale(1)" },
          "50%": { opacity: "0.45", transform: "scale(0.92)" }
        }
      }
    }
  }
};
}
</script>';
}

function talk_dash_head(string $title, array $extraCss = []): void
{
    $useTw = in_array('tailwind', $extraCss, true);
    if ($useTw) {
        $GLOBALS['talk_use_tailwind'] = true;
    }
    $t = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
    echo '<!DOCTYPE html><html lang="bn" class="h-full"><head>';
    echo '<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>' . $t . ' · হ্যালো</title>';
    echo '<link rel="preconnect" href="https://fonts.googleapis.com">';
    echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>';
    echo '<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Noto+Sans+Bengali:wght@400;500;600;700&display=swap" rel="stylesheet">';
    if (! $useTw) {
        echo '<link rel="stylesheet" href="' . htmlspecialchars(talk_dash_asset('dashboard.css'), ENT_QUOTES, 'UTF-8') . '">';
    }
    foreach ($extraCss as $css) {
        if ($css === 'tailwind') {
            continue;
        }
        echo '<link rel="stylesheet" href="' . htmlspecialchars(talk_dash_asset($css), ENT_QUOTES, 'UTF-8') . '">';
    }
    if ($useTw) {
        talk_dash_tailwind_script();
    }
    if ($useTw) {
        echo '</head><body class="min-h-full font-sans text-slate-900 antialiased bg-slate-100/80">';
        echo '<div class="flex min-h-screen">';
    } else {
        echo '</head><body><div class="app-shell">';
    }
}

function talk_dash_sidebar_tw(string $active, string $who): void
{
    $items = talk_dash_nav_items();
    echo '<aside class="hidden lg:flex w-[260px] shrink-0 flex-col border-r border-slate-200/80 bg-white/75 backdrop-blur-xl px-3 py-5 sticky top-0 h-screen" aria-label="মেনু">';
    echo '<a class="flex items-center gap-3 rounded-2xl px-2 py-3 mb-2 no-underline text-inherit hover:bg-slate-50/80 transition-colors duration-200" href="';
    echo htmlspecialchars(talk_dash_base() . '/index.php', ENT_QUOTES, 'UTF-8') . '">';
    echo '<span class="grid h-11 w-11 place-items-center rounded-xl bg-gradient-to-br from-blue-500 to-blue-600 text-white shadow-md shadow-blue-500/25 [&_svg]:h-[22px] [&_svg]:w-[22px]">';
    echo talk_dash_icon('logo') . '</span>';
    echo '<span><strong class="block text-[17px] font-bold tracking-tight text-slate-900">হ্যালো</strong>';
    echo '<span class="block text-xs font-medium text-slate-500">Helmet Admin</span></span></a>';
    echo '<nav class="flex flex-1 flex-col gap-1 mt-2">';
    foreach ($items as $key => $item) {
        $href = $item['href'];
        if ($key === 'range' || $key === 'wifi' || $key === 'fw') {
            $href = talk_dash_base() . '/index.php';
        }
        $on = $key === $active;
        $cls = $on
            ? 'is-active bg-blue-50 text-blue-700 shadow-sm ring-1 ring-blue-100/80'
            : 'text-slate-600 hover:bg-slate-100/90 hover:text-slate-900';
        echo '<a class="side-link flex items-center gap-2.5 rounded-xl px-3 py-2.5 text-sm font-medium transition-all duration-200 no-underline ';
        echo $cls . '" href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '"';
        if ($key === 'range' || $key === 'wifi' || $key === 'fw') {
            echo ' data-panel="' . htmlspecialchars($key, ENT_QUOTES, 'UTF-8') . '"';
        }
        echo '><span class="opacity-80 [&_svg]:h-[18px] [&_svg]:w-[18px]">' . talk_dash_icon($item['icon']) . '</span>';
        echo htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') . '</a>';
    }
    echo '</nav>';
    echo '<div class="mt-auto border-t border-slate-200/80 pt-4 space-y-2">';
    echo '<div class="rounded-xl border border-slate-200/80 bg-slate-50/80 px-3 py-2 text-sm font-semibold text-slate-800 truncate">';
    echo htmlspecialchars($who, ENT_QUOTES, 'UTF-8') . '</div>';
    echo '<a class="side-link flex items-center gap-2 rounded-xl px-3 py-2 text-sm font-medium text-slate-500 hover:bg-slate-100 hover:text-slate-800 transition-colors no-underline" href="';
    echo htmlspecialchars(talk_dash_base() . '/index.php?out=1', ENT_QUOTES, 'UTF-8') . '">';
    echo '<span class="[&_svg]:h-[18px] [&_svg]:w-[18px]">' . talk_dash_icon('logout') . '</span>বের হন</a>';
    echo '</div></aside>';
    echo '<nav class="lg:hidden fixed bottom-0 inset-x-0 z-40 flex gap-1 overflow-x-auto border-t border-slate-200/90 bg-white/90 backdrop-blur-xl px-2 py-2 safe-area-pb" aria-label="মোবাইল মেনু">';
    foreach ($items as $key => $item) {
        $href = $item['href'];
        if ($key === 'range' || $key === 'wifi' || $key === 'fw') {
            $href = talk_dash_base() . '/index.php';
        }
        $on = $key === $active;
        $cls = $on ? 'is-active text-blue-600 bg-blue-50' : 'text-slate-500';
        echo '<a class="side-link shrink-0 flex flex-col items-center gap-0.5 rounded-xl px-3 py-1.5 text-[10px] font-semibold no-underline transition-colors ' . $cls . '" href="';
        echo htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '"';
        if ($key === 'range' || $key === 'wifi' || $key === 'fw') {
            echo ' data-panel="' . htmlspecialchars($key, ENT_QUOTES, 'UTF-8') . '"';
        }
        echo '><span class="[&_svg]:h-5 [&_svg]:w-5">' . talk_dash_icon($item['icon']) . '</span>';
        echo htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') . '</a>';
    }
    echo '</nav>';
}

function talk_dash_sidebar(string $active, string $who): void
{
    if (talk_dash_use_tailwind()) {
        talk_dash_sidebar_tw($active, $who);

        return;
    }
    $items = talk_dash_nav_items();
    echo '<aside class="sidebar" aria-label="মেনু">';
    echo '<a class="brand" href="' . htmlspecialchars(talk_dash_base() . '/index.php', ENT_QUOTES, 'UTF-8') . '">';
    echo '<span class="brand-icon">' . talk_dash_icon('logo') . '</span>';
    echo '<span class="brand-text"><strong>হ্যালো</strong><span>Helmet Admin</span></span></a>';
    echo '<nav class="side-nav">';
    foreach ($items as $key => $item) {
        $on = $key === $active ? ' is-active' : '';
        $href = $item['href'];
        if ($key === 'range' || $key === 'wifi' || $key === 'fw') {
            $href = talk_dash_base() . '/index.php';
        }
        echo '<a class="side-link' . $on . '" href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '"';
        if ($key === 'range' || $key === 'wifi' || $key === 'fw') {
            echo ' data-panel="' . htmlspecialchars($key, ENT_QUOTES, 'UTF-8') . '"';
        }
        echo '><span class="ico">' . talk_dash_icon($item['icon']) . '</span>' . htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') . '</a>';
    }
    echo '</nav>';
    echo '<div class="side-foot">';
    echo '<div class="user-pill">' . htmlspecialchars($who, ENT_QUOTES, 'UTF-8') . '</div>';
    echo '<a class="side-link muted" href="' . htmlspecialchars(talk_dash_base() . '/index.php?out=1', ENT_QUOTES, 'UTF-8') . '">';
    echo '<span class="ico">' . talk_dash_icon('logout') . '</span>বের হন</a>';
    echo '</div></aside>';
}

function talk_dash_main_open(string $extraClass = ''): void
{
    if (talk_dash_use_tailwind()) {
        echo '<main class="main flex-1 min-w-0 pb-20 lg:pb-8 px-4 sm:px-8 lg:px-10 py-6 sm:py-8 bg-gradient-to-br from-slate-50 via-white to-blue-50/40 animate-fade-in' . $extraClass . '">';

        return;
    }
    $cls = 'main' . ($extraClass !== '' ? ' ' . trim($extraClass) : '');
    echo '<main class="' . htmlspecialchars($cls, ENT_QUOTES, 'UTF-8') . '">';
}

function talk_dash_main_close(): void
{
    echo '</main>';
}

function talk_dash_end(): void
{
    echo '</div></body></html>';
}

function talk_dash_page_header(string $title, string $subtitle = ''): void
{
    echo '<header class="page-head">';
    echo '<div><h1 class="page-title">' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h1>';
    if ($subtitle !== '') {
        echo '<p class="page-lead">' . htmlspecialchars($subtitle, ENT_QUOTES, 'UTF-8') . '</p>';
    }
    echo '</div></header>';
}

function talk_dash_login_render(string $loginError, bool $bad, string $oldUser): void
{
    $t = htmlspecialchars('সাইন ইন', ENT_QUOTES, 'UTF-8');
    echo '<!DOCTYPE html><html lang="bn"><head>';
    echo '<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>' . $t . ' · হ্যালো</title>';
    echo '<link rel="preconnect" href="https://fonts.googleapis.com">';
    echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>';
    echo '<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Noto+Sans+Bengali:wght@400;500;600;700&display=swap" rel="stylesheet">';
    echo '<link rel="stylesheet" href="' . htmlspecialchars(talk_dash_asset('dashboard.css'), ENT_QUOTES, 'UTF-8') . '">';
    echo '<link rel="stylesheet" href="' . htmlspecialchars(talk_dash_asset('login.css'), ENT_QUOTES, 'UTF-8') . '">';
    echo '</head><body class="login-page">';
    echo '<div class="login-wrap"><form class="card login-card" method="post" action="index.php" autocomplete="on">';
    echo '<span class="login-logo">' . talk_dash_icon('logo') . '</span>';
    echo '<h1>হ্যালো</h1><p class="page-lead">কথোপকথন ও বোর্ড মনিটরিং</p>';
    echo '<label class="field"><span>নাম</span><input name="user" maxlength="40" required autocomplete="username" value="';
    echo $bad ? htmlspecialchars($oldUser, ENT_QUOTES, 'UTF-8') : '';
    echo '"></label>';
    echo '<label class="field"><span>পাসওয়ার্ড</span><input name="pass" type="password" maxlength="80" required autocomplete="current-password"></label>';
    echo '<button type="submit" class="btn btn-primary">সাইন ইন</button>';
    if ($loginError !== '') {
        echo '<p class="form-err">' . htmlspecialchars($loginError, ENT_QUOTES, 'UTF-8') . '</p>';
    }
    echo '</form></div></body></html>';
}
