<?php

require_once dirname(__DIR__) . '/lib/store.php';

function fw_dir(): string
{
    return talk_paths()['root'] . '/fw';
}

function fw_meta_file(): string
{
    return talk_paths()['root'] . '/fw.json';
}

function fw_blank(): array
{
    return ['version' => 0, 'size' => 0, 'published' => false, 'md5' => '', 'at' => ''];
}

function fw_read(): array
{
    $out = ['AP' => fw_blank(), 'STA' => fw_blank()];
    $raw = @file_get_contents(fw_meta_file());
    $data = json_decode($raw ?: '', true);
    if (!is_array($data)) {
        return $out;
    }
    foreach (['AP', 'STA'] as $board) {
        if (!isset($data[$board]) || !is_array($data[$board])) {
            continue;
        }
        $row = $data[$board];
        $out[$board] = [
            'version' => (int) ($row['version'] ?? 0),
            'size' => (int) ($row['size'] ?? 0),
            'published' => !empty($row['published']),
            'md5' => (string) ($row['md5'] ?? ''),
            'at' => (string) ($row['at'] ?? ''),
        ];
    }
    return $out;
}

function fw_write(array $meta): void
{
    $dir = talk_paths()['root'];
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    file_put_contents(
        fw_meta_file(),
        json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
    );
}

function fw_bin(string $board): string
{
    return fw_dir() . '/' . $board . '.bin';
}

function fw_public_url(string $board): string
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    $scheme = $https ? 'https' : 'http';
    $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
    $script = (string) ($_SERVER['SCRIPT_NAME'] ?? '/api/fw.php');
    $token = talk_config()['token'];
    return $scheme . '://' . $host . $script
        . '?token=' . rawurlencode($token)
        . '&board=' . rawurlencode($board)
        . '&bin=1';
}

function fw_board(): string
{
    $board = strtoupper((string) ($_GET['board'] ?? $_POST['board'] ?? ''));
    if ($board !== 'AP' && $board !== 'STA') {
        http_response_code(400);
        header('Content-Type: application/json; charset=utf-8');
        echo '{"ok":false}';
        exit;
    }
    return $board;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['token'])) {
    $cfg = talk_config();
    $token = (string) $_GET['token'];
    if (!hash_equals($cfg['token'], $token)) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo '{"ok":false}';
        exit;
    }
    $board = fw_board();
    $meta = fw_read()[$board];
    $ver = (int) ($_GET['ver'] ?? 0);
    $ready = $meta['published'] && $meta['version'] > $ver && is_file(fw_bin($board));
    if (isset($_GET['bin'])) {
        if (!$meta['published'] || !is_file(fw_bin($board)) || $ver >= $meta['version']) {
            http_response_code(404);
            header('Content-Type: application/json; charset=utf-8');
            echo '{"ok":false}';
            exit;
        }
        $path = fw_bin($board);
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: application/octet-stream');
        header('Content-Length: ' . (string) filesize($path));
        header('Content-Encoding: identity');
        header('Cache-Control: no-store');
        readfile($path);
        exit;
    }
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    if (!$ready) {
        echo '{"ok":true,"update":0}';
        exit;
    }
    echo json_encode([
        'ok' => true,
        'update' => 1,
        'version' => $meta['version'],
        'md5' => $meta['md5'],
        'url' => fw_public_url($board),
    ], JSON_UNESCAPED_SLASHES);
    exit;
}

talk_require_web();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $meta = fw_read();
    $out = ['ok' => true];
    foreach (['AP', 'STA'] as $board) {
        $row = $meta[$board];
        $out[$board] = [
            'version' => $row['version'],
            'size' => $row['size'],
            'published' => $row['published'],
            'has' => is_file(fw_bin($board)),
            'at' => $row['at'],
        ];
    }
    echo json_encode($out, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo '{"ok":false}';
    exit;
}

$ctype = (string) ($_SERVER['CONTENT_TYPE'] ?? '');
if (stripos($ctype, 'application/json') !== false) {
    $body = json_decode((string) file_get_contents('php://input'), true);
    $board = strtoupper((string) (is_array($body) ? ($body['board'] ?? '') : ''));
    if ($board !== 'AP' && $board !== 'STA') {
        http_response_code(400);
        echo '{"ok":false}';
        exit;
    }
    $meta = fw_read();
    if (!is_file(fw_bin($board)) || $meta[$board]['version'] < 1) {
        http_response_code(400);
        echo '{"ok":false,"err":"file"}';
        exit;
    }
    $meta[$board]['published'] = true;
    $meta[$board]['at'] = date('Y-m-d H:i:s');
    fw_write($meta);
    echo '{"ok":true}';
    exit;
}

$board = strtoupper((string) ($_POST['board'] ?? ''));
$version = (int) ($_POST['version'] ?? 0);
if (($board !== 'AP' && $board !== 'STA') || $version < 1 || $version > 9999) {
    http_response_code(400);
    echo '{"ok":false}';
    exit;
}
if (!isset($_FILES['file']) || !is_array($_FILES['file']) || ($_FILES['file']['error'] ?? 1) !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo '{"ok":false,"err":"upload"}';
    exit;
}
$tmp = (string) $_FILES['file']['tmp_name'];
$size = (int) ($_FILES['file']['size'] ?? 0);
if ($size < 100000 || $size > 1800000) {
    http_response_code(400);
    echo '{"ok":false,"err":"size"}';
    exit;
}
$head = (string) file_get_contents($tmp, false, null, 0, 1);
if ($head !== "\xE9") {
    http_response_code(400);
    echo '{"ok":false,"err":"bin"}';
    exit;
}
$dir = fw_dir();
if (!is_dir($dir)) {
    mkdir($dir, 0777, true);
}
if (!move_uploaded_file($tmp, fw_bin($board))) {
    http_response_code(500);
    echo '{"ok":false}';
    exit;
}
$meta = fw_read();
$meta[$board] = [
    'version' => $version,
    'size' => $size,
    'published' => false,
    'md5' => md5_file(fw_bin($board)) ?: '',
    'at' => date('Y-m-d H:i:s'),
];
fw_write($meta);
echo '{"ok":true}';
