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
