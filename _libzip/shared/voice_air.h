#pragma once

#include <Arduino.h>
#include <string.h>

#ifndef VOICE_AIR_PAYLOAD
#define VOICE_AIR_PAYLOAD 163
#endif

static const uint8_t VOICE_AIR_MAGIC4[4] = {0xA5, 0x5A, 0xA5, 0x5A};
static const uint8_t VOICE_AIR_TAG_NEW = 0xF0;

struct VoiceAirRx {
  int matched = 0;
  int phase = 0;  // 0 hunt4, 1 post4, 2 seq(new), 3 body
  bool newFmt = false;
  uint8_t seq = 0;
  int bodyFill = 0;
  uint8_t body[VOICE_AIR_PAYLOAD];
  uint32_t lastPktMs = 0;
  uint8_t recentSeq[12];
  uint32_t recentMs[12];
  int recentN = 0;
  uint8_t lastBody[VOICE_AIR_PAYLOAD];
  bool haveLastBody = false;
  uint32_t lastBodyMs = 0;
};

static inline void voiceAirRxReset(VoiceAirRx* r) {
  r->matched = 0;
  r->phase = 0;
  r->bodyFill = 0;
}

static inline void voiceAirRxClearDedup(VoiceAirRx* r) {
  r->recentN = 0;
  r->haveLastBody = false;
}

static inline bool voiceAirSeqDup(VoiceAirRx* r, uint8_t seq) {
  uint32_t now = millis();
  for (int i = 0; i < r->recentN; i++) {
    if (r->recentSeq[i] == seq && (now - r->recentMs[i]) < 2500) return true;
  }
  return false;
}

static inline void voiceAirSeqMark(VoiceAirRx* r, uint8_t seq) {
  uint32_t now = millis();
  if (r->recentN < 12) {
    r->recentSeq[r->recentN] = seq;
    r->recentMs[r->recentN] = now;
    r->recentN++;
  } else {
    memmove(r->recentSeq, r->recentSeq + 1, 11);
    memmove(r->recentMs, r->recentMs + 1, 11 * sizeof(uint32_t));
    r->recentSeq[11] = seq;
    r->recentMs[11] = now;
  }
}

static inline bool voiceAirBodyDup(VoiceAirRx* r) {
  if (!r->haveLastBody) return false;
  if (memcmp(r->lastBody, r->body, VOICE_AIR_PAYLOAD) != 0) return false;
  return (millis() - r->lastBodyMs) < 2500;
}

static inline void voiceAirBodyMark(VoiceAirRx* r) {
  memcpy(r->lastBody, r->body, VOICE_AIR_PAYLOAD);
  r->haveLastBody = true;
  r->lastBodyMs = millis();
}

// ADPCM voice block sanity (drops UART/HaLow false sync → cir-cir noise).
static inline bool voiceAirPayloadOk(const uint8_t* p) {
  if (p[2] > 88) return false;
  int varied = 0;
  uint8_t prev = p[0];
  for (int i = 0; i < 32; i++) {
    if (p[i] != prev) varied++;
    if (p[i] != 0xFF && p[i] != 0x00) varied++;
  }
  if (varied < 3) return false;
  return true;
}

static inline bool voiceAirFinish(VoiceAirRx* r, bool* ready) {
  *ready = false;
  uint32_t now = millis();
  if (r->lastPktMs != 0 && (now - r->lastPktMs) > 450) {
    voiceAirRxClearDedup(r);
  }
  r->lastPktMs = now;

  if (!voiceAirPayloadOk(r->body)) {
    return false;
  }
  if (r->newFmt && voiceAirSeqDup(r, r->seq)) {
    return false;
  }
  if (voiceAirBodyDup(r)) {
    return false;
  }
  if (r->newFmt) voiceAirSeqMark(r, r->seq);
  voiceAirBodyMark(r);
  *ready = true;
  return true;
}

static inline bool voiceAirFeed(VoiceAirRx* r, uint8_t b, bool* ready) {
  *ready = false;
  if (r->phase == 0) {
    if (b == VOICE_AIR_MAGIC4[r->matched]) {
      r->matched++;
      if (r->matched == 4) {
        r->matched = 0;
        r->phase = 1;
      }
    } else {
      r->matched = (b == VOICE_AIR_MAGIC4[0]) ? 1 : 0;
    }
    return false;
  }

  if (r->phase == 1) {
    if (b == VOICE_AIR_TAG_NEW) {
      r->newFmt = true;
      r->phase = 2;
    } else {
      r->newFmt = false;
      r->body[0] = b;
      r->bodyFill = 1;
      r->phase = 3;
    }
    return false;
  }

  if (r->phase == 2) {
    r->seq = b;
    r->bodyFill = 0;
    r->phase = 3;
    return false;
  }

  r->body[r->bodyFill++] = b;
  if (r->bodyFill < VOICE_AIR_PAYLOAD) {
    return false;
  }

  r->phase = 0;
  r->bodyFill = 0;
  return voiceAirFinish(r, ready);
}

static inline int voiceAirBuildFrame(uint8_t* frame, int headerSkip, const uint8_t* adpcm,
                                     int payloadLen) {
  static uint8_t txSeq = 0;
  memcpy(frame + headerSkip, VOICE_AIR_MAGIC4, 4);
  frame[headerSkip + 4] = VOICE_AIR_TAG_NEW;
  frame[headerSkip + 5] = txSeq++;
  memcpy(frame + headerSkip + 6, adpcm, (size_t)payloadLen);
  return headerSkip + 6 + payloadLen;
}
