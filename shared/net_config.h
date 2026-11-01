#pragma once

// NET_ON 1 করলে বোর্ড কথা সেভ করে। Wi-Fi পেলে ড্যাশবোর্ডে পাঠায়।
// নাম খালি থাকলে রেডিও চালু হয় না। ভয়েস আগের মতোই চলে।
#ifndef NET_ON
#define NET_ON 1
#endif

#define NET_SSID "Shefa Home"
#define NET_PASS "lkjhgfdsa"
// প্রাথমিক: Laragon (PC IP বদলালে এটা + Wi-Fi সেটিংস আপডেট)
#define NET_URL "http://192.168.0.103/T-hallow/api/upload.php"
#define NET_URL_ALT "https://hallow.mmgbd.com/api/upload.php"
#define NET_TOKEN "hallow"

