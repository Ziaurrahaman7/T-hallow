<?php

/** Typical 18650 for runtime hint (dashboard only). */
function talk_battery_capacity_mah(): int
{
    return 3000;
}

function talk_battery_avg_ma(): int
{
    return 180;
}

function talk_battery_runtime_minutes(int $pct): ?int
{
    if ($pct <= 0) {
        return null;
    }
    $cap = talk_battery_capacity_mah();
    $avg = talk_battery_avg_ma();
    if ($cap <= 0 || $avg <= 0) {
        return null;
    }

    return (int) round(($pct / 100.0) * $cap / $avg * 60.0);
}

function talk_battery_runtime_label_bn(?int $minutes): string
{
    if ($minutes === null || $minutes <= 0) {
        return '';
    }
    if ($minutes < 90) {
        return '~' . $minutes . ' মিনিট (আনুমানিক)';
    }
    $h = (int) floor($minutes / 60);
    $m = $minutes % 60;
    if ($m === 0) {
        return '~' . $h . ' ঘণ্টা (আনুমানিক)';
    }

    return '~' . $h . ' ঘ ' . $m . ' মি (আনুমানিক)';
}
