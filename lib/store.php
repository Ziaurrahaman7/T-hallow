<?php

require_once __DIR__ . '/adpcm.php';

function talk_live_upload_url(): string
{
    return 'https://hallow.mmgbd.com/api/upload.php';
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
    $self = talk_config()['upload_url'];
    $live = talk_live_upload_url();
    if (rtrim($self, '/') === rtrim($live, '/')) {
        return [$self];
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
function talk_upload_urls(): array
{
    return talk_servers_read()['uploads'];
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

function talk_session_start(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    session_name('HALLOWSESS');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
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
    echo '{"ok":false}';
    exit;
}
