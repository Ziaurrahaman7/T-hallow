#pragma once

#include <driver/i2s_std.h>
#include <string.h>

#ifndef SPEAKER_BEEP_AT_BOOT
#define SPEAKER_BEEP_AT_BOOT 1
#endif

// step = phase inc per sample @ 8kHz (73400 ≈ 880 Hz)
static inline bool speakerBeepTone(i2s_chan_handle_t spk, uint32_t step, int amplitude, int ms) {
  if (spk == nullptr) return false;
  if (amplitude < 1000) amplitude = 1000;
  if (amplitude > 28000) amplitude = 28000;
  if (i2s_channel_enable(spk) != ESP_OK) return false;

  const int rate = 8000;
  if (ms < 100) ms = 100;
  if (ms > 2000) ms = 2000;
  const int total = (rate * ms) / 1000;
  uint32_t phase = 0;

  int16_t stereo[256 * 2];
  int pos = 0;
  while (pos < total) {
    int chunk = total - pos;
    if (chunk > 256) chunk = 256;
    for (int i = 0; i < chunk; i++) {
      int idx = pos + i;
      int32_t env = 1000;
      if (idx < 80) env = (idx * 1000) / 80;
      else if (idx > total - 80) env = ((total - idx) * 1000) / 80;
      phase += step;
      int32_t v = (phase & 0x80000000UL) ? amplitude : -amplitude;
      v = (v * env) / 1000;
      if (v > 32767) v = 32767;
      if (v < -32768) v = -32768;
      int16_t s = (int16_t)v;
      stereo[i * 2] = s;
      stereo[i * 2 + 1] = s;
    }
    size_t written = 0;
    i2s_channel_write(spk, stereo, (size_t)chunk * 2 * sizeof(int16_t), &written, 400);
    pos += chunk;
  }

  memset(stereo, 0, 128);
  size_t written = 0;
  i2s_channel_write(spk, stereo, 128, &written, 80);
  i2s_channel_disable(spk);
  return true;
}

static inline bool speakerBootBeep(i2s_chan_handle_t spk) {
#if !SPEAKER_BEEP_AT_BOOT
  (void)spk;
  return false;
#endif
  return speakerBeepTone(spk, 73400, 20000, 450);
}
