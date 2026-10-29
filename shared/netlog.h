#pragma once

#include "net_config.h"

#if NET_ON

#include <WiFi.h>
#include <HTTPClient.h>
#include <esp_heap_caps.h>
#include <string.h>

static const int NET_BATCH = 60;
static const int NET_JSON = 24576;

struct NetClip {
  uint32_t t;
  char who;
  uint8_t data[163];
};

static NetClip* netClips = nullptr;
static int netCap = 0;
static int netHead = 0;
static int netStored = 0;
static portMUX_TYPE netMux = portMUX_INITIALIZER_UNLOCKED;
static char netBoard[8] = "AP";
static volatile uint32_t netActiveMs = 0;
static volatile int netState = 0;
static volatile int netSent = 0;
static volatile int netFailCode = 0;

static void netKeep(const uint8_t* data, int n, char who, bool mine) {
  if (netClips == nullptr || data == nullptr || n < 163) return;
  portENTER_CRITICAL(&netMux);
  netClips[netHead].t = millis();
  netClips[netHead].who = who;
  memcpy(netClips[netHead].data, data, 163);
  netHead = (netHead + 1) % netCap;
  if (netStored < netCap) netStored++;
  portEXIT_CRITICAL(&netMux);
  if (mine) netActiveMs = millis();
}

static void netNote(const uint8_t* data, int n) {
  netKeep(data, n, netBoard[0] == 'S' ? 'S' : 'A', true);
}

static void netHear(const uint8_t* data, int n) {
  netKeep(data, n, netBoard[0] == 'S' ? 'A' : 'S', false);
}

static int netTake(NetClip* out, int maxn) {
  int n = 0;
  portENTER_CRITICAL(&netMux);
  int start = netCap ? (netHead - netStored + netCap) % netCap : 0;
  while (n < maxn && netStored > 0) {
    out[n] = netClips[start];
    start = (start + 1) % netCap;
    netStored--;
    n++;
  }
  portEXIT_CRITICAL(&netMux);
  return n;
}

static void netPushFront(const NetClip* batch, int n) {
  portENTER_CRITICAL(&netMux);
  for (int i = n - 1; i >= 0; i--) {
    if (netCap == 0 || netStored >= netCap) break;
    int start = (netHead - netStored - 1 + netCap) % netCap;
    netClips[start] = batch[i];
    netStored++;
  }
  portEXIT_CRITICAL(&netMux);
}

static int netB64(const uint8_t* in, int n, char* out) {
  static const char tbl[] =
      "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/";
  int o = 0;
  int i = 0;
  while (i + 2 < n) {
    uint32_t v = ((uint32_t)in[i] << 16) | ((uint32_t)in[i + 1] << 8) | in[i + 2];
    out[o++] = tbl[(v >> 18) & 63];
    out[o++] = tbl[(v >> 12) & 63];
    out[o++] = tbl[(v >> 6) & 63];
    out[o++] = tbl[v & 63];
    i += 3;
  }
  if (i < n) {
    uint32_t v = ((uint32_t)in[i] << 16);
    if (i + 1 < n) v |= ((uint32_t)in[i + 1] << 8);
    out[o++] = tbl[(v >> 18) & 63];
    out[o++] = tbl[(v >> 12) & 63];
    if (i + 1 < n) {
      out[o++] = tbl[(v >> 6) & 63];
      out[o++] = '=';
    } else {
      out[o++] = '=';
      out[o++] = '=';
    }
  }
  out[o] = 0;
  return o;
}

static bool netPost(char* json, const NetClip* batch, int n) {
  int cap = NET_JSON;
  int p = snprintf(json, cap,
                   "{\"board\":\"%s\",\"token\":\"%s\",\"millis\":%lu,\"clips\":[",
                   netBoard, NET_TOKEN, (unsigned long)millis());
  if (p < 0 || p >= cap - 400) return false;
  char b64[240];
  for (int i = 0; i < n; i++) {
    if (p > cap - 360) return false;
    if (i) json[p++] = ',';
    const char* who = batch[i].who == 'S' ? "STA" : "AP";
    int h = snprintf(json + p, cap - p, "{\"t\":%lu,\"w\":\"%s\",\"b\":\"",
                     (unsigned long)batch[i].t, who);
    if (h < 0 || h >= cap - p) return false;
    p += h;
    int bl = netB64(batch[i].data, 163, b64);
    if (p + bl + 3 >= cap) return false;
    memcpy(json + p, b64, bl);
    p += bl;
    json[p++] = '"';
    json[p++] = '}';
  }
  if (p + 3 >= cap) return false;
  json[p++] = ']';
  json[p++] = '}';
  json[p] = 0;

  WiFiClient client;
  HTTPClient http;
  http.setTimeout(8000);
  http.setConnectTimeout(5000);
  if (!http.begin(client, NET_URL)) {
    netFailCode = -1;
    return false;
  }
  http.addHeader("Content-Type", "application/json");
  int code = http.POST((uint8_t*)json, p);
  http.end();
  if (code != 200) netFailCode = code == 0 ? -1 : code;
  return code == 200;
}

static void netTask(void*) {
  NetClip* batch = (NetClip*)malloc(sizeof(NetClip) * NET_BATCH);
  char* json = (char*)malloc(NET_JSON);
  if (batch == nullptr || json == nullptr) {
    vTaskDelete(NULL);
    return;
  }
  uint32_t lastBegin = millis();
  for (;;) {
    if (WiFi.status() != WL_CONNECTED) {
      netState = 1;
      if (millis() - lastBegin > 10000) {
        lastBegin = millis();
        WiFi.begin(NET_SSID, NET_PASS);
      }
      vTaskDelay(pdMS_TO_TICKS(500));
      continue;
    }
    lastBegin = millis();
    netState = 2;
    bool idle = (uint32_t)(millis() - netActiveMs) > 1200;
    if (netStored > 0 && idle) {
      int n = netTake(batch, NET_BATCH);
      if (n > 0) {
        if (netPost(json, batch, n)) {
          portENTER_CRITICAL(&netMux);
          netSent += n;
          portEXIT_CRITICAL(&netMux);
        } else {
          netPushFront(batch, n);
          vTaskDelay(pdMS_TO_TICKS(5000));
        }
      }
    }
    vTaskDelay(pdMS_TO_TICKS(350));
  }
}

static void netBegin(const char* board) {
  if (NET_SSID[0] == '\0') return;
  strncpy(netBoard, board ? board : "AP", sizeof(netBoard) - 1);
  netBoard[sizeof(netBoard) - 1] = 0;
  netActiveMs = millis();

  if (psramFound()) {
    netCap = 20000;
    netClips = (NetClip*)heap_caps_malloc(sizeof(NetClip) * netCap,
                                          MALLOC_CAP_SPIRAM | MALLOC_CAP_8BIT);
  }
  if (netClips == nullptr) {
    netCap = 400;
    netClips = (NetClip*)malloc(sizeof(NetClip) * netCap);
  }
  if (netClips == nullptr) {
    netCap = 0;
    monPrint("net mem fail\n");
    return;
  }

  WiFi.persistent(false);
  WiFi.mode(WIFI_STA);
  delay(100);
  WiFi.setSleep(false);
  WiFi.setAutoReconnect(true);
  WiFi.begin(NET_SSID, NET_PASS);
  if (xTaskCreatePinnedToCore(netTask, "net", 20480, NULL, 1, NULL, 1) != pdPASS) {
    monPrint("net task fail\n");
    return;
  }
  netState = 1;
}

static void netPoll() {
  static int shown = 0;
  static uint32_t lastWait = 0;
  if (netState == 1 && millis() - lastWait > 5000) {
    lastWait = millis();
    int st = (int)WiFi.status();
    const char* why = "down";
    if (st == WL_NO_SSID_AVAIL) why = "no ssid";
    else if (st == WL_CONNECT_FAILED) why = "password";
    else if (st == WL_IDLE_STATUS) why = "idle";
    monPrintf("net wait %s\n", why);
    shown = 1;
  }
  if (netState == 2 && shown != 2) {
    shown = 2;
    IPAddress ip = WiFi.localIP();
    monPrintf("net up %u.%u.%u.%u\n", ip[0], ip[1], ip[2], ip[3]);
  }
  if (netFailCode != 0) {
    int code = netFailCode;
    netFailCode = 0;
    monPrintf("net fail %d\n", code);
  }
  if (netSent > 0) {
    int n;
    portENTER_CRITICAL(&netMux);
    n = netSent;
    netSent = 0;
    portEXIT_CRITICAL(&netMux);
    if (n > 0) monPrintf("net sent %d\n", n);
  }
}

#else

static inline void netBegin(const char*) {}
static inline void netNote(const uint8_t*, int) {}
static inline void netHear(const uint8_t*, int) {}
static inline void netPoll() {}

#endif
