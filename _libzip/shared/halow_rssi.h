#pragma once

#include <Arduino.h>

// HaLow peer RSSI (dBm), updated from main loop when voice is idle. 0 = not yet measured.
static volatile int g_halowPeerRssi = 0;

static int halowParseRssiLine(const char* line) {
  if (line == nullptr || line[0] == 0) return 0;
  const char* p = line;
  while (*p) {
    if (*p == '-' && p[1] >= '0' && p[1] <= '9') {
      int v = (int)strtol(p, nullptr, 10);
      if (v <= -10 && v >= -120) return v;
    }
    if (*p >= '0' && *p <= '9' && p > line && p[-1] != '-' && p[-1] != ':') {
      int v = (int)strtol(p, nullptr, 10);
      if (v <= -10 && v >= -120) return v;
    }
    p++;
  }
  return 0;
}

typedef void (*HalowUartFeedFn)(uint8_t);

static void halowPollPeerRssi(Stream& ah, HalowUartFeedFn feed, bool idle) {
  static uint32_t nextMs = 0;
  if (!idle || millis() < nextMs) return;
  nextMs = millis() + 7000;

  ah.print("AT+RSSI?\r\n");
  uint32_t t0 = millis();
  char line[72];
  int n = 0;
  while (millis() - t0 < 300) {
    while (ah.available()) {
      uint8_t b = (uint8_t)ah.read();
      if (b == 0xff || b >= 0xf0) {
        if (feed) feed(b);
        continue;
      }
      if (b == '\n') {
        line[n] = 0;
        if (n > 0) {
          int r = halowParseRssiLine(line);
          if (r != 0) g_halowPeerRssi = r;
        }
        n = 0;
      } else if (b != '\r' && n < 70) {
        line[n++] = (char)b;
      }
    }
    delay(1);
  }
}
