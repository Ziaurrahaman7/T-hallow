<?php

function adpcm_tables(): array
{
    return [
        'step' => [
            7, 8, 9, 10, 11, 12, 13, 14, 16, 17,
            19, 21, 23, 25, 28, 31, 34, 37, 41, 45,
            50, 55, 60, 66, 73, 80, 88, 97, 107, 118,
            130, 143, 157, 173, 190, 209, 230, 253, 279, 307,
            337, 371, 408, 449, 494, 544, 598, 658, 724, 796,
            876, 963, 1060, 1166, 1282, 1411, 1552, 1707, 1878, 2066,
            2272, 2499, 2749, 3024, 3327, 3660, 4026, 4428, 4871, 5358,
            5894, 6484, 7132, 7845, 8630, 9493, 10442, 11487, 12635, 13899,
            15289, 16818, 18500, 20350, 22385, 24623, 27086, 29794, 32767,
        ],
        'index' => [-1, -1, -1, -1, 2, 4, 6, 8, -1, -1, -1, -1, 2, 4, 6, 8],
    ];
}

function adpcm_step(int $nibble, int &$pred, int &$index): int
{
    static $tables = null;
    if ($tables === null) {
        $tables = adpcm_tables();
    }
    $step = $tables['step'][$index];
    $diffq = $step >> 3;
    if ($nibble & 4) {
        $diffq += $step;
    }
    if ($nibble & 2) {
        $diffq += $step >> 1;
    }
    if ($nibble & 1) {
        $diffq += $step >> 2;
    }
    if ($nibble & 8) {
        $pred -= $diffq;
    } else {
        $pred += $diffq;
    }
    if ($pred > 32767) {
        $pred = 32767;
    }
    if ($pred < -32768) {
        $pred = -32768;
    }
    $index += $tables['index'][$nibble & 15];
    if ($index < 0) {
        $index = 0;
    }
    if ($index > 88) {
        $index = 88;
    }
    return $pred;
}

function adpcm_unpack(string $in): ?array
{
    if (strlen($in) < 163) {
        return null;
    }
    $index = ord($in[2]);
    if ($index > 88) {
        return null;
    }
    $pred = ord($in[0]) | (ord($in[1]) << 8);
    if ($pred >= 32768) {
        $pred -= 65536;
    }
    $pcm = [$pred];
    $o = 3;
    while ($o < 163 && count($pcm) + 1 < 321) {
        $b = ord($in[$o]);
        $o++;
        $pcm[] = adpcm_step($b >> 4, $pred, $index);
        $pcm[] = adpcm_step($b & 0x0F, $pred, $index);
    }
    if (count($pcm) !== 321) {
        return null;
    }
    return $pcm;
}

function wav_from_pcm(array $samples): string
{
    $pcm = '';
    foreach ($samples as $s) {
        $s = (int) $s;
        if ($s > 32767) {
            $s = 32767;
        }
        if ($s < -32768) {
            $s = -32768;
        }
        $pcm .= pack('v', $s & 0xFFFF);
    }
    $dataLen = strlen($pcm);
    $hdr = 'RIFF';
    $hdr .= pack('V', 36 + $dataLen);
    $hdr .= 'WAVEfmt ';
    $hdr .= pack('V', 16);
    $hdr .= pack('v', 1);
    $hdr .= pack('v', 1);
    $hdr .= pack('V', 8000);
    $hdr .= pack('V', 16000);
    $hdr .= pack('v', 2);
    $hdr .= pack('v', 16);
    $hdr .= 'data';
    $hdr .= pack('V', $dataLen);
    return $hdr . $pcm;
}
