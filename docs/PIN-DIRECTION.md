# T-Hallow — পিন ডিরেকশন (AP + STA)

ফার্মওয়্যার সোর্স: `ap/ap.ino`, `sta/sta.ino`, `shared/ptt_config.h`, `shared/battery_config.h`

Arduino: **ESP32S3 Dev Module** · Partition: **Minimal SPIFFS (1.9MB APP with OTA)**

---

## কোন বোর্ডে কোন স্কেচ

| বোর্ড | ফাইল | চেনার নিয়ম |
|--------|------|-------------|
| **AP** | `ap/ap.ino` | মাইক INMP441 **GPIO 7 / 6 / 15** |
| **STA** | `sta/sta.ino` | স্পিকার MAX98357 **GPIO 7 / 6 / 15** |

---

## HaLow মডিউল (দুই বোর্ড একই)

| ESP32-S3 | HaLow UART | নোট |
|----------|------------|------|
| **GPIO 4** | RX (মডিউল TX) | `SerialAH` RX |
| **GPIO 5** | TX (মডিউল RX) | `SerialAH` TX |

হেলমেট লিংক SSID (AT): `helmet01`

---

## বাটন (momentary — চেপে ধরুন, এক পা GPIO, এক পা GND)

| ফাংশন | GPIO | ওয়্যার |
|--------|------|----------|
| **PTT** | **14** | 14 ↔ GND |
| **Vol+** | **12** | 12 ↔ GND |
| **Vol−** | **13** | 13 ↔ GND |

৪-পিন ট্যাকটাইল: প্রতি বাটনে **এক জোড়া** (internal pull-up, চাপ = LOW)।

---

## ব্যাটারি

| GPIO | নোট |
|------|------|
| **3** | BAT ADC (divider ×2) |

---

## AP বোর্ড

### INMP441 (মাইক)

| মাইক | ESP32 |
|------|-------|
| SCK | **7** |
| WS | **6** |
| SD | **15** |
| VDD | **3.3V** |
| GND | **GND** |
| L/R | **GND** (left/mono) |

### MAX98357A (স্পিকার)

| অ্যাম্প | ESP32 / পাওয়ার |
|--------|-----------------|
| BCLK | **17** |
| LRC | **16** |
| DIN | **18** |
| VIN | **VBAT** |
| GND | **GND** |
| **SD** | **VIN বা 3.3V** (অবশ্যই অন — খোলা রাখবেন না) |

---

## STA বোর্ড

### MAX98357A (স্পিকার)

| অ্যাম্প | ESP32 / পাওয়ার |
|--------|-----------------|
| BCLK | **7** |
| LRC | **6** |
| DIN | **15** |
| VIN | **VBAT** |
| GND | **GND** |
| **SD** | **VIN বা 3.3V** |

### INMP441 (মাইক)

| মাইক | ESP32 |
|------|-------|
| SCK | **17** |
| WS | **16** |
| SD | **18** |
| VDD | **3.3V** |
| GND | **GND** |
| L/R | **GND** |

---

## দ্রুত তুলনা

| সিগন্যাল | AP | STA |
|----------|----|-----|
| মাইক I2S | 7, 6, 15 | 17, 16, 18 |
| স্পিকার I2S | 17, 16, 18 | 7, 6, 15 |
| PTT / Vol+ / Vol− | 14 / 12 / 13 → GND | একই |
| HaLow UART | 4 (RX), 5 (TX) | একই |
| ব্যাটারি ADC | 3 | একই |

---

## PDF

প্রিন্ট-ফ্রেন্ডলি: `docs/T-Hallow-Pin-Direction.html` · `docs/T-Hallow-Pin-Direction.pdf`
