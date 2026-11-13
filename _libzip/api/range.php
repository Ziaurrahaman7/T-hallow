<?php

require_once dirname(__DIR__) . '/lib/range.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

talk_require_web();

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    echo json_encode(['ok' => true] + talk_range_snapshot(), JSON_UNESCAPED_UNICODE);
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

$action = (string) ($body['action'] ?? '');

if ($action === 'user_geo') {
    $lat = isset($body['lat']) && is_numeric($body['lat']) ? (float) $body['lat'] : null;
    $lon = isset($body['lon']) && is_numeric($body['lon']) ? (float) $body['lon'] : null;
    if ($lat === null || $lon === null || ! talk_geo_valid($lat, $lon)) {
        http_response_code(400);
        echo '{"ok":false,"err":"geo"}';
        exit;
    }
    $acc = isset($body['acc']) && is_numeric($body['acc']) ? (float) $body['acc'] : null;
    talk_user_geo_save($lat, $lon, $acc);
    echo json_encode(['ok' => true] + talk_range_snapshot(), JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'save_sites') {
    $patch = [];
    foreach (['site_ap', 'site_sta'] as $key) {
        if (! isset($body[$key]) || ! is_array($body[$key])) {
            continue;
        }
        $row = $body[$key];
        $patch[$key] = [];
        if (array_key_exists('lat', $row)) {
            $patch[$key]['lat'] = is_numeric($row['lat']) ? (float) $row['lat'] : null;
        }
        if (array_key_exists('lon', $row)) {
            $patch[$key]['lon'] = is_numeric($row['lon']) ? (float) $row['lon'] : null;
        }
        if (isset($row['label'])) {
            $patch[$key]['label'] = trim((string) $row['label']);
        }
    }
    talk_range_write($patch);
    echo json_encode(['ok' => true] + talk_range_snapshot(), JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'use_user_for_both') {
    $user = talk_user_geo_from_session();
    if ($user === null) {
        http_response_code(400);
        echo '{"ok":false,"err":"no_user_geo"}';
        exit;
    }
    $label = trim((string) ($body['label'] ?? 'মাঠ'));
    talk_range_write([
        'site_ap' => ['lat' => $user['lat'], 'lon' => $user['lon'], 'label' => $label . ' · AP'],
        'site_sta' => ['lat' => $user['lat'], 'lon' => $user['lon'], 'label' => $label . ' · STA'],
    ]);
    echo json_encode(['ok' => true] + talk_range_snapshot(), JSON_UNESCAPED_UNICODE);
    exit;
}

http_response_code(400);
echo '{"ok":false}';
