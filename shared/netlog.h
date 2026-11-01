#pragma once

#include "net_config.h"
#include "fw_version.h"

#if NET_ON

#include <WiFi.h>
#include <HTTPClient.h>
#include <HTTPUpdate.h>
#include <WiFiClientSecure.h>
#include <Preferences.h>
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
static volatile uint32_t netPlayMs = 0;
static volatile int netState = 0;
static volatile int netSent = 0;
static volatile int netFailCode = 0;
static volatile int netHold = 0;

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
  netPlayMs = millis();
}

static void netRadio() {
  WiFi.setSleep(true);
  WiFi.setTxPower(WIFI_POWER_15dBm);
}

static char netSsid[33];
static char netPass[65];
static char netGoodSsid[33];
static char netGoodPass[65];
static char netSkipSsid[33];
static char netSkipPass[65];
static bool netSwitching = false;
static uint32_t netSwitchAt = 0;

static const int NET_UPLOAD_MAX = 2;
static char netUploadUrls[NET_UPLOAD_MAX][220];
static int netUploadN = 0;

static void netCopy(char* dst, int cap, const char* src) {
  if (cap <= 0) return;
  strncpy(dst, src ? src : "", cap - 1);
  dst[cap - 1] = 0;
}

static void netSaveCreds() {
  Preferences prefs;
  if (!prefs.begin("hallow", false)) return;
  prefs.putString("ssid", netSsid);
  prefs.putString("pass", netPass);
  prefs.end();
}

static void netLoadCreds() {
  netCopy(netSsid, sizeof(netSsid), NET_SSID);
  netCopy(netPass, sizeof(netPass), NET_PASS);
  Preferences prefs;
  if (!prefs.begin("hallow", true)) return;
  String ssid = prefs.getString("ssid", "");
  String pass = prefs.getString("pass", "");
  prefs.end();
  if (ssid.length() > 0) {
    netCopy(netSsid, sizeof(netSsid), ssid.c_str());
    netCopy(netPass, sizeof(netPass), pass.c_str());
  }
  netCopy(netGoodSsid, sizeof(netGoodSsid), netSsid);
  netCopy(netGoodPass, sizeof(netGoodPass), netPass);
}

static bool netJsonField(const char* json, const char* key, char* out, int cap) {
  char pat[20];
  snprintf(pat, sizeof(pat), "\"%s\":\"", key);
  const char* p = strstr(json, pat);
  if (p == nullptr) return false;
  p += strlen(pat);
  int o = 0;
  while (*p && *p != '"' && o + 1 < cap) {
    if (*p == '\\' && p[1]) {
      p++;
      out[o++] = *p++;
      continue;
    }
    out[o++] = *p++;
  }
  out[o] = 0;
  return *p == '"';
}

static void netInitUploads() {
  netUploadN = 0;
  netCopy(netUploadUrls[netUploadN], sizeof(netUploadUrls[0]), NET_URL);
  netUploadN = 1;
#ifdef NET_URL_ALT
  if (NET_URL_ALT[0] != '\0' && strcmp(NET_URL, NET_URL_ALT) != 0 && netUploadN < NET_UPLOAD_MAX) {
    netCopy(netUploadUrls[netUploadN], sizeof(netUploadUrls[0]), NET_URL_ALT);
    netUploadN++;
  }
#endif
}

static const char* netUploadBase() {
  if (netUploadN > 0 && netUploadUrls[0][0] != '\0') return netUploadUrls[0];
  return NET_URL;
}

static bool netHttpBegin(HTTPClient& http, const char* url, WiFiClient& plain,
                         WiFiClientSecure& secure) {
  http.setTimeout(8000);
  http.setConnectTimeout(5000);
  if (strncmp(url, "https://", 8) == 0) {
    secure.setInsecure();
    return http.begin(secure, url);
  }
  return http.begin(plain, url);
}

static int netJsonStringArray(const char* json, const char* key, char out[][220], int maxn) {
  char pat[20];
  snprintf(pat, sizeof(pat), "\"%s\":[", key);
  const char* p = strstr(json, pat);
  if (p == nullptr) return 0;
  p += strlen(pat);
  int n = 0;
  while (n < maxn && *p && *p != ']') {
    while (*p && *p != '"' && *p != ']') p++;
    if (*p == ']') break;
    if (*p != '"') break;
    p++;
    int o = 0;
    while (*p && *p != '"' && o + 1 < 220) {
      if (*p == '\\' && p[1]) {
        p++;
        out[n][o++] = *p++;
        continue;
      }
      out[n][o++] = *p++;
    }
    out[n][o] = 0;
    if (o > 0) n++;
    if (*p == '"') p++;
  }
  return n;
}

static void netApplyUploads(const char* json) {
  char tmp[NET_UPLOAD_MAX][220];
  int n = netJsonStringArray(json, "uploads", tmp, NET_UPLOAD_MAX);
  if (n <= 0) return;
  netUploadN = n;
  for (int i = 0; i < n; i++) {
    netCopy(netUploadUrls[i], sizeof(netUploadUrls[i]), tmp[i]);
  }
}

static int netJsonInt(const char* json, const char* key, int fallback) {
  char pat[24];
  snprintf(pat, sizeof(pat), "\"%s\":", key);
  const char* p = strstr(json, pat);
  if (p == nullptr) return fallback;
  return atoi(p + strlen(pat));
}

static void netJoin() {
  WiFi.setSleep(false);
  WiFi.begin(netSsid, netPass);
}

static bool netApiUrl(char* out, int cap, const char* leaf) {
  const char* src = netUploadBase();
  const char* slash = strrchr(src, '/');
  if (slash == nullptr) return false;
  int n = (int)(slash - src + 1);
  if (n <= 0 || n >= cap) return false;
  memcpy(out, src, n);
  snprintf(out + n, cap - n, "%s", leaf);
  return true;
}

static void netUse(const char* ssid, const char* pass) {
  netCopy(netSsid, sizeof(netSsid), ssid);
  netCopy(netPass, sizeof(netPass), pass);
  WiFi.disconnect();
  netJoin();
  netState = 1;
}

static void netPullWifi() {
  char url[220];
  if (!netApiUrl(url, sizeof(url), "wifi.php?token=" NET_TOKEN)) return;

  WiFiClient plain;
  WiFiClientSecure secure;
  HTTPClient http;
  http.setTimeout(4000);
  http.setConnectTimeout(3000);
  if (!netHttpBegin(http, url, plain, secure)) return;
  int code = http.GET();
  if (code != 200) {
    http.end();
    return;
  }
  String body = http.getString();
  http.end();

  netApplyUploads(body.c_str());

  char ssid[33];
  char pass[65];
  if (!netJsonField(body.c_str(), "ssid", ssid, sizeof(ssid))) return;
  if (!netJsonField(body.c_str(), "pass", pass, sizeof(pass))) return;
  if (ssid[0] == '\0') return;
  if (strcmp(ssid, netSsid) == 0 && strcmp(pass, netPass) == 0) return;
  if (strcmp(ssid, netSkipSsid) == 0 && strcmp(pass, netSkipPass) == 0) return;
  netSwitching = true;
  netSwitchAt = millis();
  netUse(ssid, pass);
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

  if (netUploadN <= 0) netInitUploads();
  WiFiClient plain;
  WiFiClientSecure secure;
  bool any = false;
  int lastCode = 0;
  for (int u = 0; u < netUploadN; u++) {
    const char* target = netUploadUrls[u];
    if (target[0] == '\0') continue;
    HTTPClient http;
    if (!netHttpBegin(http, target, plain, secure)) {
      lastCode = -1;
      continue;
    }
    http.addHeader("Content-Type", "application/json");
    int code = http.POST((uint8_t*)json, p);
    http.end();
    if (code == 200) {
      any = true;
    } else {
      lastCode = code == 0 ? -1 : code;
    }
  }
  if (!any) netFailCode = lastCode == 0 ? -1 : lastCode;
  return any;
}

static int netFwReported() {
  int ver = FW_VERSION;
  Preferences prefs;
  if (!prefs.begin("hallow", true)) return ver;
  int saved = prefs.getInt("fw", 0);
  prefs.end();
  if (saved > ver) ver = saved;
  return ver;
}

static void netFwSave(int ver) {
  Preferences prefs;
  if (!prefs.begin("hallow", false)) return;
  prefs.putInt("fw", ver);
  prefs.end();
}

static void netFirmware() {
  uint32_t lastAudio = netActiveMs > netPlayMs ? netActiveMs : netPlayMs;
  if ((uint32_t)(millis() - lastAudio) < 700) return;
  char leaf[96];
  snprintf(leaf, sizeof(leaf), "fw.php?token=%s&board=%s&ver=%d",
           NET_TOKEN, netBoard, netFwReported());
  char url[220];
  if (!netApiUrl(url, sizeof(url), leaf)) return;
  bool tls = strncmp(url, "https://", 8) == 0;
  WiFiClient plain;
  WiFiClientSecure secure;
  HTTPClient http;
  http.setTimeout(8000);
  http.setConnectTimeout(5000);
  bool begun = false;
  if (tls) {
    secure.setInsecure();
    begun = http.begin(secure, url);
  } else {
    begun = http.begin(plain, url);
  }
  if (!begun) return;
  int code = http.GET();
  if (code != 200) {
    http.end();
    return;
  }
  String body = http.getString();
  http.end();
  if (netJsonInt(body.c_str(), "update", 0) != 1) return;
  int ver = netJsonInt(body.c_str(), "version", 0);
  if (ver <= netFwReported()) return;
  char bin[300];
  char md5[40];
  if (!netJsonField(body.c_str(), "url", bin, sizeof(bin))) return;
  if (!netJsonField(body.c_str(), "md5", md5, sizeof(md5))) return;
  lastAudio = netActiveMs > netPlayMs ? netActiveMs : netPlayMs;
  if ((uint32_t)(millis() - lastAudio) < 700) return;
  httpUpdate.rebootOnUpdate(false);
  HTTPUpdateResult ret;
  if (tls) ret = httpUpdate.update(secure, bin, md5);
  else ret = httpUpdate.update(plain, bin, md5);
  if (ret == HTTP_UPDATE_OK) {
    netFwSave(ver);
    delay(200);
    ESP.restart();
  }
  int err = httpUpdate.getLastError();
  netFailCode = err == 0 ? -20 : err;
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
      if (netSwitching && millis() - netSwitchAt > 20000) {
        netCopy(netSkipSsid, sizeof(netSkipSsid), netSsid);
        netCopy(netSkipPass, sizeof(netSkipPass), netPass);
        netSwitching = false;
        lastBegin = millis();
        netUse(netGoodSsid, netGoodPass);
        netSaveCreds();
      } else if (!netSwitching && millis() - lastBegin > 10000) {
        lastBegin = millis();
        int st = (int)WiFi.status();
        if (st == WL_CONNECT_FAILED || st == WL_NO_SSID_AVAIL || st == WL_CONNECTION_LOST) {
          WiFi.disconnect(false, false);
          vTaskDelay(pdMS_TO_TICKS(200));
        }
        netJoin();
      }
      vTaskDelay(pdMS_TO_TICKS(500));
      continue;
    }
    if (netState != 2) netRadio();
    static bool netSynced = false;
    if (!netSynced) {
      netSynced = true;
      netPullWifi();
    }
    if (netSwitching) {
      netSwitching = false;
      netCopy(netGoodSsid, sizeof(netGoodSsid), netSsid);
      netCopy(netGoodPass, sizeof(netGoodPass), netPass);
      netSaveCreds();
      netSkipSsid[0] = 0;
      netSkipPass[0] = 0;
    }
    lastBegin = millis();
    netState = 2;
    uint32_t now = millis();
    uint32_t lastAudio = netActiveMs > netPlayMs ? netActiveMs : netPlayMs;
    bool idle = (uint32_t)(now - lastAudio) > 700;
    bool stale = false;
    if (netStored > 0) {
      uint32_t oldest = 0;
      portENTER_CRITICAL(&netMux);
      if (netCap > 0) {
        int start = (netHead - netStored + netCap) % netCap;
        oldest = netClips[start].t;
      }
      portEXIT_CRITICAL(&netMux);
      stale = oldest > 0 && (uint32_t)(now - oldest) > 6000;
    }
    netHold = idle || stale ? 0 : netStored;
    static uint32_t netWifiPoll = 0;
    if (netStored > 0 && (idle || stale)) {
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
    if (idle && now - netWifiPoll > 15000) {
      netWifiPoll = now;
      netPullWifi();
    }
    static uint32_t netFwPoll = 0;
    if (idle && now - netFwPoll > 30000) {
      netFwPoll = now;
      netFirmware();
    }
    vTaskDelay(pdMS_TO_TICKS(350));
  }
}

static void netBegin(const char* board) {
  netInitUploads();
  netLoadCreds();
  if (netSsid[0] == '\0') return;
  strncpy(netBoard, board ? board : "AP", sizeof(netBoard) - 1);
  netBoard[sizeof(netBoard) - 1] = 0;
  netActiveMs = millis();
  netPlayMs = millis();

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
  WiFi.setAutoReconnect(true);
  netJoin();
  if (xTaskCreatePinnedToCore(netTask, "net", 32768, NULL, 1, NULL, 1) != pdPASS) {
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
  if (netState == 2 && netHold > 0 && millis() - lastWait > 5000) {
    lastWait = millis();
    monPrintf("net hold %d\n", netHold);
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
