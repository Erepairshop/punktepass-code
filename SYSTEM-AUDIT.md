# PunktePass Rendszer Audit - Fejlesztési Javaslatok

**Audit dátum:** 2025-12-01
**Auditor:** Claude Code
**Verzió:** 1.0

---

## Jelenlegi Állapot Összefoglaló

| Metrika | Érték |
|---------|-------|
| PHP osztályok | 98 |
| PHP sorok | 41,818+ |
| JavaScript fájlok | 57+ |
| CSS fájlok | 10+ |
| Adatbázis táblák | 40+ |
| REST végpontok | 30+ |
| Támogatott nyelvek | DE, HU, RO |
| Összméret | ~69MB |

---

## 1. MARKETING FEJLESZTÉSEK

### Már megvan:
- [x] QR plakátok, szórólapok (Dompdf)
- [x] Referral rendszer
- [x] WhatsApp Cloud API integráció
- [x] Google Review kérés pont bónusszal
- [x] Heti email jelentések
- [x] ROI kalkulátor landing page (`/rechner`)

### Javasolt fejlesztések:

#### A) Push Notification Rendszer
```
Prioritás: MAGAS
Befektetés: Közepes
Fájlok: Új class-ppv-push.php, service-worker.js bővítés
```
- Web Push API integráció (FCM/OneSignal)
- Kampány alapú értesítések (új jutalom, pont lejárat, születésnap)
- Személyre szabott időzítés (user aktivitás alapján)
- Opt-in/opt-out kezelés GDPR-nak megfelelően

#### B) Gamification Bővítés
```
Prioritás: MAGAS
Befektetés: Közepes
Fájlok: Új class-ppv-badges.php, ppv_badges tábla
```
- Badge/Jelvény rendszer (első vásárlás, 10. scan, hűséges tag)
- Streak bónusz vizualizáció (7 napos sorozat = extra pont)
- Leaderboard - havi toplista az üzletben
- Challenge-ek - időkorlátos kihívások ("Scannelj 5x ezen a héten")

#### C) Social Sharing
```
Prioritás: KÖZEPES
Befektetés: Alacsony
Fájlok: ppv-user-dashboard.js bővítés
```
- Instagram/Facebook Story sablon generátor
- "Nyertem X pontot!" megosztható kártya
- QR kód megosztás social platformokon
- Viral loop - pont bónusz megosztásért

#### D) Email Marketing Automatizáció
```
Prioritás: KÖZEPES
Befektetés: Közepes
Fájlok: class-ppv-email-automation.php
```
- Comeback kampány - 30/60/90 nap inaktivitás után
- Pont lejárat figyelmeztetés (ha bevezetésre kerül)
- Születésnapi automatikus kupon
- VIP szint elérés gratulációk

#### E) Landing Page Optimalizáció
```
Prioritás: MAGAS
Befektetés: Alacsony
Fájlok: class-ppv-roi-calculator.php
```
- A/B tesztelés a `/rechner` ROI kalkulátornál
- Video testimonials szekció
- Live demo lehetőség
- Case study-k (sikeres üzletek)

---

## 2. BIZTONSÁGI FEJLESZTÉSEK

### Már megvan (JÓVÁHAGYVA):
- [x] SQL injection védelem (prepared statements)
- [x] XSS védelem (escape függvények)
- [x] CSRF védelem (WordPress nonce)
- [x] Rate limiting (3 scan/perc, 20 failed/perc)
- [x] Device fingerprinting (SHA256)
- [x] Secure cookie-k (HttpOnly, SameSite)
- [x] Security headers (X-Frame-Options, etc.)
- [x] Fingerprint similarity scoring (>80% auto-update)
- [x] Local FingerprintJS hosting
- [x] Local QR Scanner hosting

### Hiányzó/Javasolt fejlesztések:

#### A) GPS Geofence Validáció
```
Prioritás: KRITIKUS
Befektetés: Közepes
Fájlok: trait-ppv-qr-rest.php, class-ppv-vip-settings.php
```
- Scan csak az üzlet 100m körzetében érvényes
- Opcionális be/kikapcsolás üzletenként
- GPS spoof detection (sebesség ellenőrzés)
- Haversine formula távolságszámításhoz

#### B) Device Request Cooldown
```
Prioritás: KÖZEPES
Befektetés: Alacsony
Fájlok: class-ppv-device-fingerprint.php
```
- Ugyanarról az eszközről max 1 új device kérés / 7 nap
- Spam prevention
- Admin felületen konfigurálható

#### C) Two-Factor Authentication (2FA)
```
Prioritás: KÖZEPES
Befektetés: Közepes
Fájlok: Új class-ppv-2fa.php
```
- Handler/Admin fiókok kötelező 2FA
- TOTP (Google Authenticator) támogatás
- SMS fallback (opcionális)

#### D) Audit Log Bővítés
```
Prioritás: KÖZEPES
Befektetés: Alacsony
Fájlok: ppv_audit_log tábla, class-ppv-audit.php
```
- Minden admin művelet logolása
- IP + User Agent + Timestamp
- Export funkció (CSV/JSON)
- 90 napos retention

#### E) Pont Lejárat Rendszer
```
Prioritás: ALACSONY
Befektetés: Közepes
Fájlok: class-ppv-points-expiry.php
```
- Opcionális pont érvényesség (pl. 365 nap)
- Figyelmeztetések lejárat előtt
- Grace period

#### F) API Rate Limiting Bővítés
```
Prioritás: KÖZEPES
Befektetés: Alacsony
Fájlok: class-ppv-permissions.php
```
- Per-endpoint rate limit
- API key alapú kvóták (store-onként)
- DDoS protection (Cloudflare integráció)

---

## 3. SCAN & FUNKCIÓ BŐVÍTÉSEK

### Jelenlegi scan képességek:
- [x] QR kód generálás/olvasás
- [x] Device fingerprinting
- [x] Real-time Ably WebSocket
- [x] Offline POS dock
- [x] Virtuális kártya (user QR)

### Javasolt új funkciók:

#### A) NFC Scan Támogatás
```
Prioritás: MAGAS
Befektetés: Közepes
Fájlok: class-ppv-nfc.php, ppv-nfc.js
```
- Web NFC API (Chrome Android 89+)
- NFC tag-ek az üzletben
- Gyorsabb scan élmény
- Kompatibilis telefonokon automatikus felismerés

#### B) Apple Wallet / Google Wallet Export
```
Prioritás: MAGAS
Befektetés: Magas
Fájlok: class-ppv-wallet.php
```
- PKPass generálás (Apple Wallet)
- Google Pay Pass integráció
- Pont egyenleg megjelenítés a kártyán
- Auto-update API-n keresztül

#### C) Kasszaintegráció (POS API)
```
Prioritás: KÖZEPES
Befektetés: Magas
Fájlok: class-ppv-pos-integration.php
```
- Direkt kasszaszoftver integráció
- Automatikus pont jóváírás fizetéskor
- Vásárlás összeg alapú pont számítás
- Támogatott rendszerek: iZettle, SumUp, Lightspeed

#### D) Számla/QR Scan
```
Prioritás: KÖZEPES
Befektetés: Közepes
Fájlok: class-ppv-receipt-scan.php
```
- Blokk/számla QR kód olvasás (IKEA stílus)
- OCR alapú összeg felismerés
- Automatikus pont kalkuláció
- Csalásmegelőzés (duplicate check)

#### E) Beacon Alapú Auto Check-in
```
Prioritás: ALACSONY
Befektetés: Magas
Fájlok: class-ppv-beacon.php
```
- Bluetooth beacon az üzletben
- Automatikus "belépés" detektálás
- Passive pont gyűjtés lehetőség

#### F) Multi-Store Csoportok
```
Prioritás: KÖZEPES
Befektetés: Közepes
Fájlok: class-ppv-store-groups.php
```
- Üzletláncok támogatása
- Közös pont egyenleg több üzletben
- Központi admin felület
- Franchise modell

#### G) Kupon Rendszer
```
Prioritás: MAGAS
Befektetés: Közepes
Fájlok: class-ppv-coupons.php, ppv_coupons tábla
```
- Egyedi kupon kódok generálása
- QR + alfanumerikus formátum
- Felhasználási limit
- Időkorlát
- Kombinálható pontokkal

#### H) Appointment Booking
```
Prioritás: ALACSONY
Befektetés: Magas
Fájlok: class-ppv-booking.php
```
- Időpontfoglalás integráció
- Pont bónusz foglalásért
- Reminder értesítések

#### I) Analytics Dashboard Bővítés
```
Prioritás: KÖZEPES
Befektetés: Közepes
Fájlok: class-ppv-analytics-api.php bővítés
```
- Customer Lifetime Value (CLV) kalkuláció
- Churn prediction
- Cohort analysis
- A/B teszt eredmények
- Export funkciók (PDF report)

---

## Prioritás Mátrix

| Fejlesztés | Marketing | Biztonság | Funkció | Prioritás |
|------------|:---------:|:---------:|:-------:|:---------:|
| GPS Geofence | | ★★★ | | KRITIKUS |
| Push Notifications | ★★★ | | | MAGAS |
| Gamification | ★★★ | | | MAGAS |
| Apple/Google Wallet | ★★ | | ★★★ | MAGAS |
| Kupon Rendszer | ★★★ | | ★★ | MAGAS |
| 2FA Admin | | ★★★ | | KÖZEPES |
| NFC Scan | | | ★★★ | KÖZEPES |
| Device Cooldown | | ★★ | | KÖZEPES |
| Social Sharing | ★★ | | | KÖZEPES |

---

## Javasolt Implementációs Sorrend

### Fázis 1 - Azonnali (Biztonság)
1. GPS Geofence validáció
2. Device request cooldown
3. Fingerprint change notification

**Cél:** Csalásmegelőzés erősítése

### Fázis 2 - Rövid táv (Marketing + UX)
4. Push Notifications
5. Gamification (badge-ek)
6. Social Sharing

**Cél:** User engagement növelése

### Fázis 3 - Közép táv (Prémium funkciók)
7. Apple/Google Wallet
8. Kupon rendszer
9. NFC támogatás

**Cél:** Prémium élmény

### Fázis 4 - Hosszú táv (Skálázás)
10. Kasszaintegráció
11. Multi-store csoportok
12. Advanced analytics

**Cél:** Enterprise funkciók

---

## Technikai Adósság

| Probléma | Prioritás | Megjegyzés |
|----------|-----------|------------|
| jQuery eltávolítás | Alacsony | Sok fájlt érint |
| Test suite építése | Közepes | PHPUnit + Jest |
| Dark mode külön fájlba | Alacsony | Opcionális |
| Nagy JS fájlok darabolása | Közepes | ppv-qr.js 84KB |

---

## Changelog

| Dátum | Változás |
|-------|----------|
| 2025-12-01 | Kezdeti audit létrehozása |

