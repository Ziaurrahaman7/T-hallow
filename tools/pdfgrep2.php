<?php
$path = $argv[1] ?? '';
$words = ['km', 'meter', 'metre', 'distance', 'range', 'coverage', '120', '1000', '500', 'maximum', 'communicat', '802.11', 'HaLow', 'sub-GHz'];
$raw = (string) file_get_contents($path);
$low = strtolower($raw);
foreach ($words as $w) {
    $c = substr_count($low, strtolower($w));
    if ($c > 0) {
        echo "$w: $c\n";
    }
}
// UTF-16 BE streams often in PDF
if (preg_match_all('/\(([^\\)]{4,120})\)/', $raw, $m)) {
    foreach ($m[1] as $s) {
        if (preg_match('/range|meter|km|distance|802|HaLow|communic|maximum|120/i', $s)) {
            echo "TXT: $s\n";
        }
    }
}
