<?php

require_once __DIR__ . '/store.php';

function talk_board_ids(): array
{
    return ['AP', 'STA'];
}

function talk_board_label(string $board): string
{
    return $board === 'STA' ? 'STA (স্পিকার)' : 'AP (মাইক)';
}

function talk_board_ok(string $board): bool
{
    return $board === 'AP' || $board === 'STA';
}

function talk_boards_file(): string
{
    return talk_paths()['root'] . '/boards.json';
}

function talk_boards_default(): array
{
    $out = [];
    foreach (talk_board_ids() as $id) {
        $out[$id] = [
            'pick' => ['ssid' => '', 'pass' => ''],
            'cmd' => '',
            'want_scan' => 0,
            'live' => [
                'connected' => false,
                'ssid' => '',
                'ip' => '',
                'rssi' => 0,
                'at' => 0,
            ],
            'scan' => [],
            'scan_at' => 0,
        ];
    }

    return $out;
}

function talk_boards_read(): array
{
    $data = talk_boards_default();
    $file = talk_boards_file();
    if (! is_file($file)) {
        return $data;
    }
    $raw = json_decode((string) file_get_contents($file), true);
    if (! is_array($raw)) {
        return $data;
    }
    foreach (talk_board_ids() as $id) {
        if (! isset($raw[$id]) || ! is_array($raw[$id])) {
            continue;
        }
        $row = $raw[$id];
        if (isset($row['pick']) && is_array($row['pick'])) {
            $data[$id]['pick']['ssid'] = trim((string) ($row['pick']['ssid'] ?? ''));
            $data[$id]['pick']['pass'] = (string) ($row['pick']['pass'] ?? '');
        }
        $data[$id]['cmd'] = (string) ($row['cmd'] ?? '');
        $data[$id]['want_scan'] = (int) ($row['want_scan'] ?? 0);
        if (isset($row['live']) && is_array($row['live'])) {
            $data[$id]['live']['connected'] = ! empty($row['live']['connected']);
            $data[$id]['live']['ssid'] = trim((string) ($row['live']['ssid'] ?? ''));
            $data[$id]['live']['ip'] = trim((string) ($row['live']['ip'] ?? ''));
            $data[$id]['live']['rssi'] = (int) ($row['live']['rssi'] ?? 0);
            $data[$id]['live']['at'] = (int) ($row['live']['at'] ?? 0);
        }
        if (isset($row['scan']) && is_array($row['scan'])) {
            $scan = [];
            foreach ($row['scan'] as $net) {
                if (! is_array($net)) {
                    continue;
                }
                $ssid = trim((string) ($net['ssid'] ?? ''));
                if ($ssid === '' || strlen($ssid) > 32) {
                    continue;
                }
                $scan[] = [
                    'ssid' => $ssid,
                    'rssi' => (int) ($net['rssi'] ?? 0),
                ];
            }
            $data[$id]['scan'] = array_slice($scan, 0, 24);
        }
        $data[$id]['scan_at'] = (int) ($row['scan_at'] ?? 0);
    }

    return $data;
}

function talk_boards_write(array $data): void
{
    $dir = talk_paths()['root'];
    if (! is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    file_put_contents(
        talk_boards_file(),
        json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
    );
}

function talk_board_wifi_pick(string $board): array
{
    if (! talk_board_ok($board)) {
        return ['ssid' => '', 'pass' => '', 'disconnect' => 0, 'scan' => 0];
    }
    $boards = talk_boards_read();
    $pick = $boards[$board]['pick'];
    $out = [
        'ssid' => '',
        'pass' => '',
        'disconnect' => $boards[$board]['cmd'] === 'disconnect' ? 1 : 0,
        'scan' => $boards[$board]['want_scan'] > 0 ? 1 : 0,
    ];
    if ($pick['ssid'] !== '') {
        $out['ssid'] = $pick['ssid'];
        $out['pass'] = $pick['pass'];
    }

    return $out;
}

function talk_board_wifi_ack(string $board): void
{
    if (! talk_board_ok($board)) {
        return;
    }
    $boards = talk_boards_read();
    if ($boards[$board]['cmd'] === 'disconnect') {
        $boards[$board]['cmd'] = '';
    }
    if ($boards[$board]['want_scan'] > 0) {
        $boards[$board]['want_scan'] = 0;
    }
    talk_boards_write($boards);
}

function talk_board_request_scan(string $board): void
{
    if (! talk_board_ok($board)) {
        return;
    }
    $boards = talk_boards_read();
    $boards[$board]['want_scan'] = time();
    talk_boards_write($boards);
}

function talk_board_disconnect(string $board): void
{
    if (! talk_board_ok($board)) {
        return;
    }
    $boards = talk_boards_read();
    $boards[$board]['cmd'] = 'disconnect';
    talk_boards_write($boards);
}

function talk_board_assign(string $board, string $ssid, string $pass): void
{
    if (! talk_board_ok($board)) {
        return;
    }
    $ssid = trim($ssid);
    if ($ssid === '' || strlen($ssid) > 32) {
        return;
    }
    if (strlen($pass) > 64) {
        $pass = substr($pass, 0, 64);
    }
    $boards = talk_boards_read();
    $boards[$board]['pick'] = [
        'ssid' => $ssid,
        'pass' => $pass,
        'set_at' => time(),
    ];
    $boards[$board]['cmd'] = '';
    talk_boards_write($boards);
}

/**
 * @param  list<array{ssid: string, rssi: int}>  $scan
 */
function talk_board_report(string $board, bool $connected, string $ssid, string $ip, int $rssi, array $scan): void
{
    if (! talk_board_ok($board)) {
        return;
    }
    $boards = talk_boards_read();
    $now = time();
    $boards[$board]['live'] = [
        'connected' => $connected,
        'ssid' => trim($ssid),
        'ip' => trim($ip),
        'rssi' => $rssi,
        'at' => $now,
    ];
    if ($scan !== []) {
        $clean = [];
        foreach ($scan as $net) {
            if (! is_array($net)) {
                continue;
            }
            $name = trim((string) ($net['ssid'] ?? ''));
            if ($name === '' || strlen($name) > 32) {
                continue;
            }
            $clean[$name] = ['ssid' => $name, 'rssi' => (int) ($net['rssi'] ?? 0)];
        }
        uasort($clean, static function ($a, $b) {
            return ($b['rssi'] <=> $a['rssi']);
        });
        $boards[$board]['scan'] = array_slice(array_values($clean), 0, 24);
        $boards[$board]['scan_at'] = $now;
    }
    talk_boards_write($boards);
}

function talk_boards_public(): array
{
    $boards = talk_boards_read();
    $out = [];
    foreach (talk_board_ids() as $id) {
        $row = $boards[$id];
        $out[$id] = [
            'label' => talk_board_label($id),
            'pick_ssid' => $row['pick']['ssid'],
            'connected' => $row['live']['connected'],
            'live_ssid' => $row['live']['ssid'],
            'ip' => $row['live']['ip'],
            'rssi' => $row['live']['rssi'],
            'live_at' => $row['live']['at'],
            'scan' => $row['scan'],
            'scan_at' => $row['scan_at'],
        ];
    }

    return $out;
}
