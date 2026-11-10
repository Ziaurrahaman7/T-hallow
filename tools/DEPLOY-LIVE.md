# hallow.mmgbd.com — আপলোড ঠিক রাখতে

1. **কোড আপলোড:** সর্বশেষ `lib/store.php` (production upload fix) সার্ভারে দিন।
2. **`data/local.json` সার্ভারে রাখবেন না** — LAN IP বোর্ডকে ভুল URL দেয়।
3. **`data/servers.json`:** `servers.production.json` কপি করুন অথবা সাইট খুললে auto-sync ঠিক করে দেবে।
4. **`data/` ফোল্ডার writable** (index.json, wav/, boards.json)।
5. বোর্ড **দুই ঠিকানা (লোকাল+লাইভ):** `net_config.local.h` = LAN `NET_URL` + `NET_URL_ALT` = `https://hallow.mmgbd.com/...` → AP+STA flash।  
   শুধু লাইভ: `net_config.live.h` (local.h সরান)।

সার্ভার টেস্ট:
`POST https://hallow.mmgbd.com/api/upload.php` body `{"token":"hallow","board":"AP","millis":1,"clips":[]}` → `{"ok":true}`
