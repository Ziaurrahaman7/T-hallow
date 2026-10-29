<?php

require_once __DIR__ . '/adpcm.php';

function talk_config(): array
{
    return [
        'token' => 'hallow',
        'upload_url' => 'http://192.168.0.101/T-hallow/api/upload.php',
    ];
}

function talk_paths(): array
{
    $root = dirname(__DIR__) . '/data';
    return [
        'root' => $root,
        'index' => $root . '/index.json',
        'wav' => $root . '/wav',
    ];
}

function talk_bangla_day(int $unix): string
{
    $months = [
        1 => 'জানুয়ারি', 2 => 'ফেব্রুয়ারি', 3 => 'মার্চ', 4 => 'এপ্রিল',
        5 => 'মে', 6 => 'জুন', 7 => 'জুলাই', 8 => 'আগস্ট',
        9 => 'সেপ্টেম্বর', 10 => 'অক্টোবর', 11 => 'নভেম্বর', 12 => 'ডিসেম্বর',
    ];
    $m = (int) date('n', $unix);
    return date('j', $unix) . ' ' . $months[$m] . ' ' . date('Y', $unix);
}

function talk_save_batch(string $board, int $boardNow, array $clips): int
{
    date_default_timezone_set('Asia/Dhaka');
    $paths = talk_paths();
    if (!is_dir($paths['wav'])) {
        mkdir($paths['wav'], 0777, true);
    }

    $nowMs = (int) round(microtime(true) * 1000);
    $decoded = [];
    foreach ($clips as $clip) {
        if (!is_array($clip) || !isset($clip['t'], $clip['b'])) {
            continue;
        }
        $raw = base64_decode((string) $clip['b'], true);
        if ($raw === false) {
            continue;
        }
        $pcm = adpcm_unpack($raw);
        if ($pcm === null) {
            continue;
        }
        $t = (int) $clip['t'];
        if ($t < 0) {
            $t = 0;
        }
        if ($boardNow > 0 && $t > $boardNow) {
            $t = $boardNow;
        }
        $who = $board;
        if (isset($clip['w']) && ($clip['w'] === 'AP' || $clip['w'] === 'STA')) {
            $who = $clip['w'];
        }
        $decoded[] = ['t' => $t, 'pcm' => $pcm, 'who' => $who];
    }
    if (!$decoded) {
        return 0;
    }
    usort($decoded, function ($a, $b) {
        return $a['t'] <=> $b['t'];
    });

    $groups = [];
    $cur = null;
    foreach ($decoded as $item) {
        if ($cur === null || $item['who'] !== $cur['who'] || ($item['t'] - $cur['last']) > 500) {
            if ($cur !== null) {
                $groups[] = $cur;
            }
            $cur = [
                'start' => $item['t'],
                'last' => $item['t'],
                'who' => $item['who'],
                'pcm' => $item['pcm'],
            ];
            continue;
        }
        $gapMs = $item['t'] - $cur['last'];
        $silence = (int) round(($gapMs - 40) * 8);
        if ($silence > 8 && $silence < 3600) {
            $cur['pcm'] = array_merge($cur['pcm'], array_fill(0, $silence, 0));
        }
        $cur['pcm'] = array_merge($cur['pcm'], $item['pcm']);
        $cur['last'] = $item['t'];
    }
    if ($cur !== null) {
        $groups[] = $cur;
    }

    $made = [];
    foreach ($groups as $group) {
        $id = 'u' . bin2hex(random_bytes(8));
        $wav = wav_from_pcm($group['pcm']);
        $file = $paths['wav'] . '/' . $id . '.wav';
        if (file_put_contents($file, $wav) === false) {
            continue;
        }
        $age = $boardNow > $group['start'] ? ($boardNow - $group['start']) : 0;
        $wall = $nowMs - $age;
        $unix = (int) floor($wall / 1000);
        $sec = round(count($group['pcm']) / 8000, 2);
        $made[] = [
            'id' => $id,
            'board' => $group['who'],
            'ms' => $wall,
            'at' => date('H:i:s', $unix),
            'day' => talk_bangla_day($unix),
            'sec' => $sec,
        ];
    }
    if (!$made) {
        return 0;
    }

    $fp = fopen($paths['index'], 'c+');
    if ($fp === false) {
        return 0;
    }
    flock($fp, LOCK_EX);
    $raw = stream_get_contents($fp);
    $data = json_decode($raw ?: '', true);
    if (!is_array($data) || !isset($data['utterances']) || !is_array($data['utterances'])) {
        $data = ['utterances' => []];
    }
    foreach ($made as $row) {
        $data['utterances'][] = $row;
    }
    usort($data['utterances'], function ($a, $b) {
        return ($a['ms'] <=> $b['ms']);
    });
    while (count($data['utterances']) > 400) {
        $old = array_shift($data['utterances']);
        if (isset($old['id'])) {
            $oldFile = $paths['wav'] . '/' . $old['id'] . '.wav';
            if (is_file($oldFile)) {
                unlink($oldFile);
            }
        }
    }
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, $json === false ? '{"utterances":[]}' : $json);
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);
    return count($made);
}

function talk_list(): array
{
    $paths = talk_paths();
    if (!is_file($paths['index'])) {
        return [];
    }
    $data = json_decode((string) file_get_contents($paths['index']), true);
    if (!is_array($data) || !isset($data['utterances']) || !is_array($data['utterances'])) {
        return [];
    }
    return $data['utterances'];
}
