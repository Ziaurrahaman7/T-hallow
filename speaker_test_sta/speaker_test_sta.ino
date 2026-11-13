/*
 * STA বোর্ড — স্পিকার খুঁজে বের করা (দুই পিন সেট)
 * A = নিচু টোন @ GPIO 7/6/15 (STA ডিফল্ট)
 * B = উঁচু টোন @ GPIO 17/16/18 (ভুল লেবেল হলে এখানে শব্দ হতে পারে)
 * যেটা শুনবেন Serial-এ লাইন দেখুন। তারপর sta.ino ফ্ল্যাশ।
 */
#include <Arduino.h>
#include <driver/i2s_std.h>
#include "../shared/speaker_beep.h"

static i2s_chan_handle_t spk = nullptr;

static bool speakerStart(int bclk, int lrc, int din) {
  if (spk != nullptr) {
    i2s_channel_disable(spk);
    i2s_del_channel(spk);
    spk = nullptr;
  }
  i2s_chan_config_t chan = I2S_CHANNEL_DEFAULT_CONFIG(I2S_NUM_0, I2S_ROLE_MASTER);
  chan.auto_clear = true;
  if (i2s_new_channel(&chan, &spk, nullptr) != ESP_OK) return false;
  i2s_std_config_t cfg = {
    .clk_cfg = I2S_STD_CLK_DEFAULT_CONFIG(8000),
    .slot_cfg = I2S_STD_PHILIPS_SLOT_DEFAULT_CONFIG(I2S_DATA_BIT_WIDTH_16BIT, I2S_SLOT_MODE_STEREO),
    .gpio_cfg = {
      .mclk = I2S_GPIO_UNUSED,
      .bclk = (gpio_num_t)bclk,
      .ws = (gpio_num_t)lrc,
      .dout = (gpio_num_t)din,
      .din = I2S_GPIO_UNUSED,
    },
  };
  return i2s_channel_init_std_mode(spk, &cfg) == ESP_OK;
}

static void playLabeled(const char* label, uint32_t step) {
  Serial.println(label);
  if (spk && speakerBeepTone(spk, step, 24000, 600)) {
    Serial.println("  (I2S ok — if NO sound: VIN/GND/speaker module)");
  } else {
    Serial.println("  FAIL I2S");
  }
  delay(800);
}

void setup() {
  Serial.begin(115200);
  delay(400);
  Serial.println("=== STA SPEAKER FINDER ===");
  Serial.println("VBAT/USB power + MAX98357 VIN/GND must be on");

  if (speakerStart(7, 6, 15)) {
    playLabeled("BEEP A: GPIO 7/6/15 (STA speaker in code)", 50000);
  } else {
    Serial.println("FAIL init 7/6/15");
  }

  if (speakerStart(17, 16, 18)) {
    playLabeled("BEEP B: GPIO 17/16/18 (if you hear THIS, speaker is here)", 95000);
  } else {
    Serial.println("FAIL init 17/16/18");
  }

  Serial.println("Loop: A then B every 6s — note which tone you HEAR");
}

static bool useAlt = false;

void loop() {
  delay(6000);
  if (useAlt) {
    if (speakerStart(7, 6, 15)) {
      playLabeled("loop A 7/6/15", 50000);
    }
  } else {
    if (speakerStart(17, 16, 18)) {
      playLabeled("loop B 17/16/18", 95000);
    }
  }
  useAlt = !useAlt;
}
