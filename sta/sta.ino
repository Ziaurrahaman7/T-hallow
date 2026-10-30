// STA board. Upload to the board whose speaker is on GPIO7/6/15.
// MAX98357A: BCLK=GPIO7  LRC=GPIO6  DIN=GPIO15  GND=GND  VIN=VBAT
// INMP441: SCK=GPIO17  WS=GPIO16  SD=GPIO18  VDD=3.3V  GND=GND  L/R=GND
// Talks both ways. Plays AP magic A5 5A A5 5A. Own voice uses A5 5A 5A A5.

#include <Arduino.h>
#include <driver/i2s_std.h>
#include <stdio.h>
#include <stdarg.h>
#if !ARDUINO_USB_CDC_ON_BOOT
#include "HWCDC.h"
static HWCDC MonPort;
#endif

#define AH_RX 4
#define AH_TX 5
#define I2S_BCLK 7
#define I2S_LRC  6
#define I2S_DIN  15
#define MIC_SCK 17
#define MIC_WS  16
#define MIC_SD  18
#define SAMPLES 163
#define PCM_N 321
#define PAYLOAD 163
#define DECIM 2
#define SHIFT 11
#define RING 2048

static const uint8_t PLAY_MAGIC[4] = {0xA5, 0x5A, 0xA5, 0x5A};
static const uint8_t OWN_MAGIC[4] = {0xA5, 0x5A, 0x5A, 0xA5};

HardwareSerial SerialAH(2);

#if ARDUINO_USB_CDC_ON_BOOT
static void monBegin() {
  Serial.begin(115200);
  Serial.setTxTimeoutMs(0);
}
static size_t monWrite(const uint8_t* buf, size_t n) {
  if (n == 0) return 0;
  return Serial.write(buf, n);
}
#else
static void monBegin() {
  MonPort.begin(115200);
  MonPort.setTxTimeoutMs(0);
}
static size_t monWrite(const uint8_t* buf, size_t n) {
  if (n == 0) return 0;
  return MonPort.write(buf, n);
}
#endif
static void monFlush() {}

static void monPrint(const char* s) {
  if (s) monWrite((const uint8_t*)s, strlen(s));
  monFlush();
}
static void monPrintf(const char* fmt, ...) {
  char buf[160];
  va_list ap;
  va_start(ap, fmt);
  vsnprintf(buf, sizeof(buf), fmt, ap);
  va_end(ap);
  monPrint(buf);
}

#include "../shared/netlog.h"

uint32_t rxCount = 0;
uint32_t lastPrint = 0;

void sendAT(const char* cmd) {
  SerialAH.print(cmd);
  SerialAH.print("\r\n");
  delay(300);
  while (SerialAH.available()) SerialAH.read();
}

uint32_t playCount = 0;
uint32_t txCount = 0;
uint32_t failCount = 0;
bool wasTalk = false;
static uint32_t phraseMs = 0;
static bool spkOn = false;
static i2s_chan_handle_t micIn = NULL;
static volatile uint32_t micC0 = 0;
static volatile uint32_t micC1 = 0;
static int16_t ring[RING];
static volatile int wpos = 0;
static volatile int rpos = 0;
static portMUX_TYPE mux = portMUX_INITIALIZER_UNLOCKED;
static bool wantOk = false;
static bool okFlag = false;

static uint32_t mag32(int32_t s) {
  return (s < 0) ? (uint32_t)(-(int64_t)s) : (uint32_t)s;
}

static void pushSample(int16_t s) {
  portENTER_CRITICAL(&mux);
  int next = (wpos + 1) % RING;
  if (next == rpos) rpos = (rpos + 1) % RING;
  ring[wpos] = s;
  wpos = next;
  portEXIT_CRITICAL(&mux);
}

static bool takePacket(int16_t *dst) {
  portENTER_CRITICAL(&mux);
  int avail = (wpos - rpos + RING) % RING;
  if (avail > PCM_N) {
    rpos = (rpos + (avail - PCM_N)) % RING;
    avail = PCM_N;
  }
  if (avail < PCM_N) {
    portEXIT_CRITICAL(&mux);
    return false;
  }
  for (int i = 0; i < PCM_N; i++) {
    dst[i] = ring[rpos];
    rpos = (rpos + 1) % RING;
  }
  portEXIT_CRITICAL(&mux);
  return true;
}

static void micTask(void *) {
  int64_t sum = 0;
  int got = 0;
  int32_t raw[256];
  int32_t dc = 0;
  bool dcInit = false;

  for (;;) {
    if (micIn == NULL) {
      delay(20);
      continue;
    }
    size_t nbytes = 0;
    i2s_channel_read(micIn, raw, sizeof(raw), &nbytes, portMAX_DELAY);
    int n = (int)(nbytes / sizeof(int32_t));
    uint32_t m0 = 0;
    uint32_t m1 = 0;
    for (int i = 0; i + 1 < n; i += 2) {
      uint32_t a0 = mag32(raw[i]);
      uint32_t a1 = mag32(raw[i + 1]);
      if (a0 > m0) m0 = a0;
      if (a1 > m1) m1 = a1;
      int32_t s = 0;
      if (a0 <= 0x70000000UL && a0 > 0) s = raw[i];
      else if (a1 <= 0x70000000UL && a1 > 0) s = raw[i + 1];
      sum += s;
      got++;
      if (got < DECIM) continue;

      int32_t avg = (int32_t)(sum / DECIM);
      sum = 0;
      got = 0;
      if (!dcInit) {
        dc = avg;
        dcInit = true;
      } else {
        dc += (avg - dc) >> 8;
      }
      int32_t v = (avg - dc) >> SHIFT;
      static int32_t hpY = 0;
      static int32_t hpX = 0;
      int32_t x = v;
      v = x - hpX + (hpY * 7) / 8;
      hpX = x;
      hpY = v;
      if (v > 32767) v = 32767;
      if (v < -32768) v = -32768;
      int32_t a = v < 0 ? -v : v;
      if (a > 22000) {
        int32_t shaped = 22000 + (a - 22000) / 4;
        v = v < 0 ? -shaped : shaped;
      }
      if (v > 32767) v = 32767;
      if (v < -32768) v = -32768;
      pushSample((int16_t)v);
    }
    micC0 = m0;
    micC1 = m1;
  }
}

static bool setupMic() {
  i2s_chan_config_t chan = I2S_CHANNEL_DEFAULT_CONFIG(I2S_NUM_1, I2S_ROLE_MASTER);
  esp_err_t okChan = i2s_new_channel(&chan, NULL, &micIn);
  i2s_std_config_t cfg = {
    .clk_cfg = I2S_STD_CLK_DEFAULT_CONFIG(16000),
    .slot_cfg = I2S_STD_PHILIPS_SLOT_DEFAULT_CONFIG(I2S_DATA_BIT_WIDTH_32BIT, I2S_SLOT_MODE_STEREO),
    .gpio_cfg = {
      .mclk = I2S_GPIO_UNUSED,
      .bclk = (gpio_num_t)MIC_SCK,
      .ws = (gpio_num_t)MIC_WS,
      .dout = I2S_GPIO_UNUSED,
      .din = (gpio_num_t)MIC_SD,
      .invert_flags = {
        .mclk_inv = false,
        .bclk_inv = false,
        .ws_inv = false,
      },
    },
  };
  esp_err_t okStd = (okChan == ESP_OK) ? i2s_channel_init_std_mode(micIn, &cfg) : okChan;
  esp_err_t okEn = (okStd == ESP_OK) ? i2s_channel_enable(micIn) : okStd;
  if (okEn != ESP_OK) {
    micIn = NULL;
    monPrintf("mic pin fail chan=%d std=%d en=%d\n", (int)okChan, (int)okStd, (int)okEn);
    return false;
  }
  xTaskCreatePinnedToCore(micTask, "mic", 8192, NULL, 3, NULL, 0);
  monPrint("mic pins ok\n");
  return true;
}
static int16_t lastOut = 0;
static i2s_chan_handle_t spk = NULL;

static const int16_t stepTable[89] = {
  7, 8, 9, 10, 11, 12, 13, 14, 16, 17,
  19, 21, 23, 25, 28, 31, 34, 37, 41, 45,
  50, 55, 60, 66, 73, 80, 88, 97, 107, 118,
  130, 143, 157, 173, 190, 209, 230, 253, 279, 307,
  337, 371, 408, 449, 494, 544, 598, 658, 724, 796,
  876, 963, 1060, 1166, 1282, 1411, 1552, 1707, 1878, 2066,
  2272, 2499, 2749, 3024, 3327, 3660, 4026, 4428, 4871, 5358,
  5894, 6484, 7132, 7845, 8630, 9493, 10442, 11487, 12635, 13899,
  15289, 16818, 18500, 20350, 22385, 24623, 27086, 29794, 32767
};
static const int8_t indexTable[16] = {
  -1, -1, -1, -1, 2, 4, 6, 8,
  -1, -1, -1, -1, 2, 4, 6, 8
};

static int adpcmNibble(int sample, int* pred, int* index) {
  int step = stepTable[*index];
  int diff = sample - *pred;
  int nibble = 0;
  if (diff < 0) {
    nibble = 8;
    diff = -diff;
  }
  int diffq = step >> 3;
  if (diff >= step) {
    nibble |= 4;
    diff -= step;
    diffq += step;
  }
  step >>= 1;
  if (diff >= step) {
    nibble |= 2;
    diff -= step;
    diffq += step;
  }
  step >>= 1;
  if (diff >= step) {
    nibble |= 1;
    diffq += step;
  }
  if (nibble & 8) *pred -= diffq;
  else *pred += diffq;
  if (*pred > 32767) *pred = 32767;
  if (*pred < -32768) *pred = -32768;
  *index += indexTable[nibble];
  if (*index < 0) *index = 0;
  if (*index > 88) *index = 88;
  return nibble;
}

static void adpcmPack(const int16_t* pcm, uint8_t* out) {
  int pred = pcm[0];
  int index = 0;
  int mag = pred < 0 ? -pred : pred;
  while (index < 80 && stepTable[index] < (mag >> 2)) index++;
  out[0] = (uint8_t)(pred & 0xFF);
  out[1] = (uint8_t)((pred >> 8) & 0xFF);
  out[2] = (uint8_t)index;
  int o = 3;
  for (int i = 1; i < PCM_N; i += 2) {
    int hi = adpcmNibble(pcm[i], &pred, &index);
    int lo = adpcmNibble(pcm[i + 1], &pred, &index);
    out[o++] = (uint8_t)((hi << 4) | lo);
  }
}

static int16_t adpcmStep(int nibble, int* pred, int* index) {
  int step = stepTable[*index];
  int diffq = step >> 3;
  if (nibble & 4) diffq += step;
  if (nibble & 2) diffq += step >> 1;
  if (nibble & 1) diffq += step >> 2;
  if (nibble & 8) *pred -= diffq;
  else *pred += diffq;
  if (*pred > 32767) *pred = 32767;
  if (*pred < -32768) *pred = -32768;
  *index += indexTable[nibble & 15];
  if (*index < 0) *index = 0;
  if (*index > 88) *index = 88;
  return (int16_t)*pred;
}

static bool adpcmUnpack(const uint8_t* in, int16_t* pcm) {
  int index = in[2];
  if (index > 88) return false;
  int pred = (int16_t)(uint16_t)(in[0] | (in[1] << 8));
  pcm[0] = (int16_t)pred;
  int o = 3;
  int s = 1;
  while (o < SAMPLES && s + 1 < PCM_N) {
    uint8_t b = in[o++];
    pcm[s++] = adpcmStep(b >> 4, &pred, &index);
    pcm[s++] = adpcmStep(b & 0x0F, &pred, &index);
  }
  return s == PCM_N;
}

void pushSilence(int n) {
  if (spk == NULL) return;
  int16_t z[128];
  memset(z, 0, sizeof(z));
  for (int i = 0; i < n; i++) {
    size_t w = 0;
    i2s_channel_write(spk, z, sizeof(z), &w, 30);
  }
}

void fadeOut() {
  if (spk == NULL) return;
  int16_t stereo[48 * 2];
  for (int i = 0; i < 48; i++) {
    int16_t s = (int16_t)((int32_t)lastOut * (47 - i) / 48);
    stereo[i * 2] = s;
    stereo[i * 2 + 1] = s;
  }
  lastOut = 0;
  size_t w = 0;
  i2s_channel_write(spk, stereo, sizeof(stereo), &w, 40);
}

static void speakerOpen() {
  if (spk == NULL || spkOn) return;
  if (i2s_channel_enable(spk) == ESP_OK) spkOn = true;
}

static void speakerClose() {
  if (spkOn) {
    fadeOut();
    pushSilence(2);
    if (spk != NULL) i2s_channel_disable(spk);
    spkOn = false;
  }
  wasTalk = false;
  lastOut = 0;
  phraseMs = 0;
}

static void speakerIdle() {
  if (spkOn && phraseMs != 0 && (uint32_t)(millis() - phraseMs) > 900) speakerClose();
}

void playPacket(uint8_t* p) {
  static uint8_t lastPkt[SAMPLES];
  static bool haveLast = false;
  rxCount++;
  if (haveLast && memcmp(lastPkt, p, SAMPLES) == 0) return;
  memcpy(lastPkt, p, SAMPLES);
  haveLast = true;

  int16_t pcm[PCM_N];
  if (!adpcmUnpack(p, pcm)) return;

  int peak = 0;
  int strong = 0;
  for (int i = 0; i < PCM_N; i++) {
    int a = pcm[i] < 0 ? -pcm[i] : pcm[i];
    if (a > peak) peak = a;
    if (a >= 400) strong++;
  }
  bool start = peak >= 600 && strong >= 8;
  if (start) phraseMs = millis();
  bool phrase = phraseMs != 0 && (uint32_t)(millis() - phraseMs) < 900;
  if (!phrase) return;
  netHear(p, SAMPLES);
  speakerOpen();
  if (!spkOn) return;
  if (!wasTalk) {
    for (int i = 0; i < 48 && i < PCM_N; i++) {
      pcm[i] = (int16_t)((int32_t)pcm[i] * i / 48);
    }
  }
  wasTalk = true;

  static int16_t stereo[PCM_N * 2];
  for (int i = 0; i < PCM_N; i++) {
    int16_t s = (int16_t)(pcm[i] / 2);
    stereo[i * 2] = s;
    stereo[i * 2 + 1] = s;
  }
  lastOut = (int16_t)(pcm[PCM_N - 1] / 2);
  size_t w = 0;
  i2s_channel_write(spk, stereo, sizeof(stereo), &w, 60);
  playCount++;
}

static int matched = 0;
static int pktIdx = 0;
static bool collecting = false;
static uint8_t airPkt[SAMPLES];
static uint8_t rxq[8][SAMPLES];
static int rxqN = 0;

static void feedByte(uint8_t b) {
  if (wantOk) {
    static uint8_t prev = 0;
    if (prev == 'O' && b == 'K') okFlag = true;
    prev = b;
  }
  if (!collecting) {
    if (b == PLAY_MAGIC[matched]) {
      matched++;
      if (matched == 4) {
        collecting = true;
        pktIdx = 0;
        matched = 0;
      }
    } else {
      matched = (b == PLAY_MAGIC[0]) ? 1 : 0;
    }
  } else {
    airPkt[pktIdx++] = b;
    if (pktIdx >= SAMPLES) {
      if (rxqN < 8) {
        memcpy(rxq[rxqN], airPkt, SAMPLES);
        rxqN++;
      }
      collecting = false;
    }
  }
}

static void playQueued() {
  if (rxqN <= 0) return;
  uint8_t tmp[SAMPLES];
  memcpy(tmp, rxq[0], SAMPLES);
  if (rxqN > 1) memmove(rxq[0], rxq[1], (size_t)(rxqN - 1) * SAMPLES);
  rxqN--;
  playPacket(tmp);
}

static void pumpUart() {
  while (SerialAH.available()) feedByte((uint8_t)SerialAH.read());
}

static bool waitOk(uint32_t ms) {
  uint32_t t0 = millis();
  while (millis() - t0 < ms) {
    pumpUart();
    if (okFlag) return true;
    delay(1);
  }
  return false;
}

static void drainQuiet() {
  uint32_t t0 = millis();
  uint32_t last = millis();
  while (millis() - t0 < 40) {
    if (SerialAH.available()) {
      pumpUart();
      last = millis();
    } else if (millis() - last >= 8) {
      return;
    } else {
      delay(1);
    }
  }
}

static bool sendPacket(const uint8_t *audio) {
  static uint8_t frame[14 + 4 + PAYLOAD];
  memset(frame, 0xFF, 6);
  memset(frame + 6, 0, 8);
  memcpy(frame + 14, OWN_MAGIC, 4);
  memcpy(frame + 18, audio, PAYLOAD);
  const int len = 18 + PAYLOAD;

  pumpUart();
  SerialAH.print("AT+TXDATA=");
  SerialAH.print(len);
  SerialAH.print("\r\n");
  wantOk = true;
  okFlag = false;
  bool ok = waitOk(150);
  wantOk = false;
  if (!ok) {
    SerialAH.print("\r\n");
    delay(10);
    pumpUart();
    return false;
  }
  SerialAH.write(frame, len);
  SerialAH.flush();
  drainQuiet();
  return true;
}

void setup() {
  monBegin();
  delay(400);
  monPrint("sta boot\n");
  SerialAH.setRxBufferSize(2048);
  SerialAH.setTxBufferSize(1024);
  SerialAH.begin(115200, SERIAL_8N1, AH_RX, AH_TX);

  i2s_chan_config_t chan = I2S_CHANNEL_DEFAULT_CONFIG(I2S_NUM_0, I2S_ROLE_MASTER);
  chan.auto_clear = true;
  esp_err_t okChan = i2s_new_channel(&chan, &spk, NULL);
  i2s_std_config_t cfg = {
    .clk_cfg = I2S_STD_CLK_DEFAULT_CONFIG(8000),
    .slot_cfg = I2S_STD_PHILIPS_SLOT_DEFAULT_CONFIG(I2S_DATA_BIT_WIDTH_16BIT, I2S_SLOT_MODE_STEREO),
    .gpio_cfg = {
      .mclk = I2S_GPIO_UNUSED,
      .bclk = (gpio_num_t)I2S_BCLK,
      .ws = (gpio_num_t)I2S_LRC,
      .dout = (gpio_num_t)I2S_DIN,
      .din = I2S_GPIO_UNUSED,
      .invert_flags = {
        .mclk_inv = false,
        .bclk_inv = false,
        .ws_inv = false,
      },
    },
  };
  esp_err_t okStd = (okChan == ESP_OK) ? i2s_channel_init_std_mode(spk, &cfg) : okChan;
  if (okStd == ESP_OK) {
    spkOn = false;
    monPrint("speaker quiet\n");
  } else {
    spk = NULL;
    monPrintf("pin setup fail chan=%d std=%d\n", (int)okChan, (int)okStd);
  }

  setupMic();

  delay(500);
  sendAT("AT+SYSDBG=LMAC,0");
  sendAT("AT+MODE=STA");
  sendAT("AT+SSID=helmet01");
  sendAT("AT+KEYMGMT=NONE");
  sendAT("AT+PAIR=1");
  delay(2500);
  sendAT("AT+PAIR=0");
  monPrint("COM5 listening\n");
  netBegin("STA");
}

void loop() {
  netPoll();
  pumpUart();
  playQueued();
  speakerIdle();

  {
    int16_t pcm[PCM_N];
    if (takePacket(pcm)) {
      int nStrong = 0;
      int peak = 0;
      for (int i = 0; i < PCM_N; i++) {
        int a = pcm[i] < 0 ? -pcm[i] : pcm[i];
        if (a > peak) peak = a;
        if (a >= 400) nStrong++;
      }
      static uint32_t voicedMs = 0;
      static int lastPeak = 0;
      static uint32_t lastSend = 0;
      bool shaped = peak >= 800 && nStrong >= 8;
      if (shaped) voicedMs = millis();
      bool voice = shaped || (voicedMs != 0 && (uint32_t)(millis() - voicedMs) < 800 && peak >= 250);
      bool echo = wasTalk && peak < 250 && (uint32_t)(millis() - lastSend) < 150;
      if (voice && !echo) {
        uint8_t payload[PAYLOAD];
        adpcmPack(pcm, payload);
        if (sendPacket(payload)) {
          txCount++;
          netNote(payload, PAYLOAD);
          lastSend = millis();
          if (peak > lastPeak) lastPeak = peak;
          else lastPeak = (lastPeak * 3 + peak) / 4;
        } else {
          failCount++;
        }
      } else if (millis() - lastSend > 400) {
        lastPeak = 0;
      }
    }
  }

  if (millis() - lastPrint > 1000) {
    monPrintf("tx=%lu rx=%lu play=%lu fail=%lu mic c0=%lu c1=%lu\n",
              txCount, rxCount, playCount, failCount,
              (unsigned long)micC0, (unsigned long)micC1);
    lastPrint = millis();
  }
}
