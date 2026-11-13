// Push-to-talk: momentary button between PTT_GPIO and GND (INPUT_PULLUP, active LOW).
// Same wiring on AP and STA boards.
#ifndef PTT_CONFIG_H
#define PTT_CONFIG_H

#include "voice_mode.h"
#include <Arduino.h>

#ifndef PTT_GPIO
#define PTT_GPIO 14
#endif

// 1 = HaLow TX only while button held; 0 = auto VAD (গুঞ্জনে TX কাটতে পারে — শুধু টেস্ট).
#ifndef PTT_REQUIRED
#define PTT_REQUIRED 1
#endif

// Walkie-style: needs PTT_REQUIRED 1.
#ifndef PTT_HALF_DUPLEX
#define PTT_HALF_DUPLEX 1
#endif

#ifndef PLAY_GAIN_NUM
#define PLAY_GAIN_NUM 14
#endif
#ifndef PLAY_GAIN_DEN
#define PLAY_GAIN_DEN 8
#endif

// Speaker volume: momentary — one leg IO, other GND (same as PTT). STA/AP both.
#ifndef VOL_UP_GPIO
#define VOL_UP_GPIO 12
#endif
#ifndef VOL_DOWN_GPIO
#define VOL_DOWN_GPIO 13
#endif
#ifndef VOL_BUTTONS
#define VOL_BUTTONS 1
#endif

// Auto TX + soft playback gates (less sa-sa / noise). Production PTT: set 0.
#ifndef VOICE_TEST_AUTO
#define VOICE_TEST_AUTO 1
#endif

#ifndef VOICE_PLAY_CLEAR
#define VOICE_PLAY_CLEAR 1
#endif

static uint8_t playGainNum = PLAY_GAIN_NUM;
static const uint8_t playGainDen = PLAY_GAIN_DEN;

static inline int16_t playSample(int16_t s) {
  int32_t v = ((int32_t)s * playGainNum) / playGainDen;
  if (v > 32767) v = 32767;
  if (v < -32768) v = -32768;
  return (int16_t)v;
}

static inline void pttBegin() {
  pinMode(PTT_GPIO, INPUT_PULLUP);
#if VOL_BUTTONS
  pinMode(VOL_UP_GPIO, INPUT_PULLUP);
  pinMode(VOL_DOWN_GPIO, INPUT_PULLUP);
#endif
}

#if VOL_BUTTONS
static inline void volButtonsPoll() {
  static uint32_t lastUp = 0;
  static uint32_t lastDn = 0;
  uint32_t now = millis();
  if (digitalRead(VOL_UP_GPIO) == LOW && now - lastUp > 280) {
    lastUp = now;
    if (playGainNum < 12) playGainNum++;
  }
  if (digitalRead(VOL_DOWN_GPIO) == LOW && now - lastDn > 280) {
    lastDn = now;
    if (playGainNum > 3) playGainNum--;
  }
}
#else
static inline void volButtonsPoll() {}
#endif

/** Physical PTT only (ignored when PTT_REQUIRED 0). */
static inline bool pttPhysicalDown() {
#if PTT_REQUIRED
  return digitalRead(PTT_GPIO) == LOW;
#else
  return false;
#endif
}

/** Serial log: 1 = button pressed. Auto mode always 0. */
static inline bool pttPressed() {
  return pttPhysicalDown();
}

// Mic level (c0/c1) != voice gate. PTT held: reject weak env noise; real speech only.
static inline bool pttShouldSend(int peak, int nStrong, bool shaped, uint32_t voicedMs, uint32_t nowMs) {
#if VOICE_SIMPLE
  (void)shaped;
  (void)voicedMs;
  (void)nowMs;
  return peak >= VOICE_TX_PEAK_MIN && nStrong >= VOICE_TX_STRONG_MIN;
#elif PTT_REQUIRED
  if (!pttPressed()) return false;
  if (shaped) return true;
  if (peak >= 320 && nStrong >= 3) return true;
  if (peak >= 400 && nStrong >= 5) return true;
  if (voicedMs != 0 && (nowMs - voicedMs) < 700 && peak >= 280 && nStrong >= 4) return true;
  return false;
#else
  (void)nowMs;
#if VOICE_TEST_AUTO
  (void)voicedMs;
  if (!shaped && peak < VOICE_AUTO_TX_PEAK) return false;
  if (shaped && peak >= VOICE_AUTO_SHAPED_PEAK && nStrong >= VOICE_AUTO_STRONG_MIN) return true;
  if (peak >= (VOICE_AUTO_TX_PEAK + 80) && nStrong >= (VOICE_AUTO_TX_STRONG + 2)) return true;
  return false;
#else
  (void)nStrong;
  return shaped || (voicedMs != 0 && (nowMs - voicedMs) < 900 && peak >= 300);
#endif
#endif
}

#endif
