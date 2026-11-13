<?php

require_once dirname(__DIR__) . '/lib/store.php';

talk_require_web();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo '{"ok":false}';
    exit;
}

$raw = file_get_contents('php://input');
$body = json_decode($raw ?: '', true);
if (!is_array($body)) {
    http_response_code(400);
    echo '{"ok":false}';
    exit;
}

$all = !empty($body['all']);
$ids = isset($body['ids']) && is_array($body['ids']) ? $body['ids'] : [];
if (!$all && !$ids) {
    http_response_code(400);
    echo '{"ok":false}';
    exit;
}

$removed = talk_clear($all, $ids);
echo json_encode(['ok' => true, 'removed' => $removed]);
