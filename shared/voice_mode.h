#pragma once

// 1 = শুধু মাইক → HaLow → স্পিকার (Wi-Fi/PTT/dashboard বন্ধ)। Production এ 0 করুন।
// 0 = Wi-Fi + ড্যাশবোর্ড | 1 = শুধু HaLow ভয়েস টেস্ট
#ifndef VOICE_SIMPLE
#define VOICE_SIMPLE 0
#endif

#if VOICE_SIMPLE

#ifndef NET_ON
#define NET_ON 0
#else
#undef NET_ON
#define NET_ON 0
#endif

#ifndef PTT_REQUIRED
#define PTT_REQUIRED 0
#else
#undef PTT_REQUIRED
#define PTT_REQUIRED 0
#endif

#ifndef PTT_HALF_DUPLEX
#define PTT_HALF_DUPLEX 0
#endif

#ifndef VOL_BUTTONS
#define VOL_BUTTONS 0
#endif

#ifndef VOICE_TEST_AUTO
#define VOICE_TEST_AUTO 0
#endif

#ifndef VOICE_PLAY_CLEAR
#define VOICE_PLAY_CLEAR 1
#endif

// Auto TX গেট (STA-তে বেশি strict = কম নয়েজ TX, AP ডাউনলিংক স্পেস)
#ifndef VOICE_TX_PEAK_MIN
#define VOICE_TX_PEAK_MIN 280
#endif
#ifndef VOICE_TX_STRONG_MIN
#define VOICE_TX_STRONG_MIN 5
#endif

#ifndef PLAY_GAIN_NUM
#define PLAY_GAIN_NUM 14
#endif

#ifndef PLAY_GAIN_DEN
#define PLAY_GAIN_DEN 8
#endif

// rx/play 0 থাকলে মাঝে মাঝে বীপ — স্পিকার শুনে যাচাই (0 = বন্ধ)
#ifndef SPEAKER_DIAG_PING
#define SPEAKER_DIAG_PING 1
#endif

// AP বুটে 17/16/18 + 7/6/15 দুটো বীপ (শুধু ap.ino) — 0 করলে এক বীপ
#ifndef AP_SPEAKER_SELF_TEST
#define AP_SPEAKER_SELF_TEST 1
#endif

#endif

// অটো VAD: ছোট শব্দ কাটা — শুধু জোর/টেকা কথা TX।
#ifndef VOICE_AUTO_SAMPLE_MIN
#define VOICE_AUTO_SAMPLE_MIN 500
#endif
#ifndef VOICE_AUTO_STRONG_MIN
#define VOICE_AUTO_STRONG_MIN 12
#endif
#ifndef VOICE_AUTO_SHAPED_PEAK
#define VOICE_AUTO_SHAPED_PEAK 960
#endif
#ifndef VOICE_AUTO_TX_PEAK
#define VOICE_AUTO_TX_PEAK 520
#endif
#ifndef VOICE_AUTO_TX_STRONG
#define VOICE_AUTO_TX_STRONG 10
#endif
#ifndef VOICE_AUTO_LOUD_MIN
#define VOICE_AUTO_LOUD_MIN 540
#endif
#ifndef VOICE_AUTO_LOUD_CNT
#define VOICE_AUTO_LOUD_CNT 5
#endif
#ifndef VOICE_PLAY_RX_PEAK_MIN
#define VOICE_PLAY_RX_PEAK_MIN 40
#endif
#ifndef VOICE_PLAY_BOOST_BELOW
#define VOICE_PLAY_BOOST_BELOW 380
#endif
#ifndef VOICE_PLAY_BOOST_NUM
#define VOICE_PLAY_BOOST_NUM 16
#endif
#ifndef VOICE_PLAY_BOOST_DEN
#define VOICE_PLAY_BOOST_DEN 10
#endif
#ifndef VOICE_NET_TX_PEAK
#define VOICE_NET_TX_PEAK 880
#endif
#ifndef VOICE_NET_LOUD_MIN
#define VOICE_NET_LOUD_MIN 7
#endif

#ifndef VOICE_PLAY_PEAK_MIN
#define VOICE_PLAY_PEAK_MIN 8
#endif
#ifndef VOICE_RX_BODY_DEDUP
#define VOICE_RX_BODY_DEDUP 0
#endif
#ifndef VOICE_AIR_RELAX_OK
#define VOICE_AIR_RELAX_OK 1
#endif
