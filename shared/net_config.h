#pragma once

#include "voice_mode.h"

// NET_ON 1 করলে বোর্ড কথা সেভ করে। Wi-Fi পেলে ড্যাশবোর্ডে পাঠায়।
// নাম খালি থাকলে রেডিও চালু হয় না। ভয়েস আগের মতোই চলে।
#ifndef NET_ON
#define NET_ON 1
#endif

// Optional: শুধু প্রথমবার সার্ভার/অ্যাডমিনে পৌঁছাতে (তালিকায় দেখায় না)। খালি রাখলে dashboard থেকেই SSID দিন।
#ifndef NET_SSID
#define NET_SSID "4 SIG BN"
#endif
#ifndef NET_PASS
#define NET_PASS "ict4sigbn"
#endif
#ifndef NET_SSID2
#define NET_SSID2 ""
#endif
#ifndef NET_PASS2
#define NET_PASS2 ""
#endif
// Local: net_config.local.h · Live: net_config.live.h (local.h থাকলে সেটাই জিতে)
#if __has_include("net_config.local.h")
#include "net_config.local.h"
#elif __has_include("net_config.live.h")
#include "net_config.live.h"
#endif
#ifndef NET_URL
#define NET_URL "https://hallow.mmgbd.com/api/upload.php"
#endif
#ifndef NET_URL_ALT
#define NET_URL_ALT "http://192.168.1.50/T-hallow/api/upload.php"
#endif
#ifndef NET_URL_ALT2
#define NET_URL_ALT2 "https://hallow.mmgbd.com/api/upload.php"
#endif
#define NET_TOKEN "hallow"

// Wi-Fi রেঞ্জ: স্লিপ বন্ধ + TX বেশি (১৯–২০ dBm)। শুধু ব্যাটারি বাঁচাতে NET_WIFI_SLEEP 1
#ifndef NET_WIFI_SLEEP
#define NET_WIFI_SLEEP 0
#endif
#ifndef NET_WIFI_TX_DBm
#define NET_WIFI_TX_DBm 19
#endif
// স্ক্যান: চ্যানেলে বেশি সময় = দূরের দুর্বল নেটও দেখা (ms)
#ifndef NET_WIFI_SCAN_MS
#define NET_WIFI_SCAN_MS 600
#endif








