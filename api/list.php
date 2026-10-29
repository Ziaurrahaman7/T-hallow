<?php

require_once dirname(__DIR__) . '/lib/store.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$items = talk_list();
$cfg = talk_config();
echo json_encode([
    'utterances' => $items,
    'upload_url' => $cfg['upload_url'],
], JSON_UNESCAPED_UNICODE);
