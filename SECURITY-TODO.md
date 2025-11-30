# PunktePass Security Audit - TODO Lista

**Audit dátum:** 2025-11-29
**Auditor:** Claude Code

---

## KRITIKUS (Azonnal!) ✅ KÉSZ

- [x] **SQL Injection fix** - `class-ppv-my-points-rest.php:182,191`
  - `$user_id` közvetlenül SQL-be kerül `prepare()` nélkül
  - ✅ JAVÍTVA: `$wpdb->prepare()` használata minden query-ben

- [x] **Privilege Escalation fix** - `class-ppv-redeem.php:89-101`
  - User bármilyen `user_id`-t küldhet POST-ban
  - ✅ JAVÍTVA: Store history ellenőrzés - user csak saját store-jához tartozó pontokat válthat

- [x] **Race Condition fix** - `class-ppv-redeem.php:116-220`
  - Nincs tranzakció védelem
  - ✅ JAVÍTVA: START TRANSACTION + FOR UPDATE lock + COMMIT/ROLLBACK

---

## MAGAS (Sürgős - 1 héten belül)

- [x] **Max pont limit** - `trait-ppv-qr-rest.php`, `class-ppv-rest.php`, `class-ppv-rewards-management.php`
  - ~~Store owner bármennyi pontot beállíthat~~
  - ✅ JAVÍTVA: Max 20 pont/scan limit + Admin UI validáció + HTML max attribútum

- [x] **Duplikáció check javítás** - `class-ppv-redeem.php:133-146`
  - ~~Csak 1 perces ablak, `reward_title` alapján~~
  - ✅ JAVÍTVA: `reward_id` + 5 perces ablak (race condition fix része)

- [x] **Session validáció** - `class-ppv-permissions.php:46-84, 562-578`
  - ~~Session user nincs validálva (létezik-e még, aktív-e)~~
  - ✅ JAVÍTVA: DB ellenőrzés user/store létezik-e és aktív-e

- [x] **Secure cookie flags** - `class-ppv-user-settings.php`, `class-ppv-session.php`
  - ~~Token cookie nincs HttpOnly/Secure/SameSite~~
  - ✅ JAVÍTVA: Secure + SameSite=Lax minden cookie-n

---

## KÖZEPES (2 héten belül)

- [x] **Scan ablak növelése** - `trait-ppv-qr-rest.php:751`
  - ~~5 sec → 10-15 sec (hálózati latency miatt)~~
  - ✅ JAVÍTVA: 10 másodperc (2025-11-29)

- [ ] **Device fingerprint validálás** - `class-ppv-scan.php:172`
  - Hossz és formátum ellenőrzés
  - Stored XSS megelőzése

- [ ] **REST NONCE validálás** - `class-ppv-rewards.php:35`
  - CSRF védelem hiányzik
  - Minden state-changing endpoint-ra kell

- [x] **Rate limiting aktiválás** - `trait-ppv-qr-rest.php`, `class-ppv-redeem.php`
  - ~~Létezik de nincs használva!~~
  - ✅ JAVÍTVA: Sikeres scan 3/perc, sikertelen 20/perc, Redeem 5/perc per IP

- [x] **Birthday bonus race fix** - `trait-ppv-qr-rest.php:656-663`
  - ~~Atomic update-re átírni~~
  - ✅ MÁR JAVÍTVA VOLT: atomic UPDATE with WHERE clause

- [ ] **GPS validálás** - `class-ppv-scan.php:173-174`
  - Store geofence ellenőrzés
  - Fake location megelőzése

---

## 📱 DEVICE & FINGERPRINT FEJLESZTÉSEK (2025-11-30)

### 🔴 MAGAS Prioritás

- [x] **1. Local FingerprintJS hosting** ✅ (2025-11-30)
  - CDN függőség megszüntetése (`cdn.jsdelivr.net`)
  - `assets/js/vendor/fp.min.js` lokális tárolás
  - ✅ JAVÍTVA: FingerprintJS v4.6.2 lokálisan
  - **Fájlok:** `class-ppv-user-signup.php`, `class-ppv-login.php`, `ppv-login.js`

- [x] **2. Local QR Scanner hosting** ✅ (2025-11-30)
  - CDN függőség megszüntetése (`unpkg.com/qr-scanner`)
  - `assets/js/vendor/qr-scanner.umd.min.js` lokális tárolás
  - ✅ JAVÍTVA: QR Scanner + Worker lokálisan, PPV_STORE_DATA.plugin_url hozzáadva
  - **Fájlok:** `class-ppv-qr.php`, `ppv-qr-camera.js`

- [x] **3. Auto fingerprint update** ✅ (2025-11-30)
  - Ha fingerprint változott de hasonló (>80%) → auto frissítés
  - User-nek ne kelljen manuálisan "Fingerprint frissítése"
  - Similarity score implementálás (súlyozott komponens összehasonlítás)
  - ✅ JAVÍTVA: `calculate_fingerprint_similarity()`, `find_similar_device()` metódusok
  - **Fájlok:** `class-ppv-device-fingerprint.php`, `ppv-qr-camera.js`

- [ ] **4. GPS block opció (store-onként)**
  - Új oszlop: `ppv_stores.gps_block_enabled` (default: 0)
  - Ha enabled → gyanús GPS = BLOCK (nem csak log)
  - Store owner dönthet: csak logol vagy blokkol is
  - **Fájlok:** `trait-ppv-qr-rest.php`, VIP settings UI

### 🟡 KÖZEPES Prioritás

- [ ] **5. Device request cooldown**
  - Max 1 device request / 7 nap
  - Spam prevention
  - **Fájlok:** `class-ppv-device-fingerprint.php`

- [ ] **6. Fingerprint change notification**
  - Ha fingerprint változott → toast üzenet
  - "Eszköz fingerprint változott - kattints a frissítéshez"
  - **Fájlok:** `ppv-qr-camera.js`, `trait-ppv-qr-devices.php`

- [ ] **7. Legacy mobile scanner cleanup**
  - Store-level `scanner_type` megszüntetése
  - Csak per-device `mobile_scanner` flag maradjon
  - Backward compatibility check
  - **Fájlok:** `class-ppv-device-fingerprint.php`, `trait-ppv-qr-rest.php`

### 🟢 ALACSONY Prioritás

- [ ] **8. Device activity dashboard**
  - Utolsó 7 nap scan-ek eszközönként
  - Gyanús aktivitás highlight
  - Admin UI bővítés
  - **Fájlok:** új admin page

- [ ] **9. Fingerprint similarity score**
  - 0-100% hasonlóság számítás
  - 80%+ = valószínűleg ugyanaz az eszköz
  - Jobb fraud detection
  - **Fájlok:** `class-ppv-device-fingerprint.php`

---

## ALACSONY (1 hónapon belül)

- [x] **Database indexek** - `database/add-indexes.sql`
  - ~~`ppv_points (user_id, store_id, created)`~~
  - ✅ SQL fájl elkészítve - futtatni kell manuálisan!

- [x] **Audit logging** - Security
  - ~~Minden kritikus művelet logolása~~
  - ✅ MÁR MEGVAN: `ppv_log()` minden kritikus helyen

---

## KÉSZ

- [x] XSS fix - `ppv-qr-ui.js` (escapeHtml hozzáadva)
- [x] XSS fix - `ppv-user-dashboard.js` (escapeHtml hozzáadva)
- [x] Archivált régi fájl - `ppv-qr.js` → `_archive/`
- [x] SQL Injection fix - `class-ppv-my-points-rest.php` (2025-11-29)
- [x] Privilege Escalation fix - `class-ppv-redeem.php` (2025-11-29)
- [x] Race Condition fix - `class-ppv-redeem.php` (2025-11-29)
- [x] Secure cookie flags - `class-ppv-user-settings.php`, `class-ppv-session.php` (2025-11-29)
- [x] Rate limiting - scan/redeem endpoints (2025-11-29)
- [x] Max pont limit - 20 pont/scan (2025-11-29)
- [x] Session validáció - `class-ppv-permissions.php` (2025-11-29)
- [x] Database indexek - `database/add-indexes.sql` (2025-11-29)
- [x] Scan ablak növelése - 5→10 sec `trait-ppv-qr-rest.php` (2025-11-29)
- [x] Birthday bonus race fix - már megvolt (2025-11-29)
- [x] Audit logging - már megvolt `ppv_log()` (2025-11-29)

---

## Megjegyzések

```
Javítás után tesztelni:
1. Manuális teszt a fix-re
2. Regresszió teszt (nem romlott el más)
3. Load teszt race condition-re
```
