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

- [ ] **Scan ablak növelése** - `class-ppv-scan.php:477`
  - 5 sec → 10-15 sec (hálózati latency miatt)

- [ ] **Device fingerprint validálás** - `class-ppv-scan.php:172`
  - Hossz és formátum ellenőrzés
  - Stored XSS megelőzése

- [ ] **REST NONCE validálás** - `class-ppv-rewards.php:35`
  - CSRF védelem hiányzik
  - Minden state-changing endpoint-ra kell

- [x] **Rate limiting aktiválás** - `trait-ppv-qr-rest.php`, `class-ppv-redeem.php`
  - ~~Létezik de nincs használva!~~
  - ✅ JAVÍTVA: Sikeres scan 3/perc, sikertelen 20/perc, Redeem 5/perc per IP

- [ ] **Birthday bonus race fix** - `class-ppv-scan.php:432-449`
  - Atomic update-re átírni

- [ ] **GPS validálás** - `class-ppv-scan.php:173-174`
  - Store geofence ellenőrzés
  - Fake location megelőzése

---

## ALACSONY (1 hónapon belül)

- [x] **Database indexek** - `database/add-indexes.sql`
  - ~~`ppv_points (user_id, store_id, created)`~~
  - ✅ SQL fájl elkészítve - futtatni kell manuálisan!

- [ ] **Audit logging** - Security
  - Minden kritikus művelet logolása
  - Failed attempts tracking

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

---

## Megjegyzések

```
Javítás után tesztelni:
1. Manuális teszt a fix-re
2. Regresszió teszt (nem romlott el más)
3. Load teszt race condition-re
```
