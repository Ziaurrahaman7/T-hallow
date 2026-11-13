<?php

require_once __DIR__ . '/adpcm.php';

function talk_live_upload_url(): string
{
    return 'https://hallow.mmgbd.com/api/upload.php';
}

/** PC LAN URL so boards (not on localhost) can reach Laragon. data/local.json */
function talk_lan_base_read_file(): ?string
{
    $file = talk_paths()['root'].'/local.json';
    if (! is_file($file)) {
        return null;
    }
    $data = json_decode((string) file_get_contents($file), true);
    if (! is_array($data)) {
        return null;
    }
    $base = trim((string) ($data['lan_base'] ?? ''));

    return $base !== '' ? rtrim($base, '/') : null;
}

function talk_is_production_host(?string $host = null): bool
{
    $host ??= talk_http_host_name();

    return $host === 'hallow.mmgbd.com' || $host === 'www.hallow.mmgbd.com';
}

function talk_lan_base(): ?string
{
    if (talk_is_production_host()) {
        return null;
    }
    if (PHP_SAPI !== 'cli' && ! empty($_SERVER['REQUEST_METHOD']) && talk_is_local_dev_host()) {
        $live = talk_lan_base_for_boards(talk_lan_base_read_file(), false);
        if ($live !== null) {
            return rtrim($live, '/');
        }
    }

    return talk_lan_base_read_file();
}

function talk_device_upload_url(): string
{
    $lan = talk_lan_base();
    if ($lan !== null) {
        return $lan.'/api/upload.php';
    }

    return talk_config()['upload_url'];
}

function talk_base_url(): ?string
{
    if (PHP_SAPI === 'cli' || empty($_SERVER['HTTP_HOST'])) {
        return null;
    }
    $https = (! empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    $scheme = $https ? 'https' : 'http';
    $host = (string) $_SERVER['HTTP_HOST'];
    $sn = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
    if (str_ends_with($sn, '/index.php')) {
        return $scheme.'://'.$host.substr($sn, 0, -strlen('/index.php'));
    }
    if (str_contains($sn, '/api/')) {
        return $scheme.'://'.$host.substr($sn, 0, strpos($sn, '/api/'));
    }

    return $scheme.'://'.$host;
}

function talk_config(): array
{
    $base = talk_base_url();
    $self = $base !== null
        ? rtrim($base, '/').'/api/upload.php'
        : 'http://127.0.0.1/T-hallow/api/upload.php';

    return [
        'token' => 'hallow',
        'upload_url' => $self,
    ];
}

function talk_servers_file(): string
{
    return talk_paths()['root'].'/servers.json';
}

/**
 * @return list<string>
 */
function talk_servers_default_uploads(): array
{
    $live = rtrim(talk_live_upload_url(), '/');
    if (talk_lan_base() !== null) {
        $local = rtrim(talk_device_upload_url(), '/');
        if ($local === $live) {
            return [$live];
        }

        return [$local, $live];
    }
    $self = rtrim((string) talk_config()['upload_url'], '/');
    if ($self === '' || $self === $live) {
        return [$live];
    }

    return [$self, $live];
}

/**
 * @return array{uploads: list<string>}
 */
function talk_servers_read(): array
{
    $uploads = talk_servers_default_uploads();
    $file = talk_servers_file();
    if (is_file($file)) {
        $data = json_decode((string) file_get_contents($file), true);
        if (is_array($data) && isset($data['uploads']) && is_array($data['uploads'])) {
            $uploads = [];
            foreach ($data['uploads'] as $url) {
                $url = trim((string) $url);
                if ($url === '' || strlen($url) > 220) {
                    continue;
                }
                if (! preg_match('#^https?://#i', $url)) {
                    continue;
                }
                $uploads[] = $url;
            }
        }
    }
    $uploads = array_values(array_unique($uploads));
    if ($uploads === []) {
        $uploads = talk_servers_default_uploads();
    }

    return ['uploads' => array_slice($uploads, 0, 2)];
}

/**
 * @param  list<string>  $uploads
 */
function talk_servers_save(array $uploads): void
{
    $clean = [];
    foreach ($uploads as $url) {
        $url = trim((string) $url);
        if ($url === '' || strlen($url) > 220) {
            continue;
        }
        if (! preg_match('#^https?://#i', $url)) {
            continue;
        }
        $clean[] = $url;
    }
    $clean = array_values(array_unique($clean));
    if ($clean === []) {
        $clean = talk_servers_default_uploads();
    }
    $clean = array_slice($clean, 0, 2);
    $dir = talk_paths()['root'];
    if (! is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    file_put_contents(
        talk_servers_file(),
        json_encode(['uploads' => $clean], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
    );
}

/**
 * @return list<string>
 */
function talk_production_upload_url(): string
{
    $self = rtrim((string) talk_config()['upload_url'], '/');
    if ($self !== '' && talk_is_production_host()) {
        return $self;
    }

    return rtrim(talk_live_upload_url(), '/');
}

function talk_merge_upload_urls(array $primary, array $extra): array
{
    $live = rtrim(talk_live_upload_url(), '/');
    $out = [];
    foreach (array_merge($primary, $extra) as $url) {
        $url = rtrim((string) $url, '/');
        if ($url === '' || strlen($url) > 220) {
            continue;
        }
        if (! preg_match('#^https?://#i', $url)) {
            continue;
        }
        if (in_array($url, $out, true)) {
            continue;
        }
        $out[] = $url;
        if (count($out) >= 2) {
            break;
        }
    }
    if (! in_array($live, $out, true) && count($out) < 2) {
        $out[] = $live;
    }

    return array_slice($out, 0, 2);
}

function talk_upload_urls(): array
{
    if (PHP_SAPI !== 'cli' && talk_is_production_host()) {
        return [rtrim(talk_production_upload_url(), '/')];
    }
    $uploads = talk_servers_read()['uploads'];
    if (PHP_SAPI !== 'cli' && ! empty($_SERVER['REQUEST_METHOD']) && talk_is_local_dev_host()) {
        $lan = talk_lan_base();
        if ($lan !== null) {
            $primary = rtrim($lan, '/').'/api/upload.php';

            return talk_merge_upload_urls([$primary], $uploads);
        }
    }

    return talk_merge_upload_urls($uploads, []);
}

function talk_local_json_file(): string
{
    return talk_paths()['root'].'/local.json';
}

function talk_net_config_local_h(): string
{
    return dirname(__DIR__).'/shared/net_config.local.h';
}

function talk_http_host_name(): string
{
    $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
    $host = preg_replace('/:\d+$/', '', $host);

    return $host;
}

/** Laragon / LAN dashboard — not the public live site. */
function talk_is_local_dev_host(?string $host = null): bool
{
    $host ??= talk_http_host_name();
    if ($host === '' || $host === 'hallow.mmgbd.com' || $host === 'www.hallow.mmgbd.com') {
        return false;
    }
    if ($host === 'localhost' || $host === '127.0.0.1' || $host === '[::1]' || $host === '::1') {
        return true;
    }
    if (str_ends_with($host, '.test') || str_ends_with($host, '.local')) {
        return true;
    }
    if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
        return filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
    }

    return false;
}

/** Web path prefix (e.g. /T-hallow) or "" when vhost docroot is the app. */
function talk_web_app_path(): string
{
    $sn = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
    if ($sn === '') {
        return '';
    }
    if (str_contains($sn, '/api/')) {
        $path = substr($sn, 0, strpos($sn, '/api/'));
    } elseif (str_ends_with($sn, '/index.php')) {
        $path = dirname($sn);
    } elseif (str_ends_with($sn, '/net.php') || str_ends_with($sn, '/status.php') || str_ends_with($sn, '/battery.php')) {
        $path = dirname($sn);
    } else {
        $path = dirname($sn);
    }
    if ($path === '/' || $path === '\\' || $path === '.') {
        return '';
    }

    return $path;
}

function talk_lan_ip_cache_file(): string
{
    return talk_paths()['root'].'/lan_ip_cache.json';
}

/**
 * @return list<string>
 */
function talk_all_lan_ipv4(bool $refresh = false): array
{
    $cacheFile = talk_lan_ip_cache_file();
    if (! $refresh && is_file($cacheFile)) {
        $data = json_decode((string) file_get_contents($cacheFile), true);
        if (is_array($data) && isset($data['ips']) && is_array($data['ips'])) {
            $age = time() - (int) ($data['at'] ?? 0);
            if ($age >= 0 && $age < 900) {
                $ips = [];
                foreach ($data['ips'] as $ip) {
                    $ip = trim((string) $ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP)) {
                        $ips[] = $ip;
                    }
                }
                if ($ips !== []) {
                    return array_values(array_unique($ips));
                }
            }
        }
    }

    $ips = [];
    if (DIRECTORY_SEPARATOR === '\\') {
        $script = dirname(__DIR__).'/tools/get_lan_ips.ps1';
        if (is_file($script)) {
            $cmd = 'powershell -NoProfile -ExecutionPolicy Bypass -File '
                .escapeshellarg(str_replace('/', '\\', $script));
            $out = @shell_exec($cmd);
            if (is_string($out) && trim($out) !== '') {
                foreach (preg_split('/\R+/', trim($out)) as $line) {
                    $line = trim($line);
                    if (filter_var($line, FILTER_VALIDATE_IP)) {
                        $ips[] = $line;
                    }
                }
            }
        }
    }
    if ($ips === []) {
        $one = talk_detect_lan_ipv4_fallback();
        if ($one !== null) {
            $ips = [$one];
        }
    }
    $ips = array_values(array_unique($ips));
    if ($ips !== []) {
        @file_put_contents(
            $cacheFile,
            json_encode(['ips' => $ips, 'at' => time()], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
        );
    }

    return $ips;
}

function talk_detect_lan_ipv4_fallback(): ?string
{
    if (DIRECTORY_SEPARATOR === '\\') {
        $script = dirname(__DIR__).'/tools/get_lan_ip.ps1';
        if (is_file($script)) {
            $cmd = 'powershell -NoProfile -ExecutionPolicy Bypass -File '
                .escapeshellarg(str_replace('/', '\\', $script));
            $out = @shell_exec($cmd);
            if (is_string($out)) {
                $ip = trim($out);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
    }
    if (function_exists('socket_create')) {
        $sock = @socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
        if ($sock !== false) {
            @socket_connect($sock, '8.8.8.8', 80);
            $addr = '';
            if (@socket_getsockname($sock, $addr) && $addr !== '' && $addr !== '127.0.0.1') {
                @socket_close($sock);

                return $addr;
            }
            @socket_close($sock);
        }
    }
    $srv = (string) ($_SERVER['SERVER_ADDR'] ?? '');
    if ($srv !== '' && $srv !== '127.0.0.1' && $srv !== '::1') {
        return $srv;
    }
    $name = gethostname();
    if (is_string($name) && $name !== '') {
        $ip = gethostbyname($name);
        if ($ip !== '' && $ip !== $name && $ip !== '127.0.0.1') {
            return $ip;
        }
    }

    return null;
}

function talk_detect_lan_ipv4(?string $prefer = null, bool $refresh = false): ?string
{
  $ips = talk_all_lan_ipv4($refresh);
  $home = array_values(array_filter(
      $ips,
      static fn (string $ip): bool => str_starts_with($ip, '192.168.')
  ));
  if ($home !== []) {
      if ($prefer !== null && $prefer !== '' && in_array($prefer, $home, true)) {
          return $prefer;
      }

      return $home[0];
  }
  if ($prefer !== null && $prefer !== '' && in_array($prefer, $ips, true)) {
    return $prefer;
  }
  if ($ips !== []) {
    usort($ips, static function (string $a, string $b): int {
      $rank = static function (string $ip): int {
        if (str_starts_with($ip, '192.168.')) {
          return 0;
        }
        if (str_starts_with($ip, '10.')) {
          return 2;
        }

        return 1;
      };
      $ra = $rank($a);
      $rb = $rank($b);
      if ($ra !== $rb) {
        return $ra <=> $rb;
      }

      return strcmp($a, $b);
    });

    return $ips[0];
  }

  return talk_detect_lan_ipv4_fallback();
}

function talk_lan_base_for_boards(?string $prevBase = null, bool $refreshIps = false): ?string
{
    $host = talk_http_host_name();
    $prefer = null;
    $prev = $prevBase ?? talk_lan_base_read_file();
    if ($prev !== null && preg_match('#^https?://([^/]+)#i', $prev, $m)) {
        $prefer = $m[1];
    }
    if (filter_var($host, FILTER_VALIDATE_IP) && talk_is_local_dev_host($host)) {
        $ip = $host;
    } else {
        $ip = talk_detect_lan_ipv4($prefer, $refreshIps);
    }
    if ($ip === null) {
        return null;
    }
    $path = talk_web_app_path();

    return 'http://'.$ip.$path;
}

function talk_net_header_wifi_defaults(): array
{
    $ssid = '';
    $pass = '';
    $file = talk_net_config_local_h();
    if (is_file($file)) {
        $text = (string) file_get_contents($file);
        if (preg_match('/#define\s+NET_SSID\s+"((?:\\\\.|[^"\\\\])*)"/', $text, $m)) {
            $ssid = stripcslashes($m[1]);
        }
        if (preg_match('/#define\s+NET_PASS\s+"((?:\\\\.|[^"\\\\])*)"/', $text, $m)) {
            $pass = stripcslashes($m[1]);
        }
    }
    if ($ssid === '') {
        $text = (string) @file_get_contents(dirname(__DIR__).'/shared/net_config.h');
        if (preg_match('/#define\s+NET_SSID\s+"((?:\\\\.|[^"\\\\])*)"/', $text, $m)) {
            $ssid = stripcslashes($m[1]);
        }
        if (preg_match('/#define\s+NET_PASS\s+"((?:\\\\.|[^"\\\\])*)"/', $text, $m)) {
            $pass = stripcslashes($m[1]);
        }
    }

    return ['ssid' => $ssid, 'pass' => $pass];
}

function talk_write_net_config_local_h(string $lanBase, string $ssid, string $pass): void
{
    $upload = rtrim($lanBase, '/').'/api/upload.php';
    $alt = rtrim(talk_live_upload_url(), '/');
    $body = "#pragma once\n"
        ."// Auto dashboard sync ".date('c')." — re-flash AP/STA after IP/path change.\n"
        .'#define NET_URL '.talk_c_quote($upload)."\n"
        .'#define NET_URL_ALT '.talk_c_quote($alt)."\n"
        .'#define NET_SSID '.talk_c_quote($ssid)."\n"
        .'#define NET_PASS '.talk_c_quote($pass)."\n";
    $file = talk_net_config_local_h();
    if (is_file($file)) {
        $old = (string) file_get_contents($file);
        if ($old === $body) {
            return;
        }
    }
    file_put_contents($file, $body);
}

/**
 * localhost / .test / LAN IP দিয়ে ড্যাশবোর্ড খুললে boards এর জন্য lan_base + uploads + net_config.local.h আপডেট।
 */
function talk_sync_local_lan_if_needed(): void
{
    static $ran = false;
    if ($ran) {
        return;
    }
    $ran = true;
    if (PHP_SAPI === 'cli' || empty($_SERVER['REQUEST_METHOD'])) {
        return;
    }
    if (! talk_is_local_dev_host()) {
        return;
    }
    $lanBase = talk_lan_base_for_boards(null, true);
    if ($lanBase === null) {
        return;
    }
    $upload = rtrim($lanBase, '/').'/api/upload.php';
    $host = talk_http_host_name();
    $changed = false;

    $localFile = talk_local_json_file();
    $prevBase = talk_lan_base_read_file();
    if ($prevBase === null || rtrim($prevBase, '/') !== rtrim($lanBase, '/')) {
        $dir = talk_paths()['root'];
        if (! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        file_put_contents(
            $localFile,
            json_encode(
                [
                    'lan_base' => $lanBase,
                    'browser' => 'http://'.$host.talk_web_app_path(),
                    'note' => 'Auto sync '.date('c'),
                ],
                JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
            )
        );
        $changed = true;
    }

    $wantUploads = talk_merge_upload_urls([$upload], [talk_live_upload_url()]);
    $existing = talk_servers_read()['uploads'];
    $norm = static fn (array $list): array => array_map(
        static fn ($u) => rtrim((string) $u, '/'),
        $list
    );
  if ($norm($existing) !== $norm($wantUploads) || ! isset($existing[0]) || rtrim((string) $existing[0], '/') !== rtrim($upload, '/')) {
    talk_servers_save($wantUploads);
    $changed = true;
  }

    $wifi = talk_net_header_wifi_defaults();
    talk_write_net_config_local_h($lanBase, $wifi['ssid'], $wifi['pass']);

    if ($changed) {
        $GLOBALS['talk_local_sync_note'] = $lanBase;
    }
}

function talk_local_sync_note(): string
{
    return (string) ($GLOBALS['talk_local_sync_note'] ?? '');
}

function talk_paths(): array
{
    $root = dirname(__DIR__) . '/data';
    return [
        'root' => $root,
        'index' => $root . '/index.json',
        'wav' => $root . '/wav',
    ];
}

function talk_wifi_file(): string
{
    return talk_paths()['root'] . '/wifi.json';
}

function talk_c_quote(string $value): string
{
    return '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $value) . '"';
}

function talk_wifi_from_header(): array
{
    $text = (string) @file_get_contents(dirname(__DIR__) . '/shared/net_config.h');
    $networks = [];
    if (preg_match_all(
        '/#define\s+NET_SSID(\d*)\s+"((?:\\\\.|[^"\\\\])*)"/',
        $text,
        $ssids,
        PREG_SET_ORDER
    )) {
        foreach ($ssids as $row) {
            $suffix = $row[1] === '' ? '' : $row[1];
            $ssid = stripcslashes($row[2]);
            if ($ssid === '') {
                continue;
            }
            $pass = '';
            if (preg_match('/#define\s+NET_PASS' . preg_quote($suffix, '/') . '\s+"((?:\\\\.|[^"\\\\])*)"/', $text, $m)) {
                $pass = stripcslashes($m[1]);
            }
            $networks[] = ['ssid' => $ssid, 'pass' => $pass];
        }
    }
    if ($networks === [] && preg_match('/#define\s+NET_SSID\s+"((?:\\\\.|[^"\\\\])*)"/', $text, $m)) {
        $ssid = stripcslashes($m[1]);
        $pass = '';
        if (preg_match('/#define\s+NET_PASS\s+"((?:\\\\.|[^"\\\\])*)"/', $text, $pm)) {
            $pass = stripcslashes($pm[1]);
        }
        $networks[] = ['ssid' => $ssid, 'pass' => $pass];
    }

    return $networks;
}

/**
 * @param  list<array{ssid: string, pass: string}>  $rows
 * @return list<array{ssid: string, pass: string}>
 */
function talk_wifi_normalize(array $rows): array
{
    $out = [];
    foreach ($rows as $row) {
        if (! is_array($row)) {
            continue;
        }
        $ssid = trim((string) ($row['ssid'] ?? ''));
        if ($ssid === '' || strlen($ssid) > 32) {
            continue;
        }
        $pass = (string) ($row['pass'] ?? '');
        if (strlen($pass) > 64) {
            $pass = substr($pass, 0, 64);
        }
        $out[$ssid] = ['ssid' => $ssid, 'pass' => $pass];
    }

    return array_values($out);
}

/**
 * @return list<array{ssid: string, pass: string}>
 */
function talk_wifi_networks(): array
{
    $file = talk_wifi_file();
    if (is_file($file)) {
        $data = json_decode((string) file_get_contents($file), true);
        if (is_array($data)) {
            if (isset($data['networks']) && is_array($data['networks'])) {
                $list = talk_wifi_normalize($data['networks']);
                if ($list !== []) {
                    return array_slice($list, 0, 5);
                }
            }
            if (isset($data['ssid'])) {
                $one = talk_wifi_normalize([[
                    'ssid' => (string) $data['ssid'],
                    'pass' => (string) ($data['pass'] ?? ''),
                ]]);
                if ($one !== []) {
                    return $one;
                }
            }
        }
    }

    $fromHeader = talk_wifi_from_header();

    return array_slice($fromHeader, 0, 5);
}

function talk_wifi_read(): array
{
    $networks = talk_wifi_networks();
    $first = $networks[0] ?? ['ssid' => '', 'pass' => ''];

    return [
        'ssid' => $first['ssid'],
        'pass' => $first['pass'],
        'networks' => $networks,
    ];
}

/**
 * @param  list<array{ssid: string, pass: string}>  $networks
 */
function talk_wifi_write(array $networks): void
{
    $networks = talk_wifi_normalize($networks);
    if ($networks === []) {
        return;
    }
    $networks = array_slice($networks, 0, 5);
    $dir = talk_paths()['root'];
    if (! is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    file_put_contents(
        talk_wifi_file(),
        json_encode(['networks' => $networks], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
    );
    $first = $networks[0];
    $path = dirname(__DIR__) . '/shared/net_config.h';
    $text = @file_get_contents($path);
    if ($text === false) {
        return;
    }
    $lines = preg_split("/\r\n|\n|\r/", $text);
    foreach ($lines as $i => $line) {
        if (strpos(ltrim($line), '#define NET_SSID ') === 0) {
            $lines[$i] = '#define NET_SSID ' . talk_c_quote($first['ssid']);
        }
        if (strpos(ltrim($line), '#define NET_PASS ') === 0) {
            $lines[$i] = '#define NET_PASS ' . talk_c_quote($first['pass']);
        }
    }
    file_put_contents($path, implode("\n", $lines) . "\n");
}

function talk_wifi_save(string $ssid, string $pass): void
{
    $ssid = trim($ssid);
    if ($ssid === '') {
        return;
    }
    $networks = talk_wifi_networks();
    $rest = [];
    foreach ($networks as $row) {
        if ($row['ssid'] !== $ssid) {
            $rest[] = $row;
        }
    }
    array_unshift($rest, ['ssid' => $ssid, 'pass' => $pass]);
    talk_wifi_write($rest);
}

/**
 * @param  list<array{ssid: string, pass: string}>  $extra
 */
function talk_wifi_merge(array $extra): void
{
    $merged = talk_wifi_normalize(array_merge($extra, talk_wifi_networks()));
    talk_wifi_write($merged);
}

function talk_bangla_day(int $unix): string
{
    $months = [
        1 => 'জানুয়ারি', 2 => 'ফেব্রুয়ারি', 3 => 'মার্চ', 4 => 'এপ্রিল',
        5 => 'মে', 6 => 'জুন', 7 => 'জুলাই', 8 => 'আগস্ট',
        9 => 'সেপ্টেম্বর', 10 => 'অক্টোবর', 11 => 'নভেম্বর', 12 => 'ডিসেম্বর',
    ];
    $m = (int) date('n', $unix);
    return date('j', $unix) . ' ' . $months[$m] . ' ' . date('Y', $unix);
}

function talk_pcm_peak(array $pcm): int
{
    $peak = 0;
    foreach ($pcm as $s) {
        $a = abs((int) $s);
        if ($a > $peak) {
            $peak = $a;
        }
    }

    return $peak;
}

function talk_voice_like(array $pcm): bool
{
    $n = count($pcm);
    if ($n < 640) {
        return false;
    }
    $peak = talk_pcm_peak($pcm);
    if ($peak >= 1050) {
        return true;
    }
    if ($peak < 680) {
        return false;
    }
    $strong = 0;
    $sum = 0;
    $zcr = 0;
    $prev = 0;
    foreach ($pcm as $i => $s) {
        $s = (int) $s;
        $a = abs($s);
        $sum += $a;
        if ($a >= 450) {
            $strong++;
        }
        if ($i > 0 && (($s ^ $prev) < 0) && ($a > 150 || abs($prev) > 150)) {
            $zcr++;
        }
        $prev = $s;
    }
    $avg = $sum / $n;
    if ($avg < 55) {
        return false;
    }
    if ($strong < (int) ($n * 0.035)) {
        return false;
    }
    if ($strong > (int) ($n * 0.90)) {
        return false;
    }
    if ($zcr < 5 || $zcr > 200) {
        return false;
    }

    return true;
}

function talk_save_batch(string $board, int $boardNow, array $clips): int
{
    date_default_timezone_set('Asia/Dhaka');
    $paths = talk_paths();
    if (!is_dir($paths['wav'])) {
        mkdir($paths['wav'], 0777, true);
    }

    $nowMs = (int) round(microtime(true) * 1000);
    $decoded = [];
    foreach ($clips as $clip) {
        if (!is_array($clip) || !isset($clip['t'], $clip['b'])) {
            continue;
        }
        $raw = base64_decode((string) $clip['b'], true);
        if ($raw === false) {
            continue;
        }
        $pcm = adpcm_unpack($raw);
        if ($pcm === null || talk_pcm_peak($pcm) < 400) {
            continue;
        }
        $t = (int) $clip['t'];
        if ($t < 0) {
            $t = 0;
        }
        if ($boardNow > 0 && $t > $boardNow) {
            $t = $boardNow;
        }
        $who = $board;
        if (isset($clip['w']) && ($clip['w'] === 'AP' || $clip['w'] === 'STA')) {
            $who = $clip['w'];
        }
        $decoded[] = ['t' => $t, 'pcm' => $pcm, 'who' => $who];
    }
    if (!$decoded) {
        return 0;
    }
    usort($decoded, function ($a, $b) {
        return $a['t'] <=> $b['t'];
    });

    $groups = [];
    $cur = null;
    foreach ($decoded as $item) {
        if ($cur === null || $item['who'] !== $cur['who'] || ($item['t'] - $cur['last']) > 2000) {
            if ($cur !== null) {
                $groups[] = $cur;
            }
            $cur = [
                'start' => $item['t'],
                'last' => $item['t'],
                'who' => $item['who'],
                'pcm' => $item['pcm'],
            ];
            continue;
        }
        $gapMs = $item['t'] - $cur['last'];
        $silence = (int) round(($gapMs - 40) * 8);
        if ($silence > 8 && $silence < 3600) {
            $cur['pcm'] = array_merge($cur['pcm'], array_fill(0, $silence, 0));
        }
        $cur['pcm'] = array_merge($cur['pcm'], $item['pcm']);
        $cur['last'] = $item['t'];
    }
    if ($cur !== null) {
        $groups[] = $cur;
    }

    $fp = fopen($paths['index'], 'c+');
    if ($fp === false) {
        return 0;
    }
    flock($fp, LOCK_EX);
    $raw = stream_get_contents($fp);
    $data = json_decode($raw ?: '', true);
    if (!is_array($data) || !isset($data['utterances']) || !is_array($data['utterances'])) {
        $data = ['utterances' => []];
    }
    $saved = 0;
    foreach ($groups as $group) {
        $age = $boardNow > $group['start'] ? ($boardNow - $group['start']) : 0;
        $wall = $nowMs - $age;
        $sec = round(count($group['pcm']) / 8000, 2);
        $joined = false;
        for ($i = count($data['utterances']) - 1; $i >= 0; $i--) {
            $prev = $data['utterances'][$i];
            if (!isset($prev['board'], $prev['ms'], $prev['id']) || $prev['board'] !== $group['who']) {
                continue;
            }
            $prevEnd = (int) $prev['ms'] + (int) round(((float) ($prev['sec'] ?? 0)) * 1000);
            $prevSec = (float) ($prev['sec'] ?? 0);
            if ($wall + 400 < $prevEnd || ($wall - $prevEnd) > 2000 || $prevSec > 25) {
                break;
            }
            $file = $paths['wav'] . '/' . $prev['id'] . '.wav';
            $old = talk_wav_samples($file);
            if (!$old) {
                break;
            }
            $gap = (int) round(($wall - $prevEnd) * 8);
            if ($gap > 8 && $gap < 16000) {
                $old = array_merge($old, array_fill(0, $gap, 0));
            }
            if (! talk_voice_like($group['pcm']) && talk_pcm_peak($group['pcm']) < 720) {
                break;
            }
            $pcm = array_merge($old, $group['pcm']);
            if (file_put_contents($file, wav_from_pcm($pcm)) === false) {
                break;
            }
            $data['utterances'][$i]['sec'] = round(count($pcm) / 8000, 2);
            $joined = true;
            $saved++;
            break;
        }
        if ($joined) {
            continue;
        }
        if (! talk_voice_like($group['pcm'])) {
            continue;
        }
        $id = 'u' . bin2hex(random_bytes(8));
        $file = $paths['wav'] . '/' . $id . '.wav';
        if (file_put_contents($file, wav_from_pcm($group['pcm'])) === false) {
            continue;
        }
        $unix = (int) floor($wall / 1000);
        $data['utterances'][] = [
            'id' => $id,
            'board' => $group['who'],
            'ms' => $wall,
            'at' => date('H:i:s', $unix),
            'day' => talk_bangla_day($unix),
            'sec' => $sec,
        ];
        $saved++;
    }
    usort($data['utterances'], function ($a, $b) {
        return ($a['ms'] <=> $b['ms']);
    });
    while (count($data['utterances']) > 400) {
        $old = array_shift($data['utterances']);
        if (isset($old['id'])) {
            $oldFile = $paths['wav'] . '/' . $old['id'] . '.wav';
            if (is_file($oldFile)) {
                unlink($oldFile);
            }
        }
    }
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, $json === false ? '{"utterances":[]}' : $json);
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);
    return $saved;
}

function talk_wav_samples(string $file): array
{
    if (!is_file($file)) {
        return [];
    }
    $raw = file_get_contents($file);
    if ($raw === false || strlen($raw) < 44) {
        return [];
    }
    $pcm = substr($raw, 44);
    $out = [];
    $n = intdiv(strlen($pcm), 2);
    for ($i = 0; $i < $n; $i++) {
        $u = unpack('v', substr($pcm, $i * 2, 2));
        if ($u === false) {
            break;
        }
        $s = $u[1];
        if ($s >= 32768) {
            $s -= 65536;
        }
        $out[] = $s;
    }
    return $out;
}

function talk_id_ok(string $id): bool
{
    return (bool) preg_match('/^u[a-f0-9]{16}$/', $id);
}

function talk_clear(bool $all, array $ids): int
{
    $paths = talk_paths();
    if (!is_dir($paths['wav'])) {
        mkdir($paths['wav'], 0777, true);
    }
    $fp = fopen($paths['index'], 'c+');
    if ($fp === false) {
        return 0;
    }
    flock($fp, LOCK_EX);
    $raw = stream_get_contents($fp);
    $data = json_decode($raw ?: '', true);
    if (!is_array($data) || !isset($data['utterances']) || !is_array($data['utterances'])) {
        $data = ['utterances' => []];
    }
    $drop = [];
    if ($all) {
        foreach ($data['utterances'] as $row) {
            if (isset($row['id'])) {
                $drop[(string) $row['id']] = true;
            }
        }
        foreach (glob($paths['wav'] . '/u*.wav') ?: [] as $file) {
            $drop[basename($file, '.wav')] = true;
        }
    } else {
        foreach ($ids as $id) {
            $id = (string) $id;
            if (talk_id_ok($id)) {
                $drop[$id] = true;
            }
        }
    }
    $kept = [];
    $removed = 0;
    foreach ($data['utterances'] as $row) {
        $id = isset($row['id']) ? (string) $row['id'] : '';
        if ($id !== '' && isset($drop[$id])) {
            $removed++;
            continue;
        }
        $kept[] = $row;
    }
    foreach (array_keys($drop) as $id) {
        if (!talk_id_ok($id)) {
            continue;
        }
        $file = $paths['wav'] . '/' . $id . '.wav';
        if (is_file($file)) {
            unlink($file);
        }
    }
    $data['utterances'] = $kept;
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, $json === false ? '{"utterances":[]}' : $json);
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);
    return $removed;
}

function talk_list(): array
{
    $paths = talk_paths();
    if (!is_file($paths['index'])) {
        return [];
    }
    $data = json_decode((string) file_get_contents($paths['index']), true);
    if (!is_array($data) || !isset($data['utterances']) || !is_array($data['utterances'])) {
        return [];
    }
    return $data['utterances'];
}

function talk_auth_file(): string
{
    return talk_paths()['root'] . '/auth.json';
}

function talk_auth_read(): array
{
    $file = talk_auth_file();
    if (!is_file($file)) {
        $dir = talk_paths()['root'];
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        $data = [
            'user' => 'admin',
            'hash' => password_hash('hallow', PASSWORD_DEFAULT),
        ];
        file_put_contents($file, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        return $data;
    }
    $data = json_decode((string) file_get_contents($file), true);
    if (!is_array($data) || !isset($data['user'], $data['hash'])) {
        return ['user' => '', 'hash' => ''];
    }
    return [
        'user' => (string) $data['user'],
        'hash' => (string) $data['hash'],
    ];
}

function talk_session_path(): string
{
    $sn = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    if (preg_match('#^(.+)/(api|lib)/#', $sn, $m)) {
        return $m[1].'/';
    }
    $dir = dirname($sn);
    if ($dir === '/' || $dir === '.' || $dir === '') {
        return '/';
    }

    return rtrim($dir, '/').'/';
}

function talk_session_start(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    session_name('HALLOWSESS');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => talk_session_path(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function talk_logged_in(): bool
{
    return isset($_SESSION['talk_user']) && $_SESSION['talk_user'] !== '';
}

function talk_try_login(string $user, string $pass): bool
{
    $auth = talk_auth_read();
    if ($auth['user'] === '' || !hash_equals($auth['user'], $user)) {
        return false;
    }
    return password_verify($pass, $auth['hash']);
}

function talk_require_web(): void
{
    talk_session_start();
    if (talk_logged_in()) {
        return;
    }
    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo '{"ok":false,"err":"login"}';
    exit;
}

/** Live সাইটে local.json/servers.json ভুল LAN URL থাকলে HTTPS upload ঠিক করে। */
function talk_sync_production_uploads_if_needed(): void
{
    static $ran = false;
    if ($ran || PHP_SAPI === 'cli' || ! talk_is_production_host()) {
        return;
    }
    $ran = true;
  if (empty($_SERVER['REQUEST_METHOD'])) {
      return;
  }
    $want = [talk_production_upload_url()];
    $existing = talk_servers_read()['uploads'];
    $norm = static fn (array $a): array => array_map(
        static fn ($u) => rtrim((string) $u, '/'),
        $a
    );
    if ($norm($existing) === $norm($want)) {
        return;
    }
    talk_servers_save($want);
}

function talk_should_auto_sync_local(): bool
{
    $sn = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
    if (str_ends_with($sn, '/index.php') || str_ends_with($sn, '/net.php')
        || str_ends_with($sn, '/status.php') || str_ends_with($sn, '/battery.php')) {
        return true;
    }
    if (str_ends_with($sn, '/api/wifi.php') && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        return true;
    }

    return false;
}

if (PHP_SAPI !== 'cli') {
    talk_sync_production_uploads_if_needed();
    if (talk_should_auto_sync_local()) {
        talk_sync_local_lan_if_needed();
    }
}
