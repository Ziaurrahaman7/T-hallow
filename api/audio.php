<?php

$id = isset($_GET['id']) ? (string) $_GET['id'] : '';
if (!preg_match('/^u[a-f0-9]{16}$/', $id)) {
    http_response_code(404);
    exit;
}

$file = dirname(__DIR__) . '/data/wav/' . $id . '.wav';
if (!is_file($file)) {
    http_response_code(404);
    exit;
}

header('Content-Type: audio/wav');
header('Content-Length: ' . filesize($file));
header('Cache-Control: private, max-age=86400');
readfile($file);
