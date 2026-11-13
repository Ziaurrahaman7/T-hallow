<?php

require_once __DIR__ . '/boardnet.php';

function talk_range_file(): string
{
    return talk_paths()['root'] . '/range.json';
}

function talk_range_default(): array
{
    return [
        'site_ap' => ['lat' => null, 'lon' => null, 'label' => ''],
        'site_sta' => ['lat' => null, 'lon' => null, 'label' => ''],
    ];
}

function talk_range_read(): array
{
    $data = talk_range_default();
    $file = talk_range_file();
    if (! is_file($file)) {
        return $data;
    }
    $raw = json_decode((string) file_get_contents($file), true);
    if (! is_array($raw)) {
        return $data;
    }
    foreach (['site_ap', 'site_sta'] as $key) {
        if (! isset($raw[$key]) || ! is_array($raw[$key])) {
            continue;
        }
        $row = $raw[$key];
        $lat = $row['lat'] ?? null;
        $lon = $row['lon'] ?? null;
        $data[$key]['lat'] = is_numeric($lat) ? (float) $lat : null;
        $data[$key]['lon'] = is_numeric($lon) ? (float) $lon : null;
        $data[$key]['label'] = trim((string) ($row['label'] ?? ''));
    }

    return $data;
}

function talk_range_write(array $patch): array
{
    $data = talk_range_read();
    foreach (['site_ap', 'site_sta'] as $key) {
        if (! isset($patch[$key]) || ! is_array($patch[$key])) {
            continue;
        }
        $row = $patch[$key];
        if (array_key_exists('lat', $row)) {
            $lat = $row['lat'];
            $data[$key]['lat'] = is_numeric($lat) ? (float) $lat : null;
        }
        if (array_key_exists('lon', $row)) {
            $lon = $row['lon'];
            $data[$key]['lon'] = is_numeric($lon) ? (float) $lon : null;
        }
        if (isset($row['label'])) {
            $data[$key]['label'] = trim((string) $row['label']);
        }
    }
    $dir = talk_paths()['root'];
    if (! is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    file_put_contents(
        talk_range_file(),
        json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
    );

    return $data;
}

function talk_geo_valid(?float $lat, ?float $lon): bool
{
    if ($lat === null || $lon === null) {
        return false;
    }

    return $lat >= -90 && $lat <= 90 && $lon >= -180 && $lon <= 180 && ! ($lat == 0.0 && $lon == 0.0);
}

function talk_haversine_m(float $lat1, float $lon1, float $lat2, float $lon2): float
{
    $r = 6371000.0;
    $p1 = deg2rad($lat1);
    $p2 = deg2rad($lat2);
    $dp = deg2rad($lat2 - $lat1);
    $dl = deg2rad($lon2 - $lon1);
    $a = sin($dp / 2) ** 2 + cos($p1) * cos($p2) * sin($dl / 2) ** 2;

    return round(2 * $r * asin(min(1.0, sqrt($a))));
}

/** Rough HaLow 915 MHz link estimate from peer RSSI (dBm). */
function talk_halow_rssi_to_meters(int $rssiDbm): ?float
{
    if ($rssiDbm >= 0 || $rssiDbm < -115) {
        return null;
    }
    $at1m = -28.0;
    $n = 2.7;
    $exp = ($at1m - (float) $rssiDbm) / (10.0 * $n);

    return round(10 ** $exp, 1);
}

function talk_halow_link_meters(int $apRssi, int $staRssi): ?array
{
    $parts = [];
    if ($apRssi < 0 && $apRssi > -115) {
        $m = talk_halow_rssi_to_meters($apRssi);
        if ($m !== null) {
            $parts[] = $m;
        }
    }
    if ($staRssi < 0 && $staRssi > -115) {
        $m = talk_halow_rssi_to_meters($staRssi);
        if ($m !== null) {
            $parts[] = $m;
        }
    }
    if ($parts === []) {
        return null;
    }

    return [
        'meters' => round(array_sum($parts) / count($parts), 1),
        'ap_rssi' => $apRssi,
        'sta_rssi' => $staRssi,
    ];
}

function talk_user_geo_from_session(): ?array
{
    talk_session_start();
    if (! isset($_SESSION['talk_geo']) || ! is_array($_SESSION['talk_geo'])) {
        return null;
    }
    $g = $_SESSION['talk_geo'];
    $lat = isset($g['lat']) && is_numeric($g['lat']) ? (float) $g['lat'] : null;
    $lon = isset($g['lon']) && is_numeric($g['lon']) ? (float) $g['lon'] : null;
    if (! talk_geo_valid($lat, $lon)) {
        return null;
    }

    return [
        'lat' => $lat,
        'lon' => $lon,
        'at' => (int) ($g['at'] ?? 0),
        'acc' => isset($g['acc']) && is_numeric($g['acc']) ? (float) $g['acc'] : null,
    ];
}

function talk_user_geo_save(float $lat, float $lon, ?float $acc = null): void
{
    talk_session_start();
    $_SESSION['talk_geo'] = [
        'lat' => $lat,
        'lon' => $lon,
        'acc' => $acc,
        'at' => time(),
    ];
}

/**
 * @return array<string, mixed>
 */
function talk_range_snapshot(): array
{
    $boards = talk_boards_public();
    $sites = talk_range_read();
    $user = talk_user_geo_from_session();

    $apHr = (int) ($boards['AP']['halow_rssi'] ?? 0);
    $staHr = (int) ($boards['STA']['halow_rssi'] ?? 0);
    $link = talk_halow_link_meters($apHr, $staHr);

    $distUser = [];
    foreach (talk_board_ids() as $id) {
        $key = $id === 'AP' ? 'site_ap' : 'site_sta';
        $lat = $sites[$key]['lat'];
        $lon = $sites[$key]['lon'];
        $entry = [
            'board' => $id,
            'label' => talk_board_label($id),
            'site_label' => $sites[$key]['label'],
            'meters' => null,
            'site_set' => talk_geo_valid($lat, $lon),
        ];
        if ($user !== null && talk_geo_valid($lat, $lon)) {
            $entry['meters'] = talk_haversine_m($user['lat'], $user['lon'], $lat, $lon);
        }
        $distUser[$id] = $entry;
    }

    return [
        'boards' => $boards,
        'sites' => $sites,
        'user' => $user,
        'halow_link' => $link,
        'user_distance' => $distUser,
        'now' => time(),
    ];
}
