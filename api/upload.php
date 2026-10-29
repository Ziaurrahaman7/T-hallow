<?php

require_once dirname(__DIR__) . '/lib/store.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo '{"ok":false}';
    exit;
}

$raw = file_get_contents('php://input');
if ($raw === false || strlen($raw) > 200000) {
    http_response_code(413);
    echo '{"ok":false}';
    exit;
}

$body = json_decode($raw, true);
$cfg = talk_config();
$token = is_array($body) && isset($body['token']) ? (string) $body['token'] : '';
if (!is_array($body) || !hash_equals($cfg['token'], $token)) {
    http_response_code(403);
    echo '{"ok":false}';
    exit;
}

$board = isset($body['board']) ? (string) $body['board'] : '';
if ($board !== 'AP' && $board !== 'STA') {
    http_response_code(400);
    echo '{"ok":false}';
    exit;
}

$clips = isset($body['clips']) && is_array($body['clips']) ? $body['clips'] : [];
if (count($clips) > 80) {
    $clips = array_slice($clips, 0, 80);
}

$saved = talk_save_batch($board, (int) ($body['millis'] ?? 0), $clips);
echo json_encode(['ok' => true, 'saved' => $saved]);
