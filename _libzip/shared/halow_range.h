#pragma once

#include <Arduino.h>

// HaLow range tuning (see LilyGO docs/AT_cmd.md). Voice needs little bandwidth — 1 MHz + MCS 0 = farthest link.
#ifndef HALOW_TX_POWER
#define HALOW_TX_POWER 20
#endif
#ifndef HALOW_BSS_BW
#define HALOW_BSS_BW 1
#endif
#ifndef HALOW_TX_MCS
#define HALOW_TX_MCS 0
#endif

static void halowApplyRange(void (*sendAt)(const char*)) {
  char buf[40];
  snprintf(buf, sizeof(buf), "AT+TXPOWER=%d", HALOW_TX_POWER);
  sendAt(buf);
  snprintf(buf, sizeof(buf), "AT+BSS_BW=%d", HALOW_BSS_BW);
  sendAt(buf);
  snprintf(buf, sizeof(buf), "AT+TX_MCS=%d", HALOW_TX_MCS);
  sendAt(buf);
}

static inline void halowTxDataLine(Stream& ah, int len) {
  ah.print("AT+TXDATA=");
  ah.print(len);
  ah.print(",");
  ah.print(HALOW_BSS_BW);
  ah.print(",");
  ah.print(HALOW_TX_MCS);
  ah.print(",0\r\n");
}
