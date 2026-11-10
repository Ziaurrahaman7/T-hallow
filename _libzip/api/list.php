<?php

require_once dirname(__DIR__) . '/lib/store.php';

talk_require_web();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$items = talk_list();
$cfg = talk_config();
echo json_encode([
    'utterances' => $items,
    'upload_url' => $cfg['upload_url'],
    'upload_urls' => talk_upload_urls(),
], JSON_UNESCAPED_UNICODE);
