<?php

require_once dirname(__DIR__) . '/lib/boardnet.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$cfg = talk_config();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $token = isset($_GET['token']) ? (string) $_GET['token'] : '';
    if ($token !== '' && hash_equals($cfg['token'], $token)) {
        echo json_encode([
            'ok' => true,
            'now' => time(),
            'live_window' => talk_board_live_window(),
            'live_grace' => talk_board_live_grace(),
            'boards' => talk_boards_public(),
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    talk_require_web();
    echo json_encode([
        'ok' => true,
        'now' => time(),
        'live_window' => talk_board_live_window(),
        'live_grace' => talk_board_live_grace(),
        'boards' => talk_boards_public(),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($method !== 'POST') {
    http_response_code(405);
    echo '{"ok":false}';
    exit;
}

$body = json_decode(file_get_contents('php://input') ?: '', true);
if (! is_array($body)) {
    http_response_code(400);
    echo '{"ok":false}';
    exit;
}

$token = (string) ($body['token'] ?? '');
if ($token !== '' && hash_equals($cfg['token'], $token)) {
    $board = (string) ($body['board'] ?? '');
    if (! talk_board_ok($board)) {
        http_response_code(400);
        echo '{"ok":false}';
        exit;
    }
    $connected = ! empty($body['connected']);
    $ssid = (string) ($body['ssid'] ?? '');
    $ip = (string) ($body['ip'] ?? '');
    $rssi = (int) ($body['rssi'] ?? 0);
    $halowRssi = (int) ($body['halow_rssi'] ?? 0);
    $batMv = (int) ($body['bat_mv'] ?? 0);
    $batPct = (int) ($body['bat_pct'] ?? 0);
    $batUsb = (int) ($body['bat_usb'] ?? 0);
    $batPin = (int) ($body['bat_pin'] ?? -1);
    $scan = isset($body['scan']) && is_array($body['scan']) ? $body['scan'] : [];
    talk_board_report($board, $connected, $ssid, $ip, $rssi, $scan, $halowRssi, $batMv, $batPct, $batUsb, $batPin);
    echo '{"ok":true}';
    exit;
}

talk_require_web();
$board = strtoupper((string) ($body['board'] ?? ''));
if (! talk_board_ok($board)) {
    http_response_code(400);
    echo '{"ok":false}';
    exit;
}

$action = (string) ($body['action'] ?? 'connect');
if ($action === 'disconnect') {
    talk_board_disconnect($board);
    echo json_encode(['ok' => true, 'boards' => talk_boards_public()], JSON_UNESCAPED_UNICODE);
    exit;
}
if ($action === 'rescan') {
    talk_board_request_scan($board);
    echo json_encode(['ok' => true, 'boards' => talk_boards_public()], JSON_UNESCAPED_UNICODE);
    exit;
}

$ssid = trim((string) ($body['ssid'] ?? ''));
if ($ssid === '') {
    http_response_code(400);
    echo '{"ok":false}';
    exit;
}

$pass = (string) ($body['pass'] ?? '');
if ($pass === '') {
    $boards = talk_boards_read();
    if ($boards[$board]['pick']['ssid'] === $ssid) {
        $pass = $boards[$board]['pick']['pass'];
    }
}
talk_board_assign($board, $ssid, $pass);
echo json_encode(['ok' => true, 'boards' => talk_boards_public()], JSON_UNESCAPED_UNICODE);
