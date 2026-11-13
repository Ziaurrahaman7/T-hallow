#pragma once

// STA MAX98357 — ডিফল্ট ডক্স: BCLK=7 LRC=6 DIN=15
// শুধু PCB-তে BCLK/LRC উল্টো থাকলে নিচে 6/7 সোয়াপ করুন
#ifndef STA_I2S_BCLK
#define STA_I2S_BCLK 7
#endif
#ifndef STA_I2S_LRC
#define STA_I2S_LRC 6
#endif
#ifndef STA_I2S_DIN
#define STA_I2S_DIN 15
#endif
