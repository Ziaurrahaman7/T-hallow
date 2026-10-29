// STA speaker. Upload this file to the speaker board. Plays voice from the AP mic.
// MAX98357A: BCLK=GPIO7  LRC=GPIO6  DIN=GPIO15  VIN=5V  GND=GND
// AP sends 8 kHz voice as 163-byte ADPCM. Magic A5 5A A5 5A. Quiet air is silence.

#include <Arduino.h>
#include <driver/i2s_std.h>
#include <driver/usb_serial_jtag.h>
#include <stdio.h>
#include <stdarg.h>

#define AH_RX 4
#define AH_TX 5
#define I2S_BCLK 7
#define I2S_LRC  6
#define I2S_DIN  15
#define SAMPLES 163
#define PCM_N 321

static const uint8_t MAGIC[4] = {0xA5, 0x5A, 0xA5, 0x5A};

HardwareSerial SerialAH(2);

#if ARDUINO_USB_CDC_ON_BOOT
static void monBegin() { Serial.begin(115200); }
static size_t monWrite(const uint8_t* buf, size_t n) { return Serial.write(buf, n); }
static void monFlush() { Serial.flush(); }
#else
static bool monOn = false;
static void monBegin() {
  if (!usb_serial_jtag_is_driver_installed()) {
    usb_serial_jtag_driver_config_t cfg = USB_SERIAL_JTAG_DRIVER_CONFIG_DEFAULT();
    usb_serial_jtag_driver_install(&cfg);
  }
  monOn = true;
}
static size_t monWrite(const uint8_t* buf, size_t n) {
  if (!monOn || n == 0) return 0;
  int w = usb_serial_jtag_write_bytes(buf, n, pdMS_TO_TICKS(50));
  return w > 0 ? (size_t)w : 0;
}
static void monFlush() { usb_serial_jtag_wait_tx_done(pdMS_TO_TICKS(50)); }
#endif

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

uint32_t rxCount = 0;
uint32_t lastPrint = 0;

void sendAT(const char* cmd) {
  SerialAH.print(cmd);
  SerialAH.print("\r\n");
  delay(300);
  while (SerialAH.available()) SerialAH.read();
}

uint32_t playCount = 0;
bool wasTalk = false;
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

void playPacket(uint8_t* p) {
  static uint8_t lastPkt[SAMPLES];
  static bool haveLast = false;
  rxCount++;
  if (haveLast && memcmp(lastPkt, p, SAMPLES) == 0) return;
  memcpy(lastPkt, p, SAMPLES);
  haveLast = true;

  int16_t pcm[PCM_N];
  if (!adpcmUnpack(p, pcm)) return;

  int strong = 0;
  int hot = 0;
  for (int i = 0; i < PCM_N; i++) {
    int a = pcm[i] < 0 ? -pcm[i] : pcm[i];
    if (a >= 250) strong++;
    if (a >= 28000) hot++;
  }
  bool talk = strong >= 50 && hot < (PCM_N * 4) / 5;
  if (!talk) {
    if (wasTalk) {
      fadeOut();
      pushSilence(4);
      wasTalk = false;
    }
    return;
  }
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
  if (spk != NULL) i2s_channel_write(spk, stereo, sizeof(stereo), &w, 200);
  playCount++;
}

void setup() {
  monBegin();
  delay(400);
  monPrint("sta boot\n");
  SerialAH.setRxBufferSize(2048);
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
  esp_err_t okEn = (okStd == ESP_OK) ? i2s_channel_enable(spk) : okStd;
  if (okEn == ESP_OK) {
    pushSilence(4);
    monPrint("speaker quiet\n");
  } else {
    spk = NULL;
    monPrintf("pin setup fail chan=%d std=%d en=%d\n", (int)okChan, (int)okStd, (int)okEn);
  }

  delay(500);
  sendAT("AT+SYSDBG=LMAC,0");
  sendAT("AT+MODE=STA");
  sendAT("AT+SSID=helmet01");
  sendAT("AT+KEYMGMT=NONE");
  sendAT("AT+PAIR=1");
  delay(2500);
  sendAT("AT+PAIR=0");
  monPrint("COM5 listening\n");
}

void loop() {
  static int matched = 0;
  static int idx = 0;
  static bool collecting = false;
  static uint8_t pkt[SAMPLES];

  while (SerialAH.available()) {
    uint8_t b = SerialAH.read();
    if (!collecting) {
      if (b == MAGIC[matched]) {
        matched++;
        if (matched == 4) {
          collecting = true;
          idx = 0;
          matched = 0;
        }
      } else {
        matched = (b == MAGIC[0]) ? 1 : 0;
      }
    } else {
      pkt[idx++] = b;
      if (idx >= SAMPLES) {
        playPacket(pkt);
        collecting = false;
      }
    }
  }

  if (!wasTalk) pushSilence(1);

  if (millis() - lastPrint > 1000) {
    monPrintf("alive rx=%lu play=%lu\n", rxCount, playCount);
    lastPrint = millis();
  }
}
