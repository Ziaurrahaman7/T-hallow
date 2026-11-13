<?php
$path = $argv[1] ?? '';
if ($path === '' || !is_file($path)) {
    fwrite(STDERR, "usage: php pdfgrep.php <file.pdf>\n");
    exit(1);
}
$raw = (string) file_get_contents($path);
preg_match_all('/[\x20-\x7E]{6,240}/', $raw, $m);
$hits = [];
foreach ($m[0] as $s) {
    if (preg_match('/range|meter|metre| kilomet|distance|coverage|802\.11|HaLow|communicat|transmit|dBm|\bkm\b|\bm\b|Wi.?Fi|915|928|sub.?GHz/i', $s)) {
        $hits[$s] = true;
    }
}
echo implode("\n", array_keys($hits));
