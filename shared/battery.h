#pragma once

#include "battery_config.h"
#include <Arduino.h>

#if BATTERY_ADC_GPIO >= 0 || BATTERY_SCAN_PINS

static int g_batteryMv = 0;
static int g_batteryPct = 0;
static bool g_batteryUsb = false;
static int g_batteryPin = -1;

static const int kBatteryPinCandidates[] = {
    3, 1, 2, 10, 13, 8, 9, 21,
#if BATTERY_ADC_GPIO >= 0
    BATTERY_ADC_GPIO,
#endif
};

static int batteryReadPinMv(int gpio) {
  long sum = 0;
  analogSetPinAttenuation(gpio, ADC_11db);
  for (int i = 0; i < 6; i++) {
    sum += analogReadMilliVolts(gpio);
    delay(1);
  }
  return (int)(sum / 6);
}

static int batteryEstimatePackMv(int adcPinMv) {
  if (adcPinMv <= 0) return 0;
  int mvDiv2 = adcPinMv * BATTERY_ADC_DIV;
  if (mvDiv2 >= 2800 && mvDiv2 <= 4450) return mvDiv2;
  if (adcPinMv >= 2800 && adcPinMv <= 4450) return adcPinMv;
  return 0;
}

static int batteryPctFromMv(int mv) {
  if (mv >= BATTERY_MV_FULL) return 100;
  if (mv <= BATTERY_MV_EMPTY) return 0;
  return (int)((long)(mv - BATTERY_MV_EMPTY) * 100L / (BATTERY_MV_FULL - BATTERY_MV_EMPTY));
}

static void batteryInit(void) {
  for (size_t i = 0; i < sizeof(kBatteryPinCandidates) / sizeof(kBatteryPinCandidates[0]); i++) {
    int p = kBatteryPinCandidates[i];
    if (p < 0) continue;
    pinMode(p, INPUT);
  }
}

static void batterySample(void) {
  int bestMv = 0;
  int bestPin = -1;

#if BATTERY_SCAN_PINS
  for (size_t i = 0; i < sizeof(kBatteryPinCandidates) / sizeof(kBatteryPinCandidates[0]); i++) {
    int p = kBatteryPinCandidates[i];
    if (p < 0) continue;
    int adc = batteryReadPinMv(p);
    int pack = batteryEstimatePackMv(adc);
    if (pack > bestMv) {
      bestMv = pack;
      bestPin = p;
    }
  }
#else
  int adc = batteryReadPinMv(BATTERY_ADC_GPIO);
  bestMv = batteryEstimatePackMv(adc);
  bestPin = BATTERY_ADC_GPIO;
#endif

  g_batteryPin = bestPin;
  g_batteryUsb = (bestMv > 4450);

  if (bestMv < 2600) {
    g_batteryMv = 0;
    g_batteryPct = 0;
    g_batteryUsb = false;
    return;
  }

  if (g_batteryUsb) {
    g_batteryMv = bestMv;
    g_batteryPct = g_batteryPct > 0 ? g_batteryPct : 100;
    return;
  }

  g_batteryMv = bestMv;
  g_batteryPct = batteryPctFromMv(bestMv);
}

static inline int batteryGetMv(void) { return g_batteryMv; }
static inline int batteryGetPct(void) { return g_batteryPct; }
static inline int batteryGetUsb(void) { return g_batteryUsb ? 1 : 0; }
static inline int batteryGetPin(void) { return g_batteryPin; }

#else

static inline void batteryInit(void) {}
static inline void batterySample(void) {}
static inline int batteryGetMv(void) { return 0; }
static inline int batteryGetPct(void) { return 0; }
static inline int batteryGetUsb(void) { return 0; }
static inline int batteryGetPin(void) { return -1; }

#endif
