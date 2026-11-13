#pragma once

// T-Halow: BAT ADC on GPIO3 (from live reports). Do not scan other pins — can disturb Wi-Fi.
#ifndef BATTERY_ADC_GPIO
#define BATTERY_ADC_GPIO 3
#endif

#ifndef BATTERY_SCAN_PINS
#define BATTERY_SCAN_PINS 0
#endif

#ifndef BATTERY_ADC_DIV
#define BATTERY_ADC_DIV 2
#endif

#ifndef BATTERY_MV_FULL
#define BATTERY_MV_FULL 4200
#endif

#ifndef BATTERY_MV_EMPTY
#define BATTERY_MV_EMPTY 3200
#endif

#ifndef BATTERY_CAPACITY_MAH
#define BATTERY_CAPACITY_MAH 3000
#endif

#ifndef BATTERY_AVG_MA
#define BATTERY_AVG_MA 180
#endif
