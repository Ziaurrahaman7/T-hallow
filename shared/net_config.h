#pragma once

// NET_ON 1 করলে বোর্ড কথা সেভ করে। Wi-Fi পেলে ড্যাশবোর্ডে পাঠায়।
// নাম খালি থাকলে রেডিও চালু হয় না। ভয়েস আগের মতোই চলে।
#ifndef NET_ON
#define NET_ON 1
#endif

#define NET_SSID "Redmi 13C"
#define NET_PASS "1234567a"
#define NET_SSID2 "Shefa Home"
#define NET_PASS2 "lkjhgfdsa"
// হটস্পট/মোবাইল ডেটা: লাইভ সার্ভার আগে; Laragon শুধু একই Wi-Fi-তে
#define NET_URL "https://hallow.mmgbd.com/api/upload.php"
#define NET_URL_ALT "http://192.168.0.103/T-hallow/api/upload.php"
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

