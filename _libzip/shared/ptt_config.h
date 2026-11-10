// Push-to-talk: momentary button between PTT_GPIO and GND (INPUT_PULLUP, active LOW).
// Same wiring on AP and STA boards.
#ifndef PTT_CONFIG_H
#define PTT_CONFIG_H

#include <Arduino.h>

#ifndef PTT_GPIO
#define PTT_GPIO 14
#endif

// 1 = HaLow TX only while button held; 0 = legacy auto VAD (no button).
#ifndef PTT_REQUIRED
#define PTT_REQUIRED 1
#endif

// Walkie-style: no speaker while you transmit; no mic TX while remote plays (reduces echo).
#ifndef PTT_HALF_DUPLEX
#define PTT_HALF_DUPLEX 1
#endif

#ifndef PLAY_GAIN_NUM
#define PLAY_GAIN_NUM 7
#endif
#ifndef PLAY_GAIN_DEN
#define PLAY_GAIN_DEN 8
#endif

static inline int16_t playSample(int16_t s) {
  int32_t v = ((int32_t)s * PLAY_GAIN_NUM) / PLAY_GAIN_DEN;
  if (v > 32767) v = 32767;
  if (v < -32768) v = -32768;
  return (int16_t)v;
}

static inline void pttBegin() {
#if PTT_REQUIRED
  pinMode(PTT_GPIO, INPUT_PULLUP);
#endif
}

static inline bool pttPressed() {
#if PTT_REQUIRED
  return digitalRead(PTT_GPIO) == LOW;
#else
  return true;
#endif
}

// Mic OK (c0/c1) != voice gate. With PTT held, use a lower bar so talk gets through.
static inline bool pttShouldSend(int peak, int nStrong, bool shaped, uint32_t voicedMs, uint32_t nowMs) {
#if PTT_REQUIRED
  if (!pttPressed()) return false;
  if (shaped) return true;
  if (peak >= 200) return true;
  if (nStrong >= 3 && peak >= 120) return true;
  if (voicedMs != 0 && (nowMs - voicedMs) < 900 && peak >= 100) return true;
  return false;
#else
  (void)nStrong;
  return shaped || (voicedMs != 0 && (nowMs - voicedMs) < 900 && peak >= 300);
#endif
}

#endif
