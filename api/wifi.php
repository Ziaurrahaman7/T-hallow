<?php

require_once dirname(__DIR__) . '/lib/store.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$cfg = talk_config();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $token = isset($_GET['token']) ? (string) $_GET['token'] : '';
    if (!hash_equals($cfg['token'], $token)) {
        http_response_code(403);
        echo '{"ok":false}';
        exit;
    }
    $wifi = talk_wifi_read();
    echo json_encode([
        'ok' => true,
        'ssid' => $wifi['ssid'],
        'pass' => $wifi['pass'],
        'uploads' => talk_upload_urls(),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

talk_require_web();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo '{"ok":false}';
    exit;
}

$body = json_decode(file_get_contents('php://input') ?: '', true);
if (!is_array($body)) {
    http_response_code(400);
    echo '{"ok":false}';
    exit;
}

$ssid = trim((string) ($body['ssid'] ?? ''));
if ($ssid === '') {
    http_response_code(400);
    echo '{"ok":false}';
    exit;
}

$current = talk_wifi_read();
$pass = array_key_exists('pass', $body) ? (string) $body['pass'] : '';
if ($pass === '') {
    $pass = $current['pass'];
}
talk_wifi_save($ssid, $pass);

$uploads = [];
if (isset($body['uploads']) && is_array($body['uploads'])) {
    foreach ($body['uploads'] as $url) {
        $uploads[] = (string) $url;
    }
} else {
    $u1 = trim((string) ($body['upload_url'] ?? ''));
    $u2 = trim((string) ($body['upload_url2'] ?? ''));
    if ($u1 !== '') {
        $uploads[] = $u1;
    }
    if ($u2 !== '') {
        $uploads[] = $u2;
    }
}
if ($uploads !== []) {
    talk_servers_save($uploads);
}

echo json_encode(['ok' => true, 'ssid' => $ssid, 'uploads' => talk_upload_urls()], JSON_UNESCAPED_UNICODE);
