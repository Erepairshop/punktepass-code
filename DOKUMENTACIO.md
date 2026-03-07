# PunktePass – Rendszerdokumentáció (állásinterjúhoz)

## Mi a PunktePass?

A PunktePass egy WordPress-alapú, testreszabott hűségpont-rendszer, amelyet fizikai üzletek (kereskedők) számára fejlesztettünk. Az ügyfelek QR-kód szkennelésével pontokat gyűjtenek, amelyeket jutalmakra válthatnak be. A rendszer egyidejűleg három különböző felhasználói csoportnak ad felületet: végfelhasználóknak (pontgyűjtők), kereskedőknek (bolt-tulajdonosok) és az adminnak (platform üzemeltető).

A rendszerhez kapcsolódik egy teljesen különálló javítási megrendelő modul (Repair Form), amelyet kifejezetten szervizboltok (pl. mobiltelefon-javítók) számára terveztünk.

---

## Technikai stack

| Réteg | Technológia |
|---|---|
| Backend | PHP 8.x, WordPress plugin architektúra |
| Adatbázis | MySQL (egyedi táblák: ppv_users, ppv_stores, ppv_points, ppv_rewards, stb.) |
| Frontend | Vanilla JS (moduláris, fájlonként egy funkció), HTML/CSS |
| Real-time | Ably (WebSocket-alapú push, token-autentikációval) |
| Push értesítések | Firebase Cloud Messaging (FCM v1 API) |
| Fizetés | Stripe (webhook-alapú előfizetés-kezelés) |
| AI | Claude (Anthropic) API |
| PWA | Service Worker + manifest.json |
| Többnyelvűség | Saját PPV_Lang osztály (DE / HU / RO / EN / IT) |
| Email | PHPMailer / SMTP (saját osztály) |
| Térkép | Nominatim (OpenStreetMap) geocoding proxy |

---

## Architektúra áttekintés

A rendszer egy WordPress plugin (`punktepass.php`) formájában fut, de szinte minden oldala "standalone" módban jelenik meg – azaz a WordPress sablont megkerüli, és saját teljes HTML-t renderel. Ez teszi lehetővé a mobil-app-szerű megjelenést (bottom navigation, SPA-stílusú navigáció).

Főbb PHP osztályok (mind az `includes/` mappában):

```
class-ppv-core.php          – Plugin inicializálás, DB migrációk, asset verziókezelés
class-ppv-qr.php            – Kassenscanner (QR-szkennelő + kampányok)
class-ppv-scan.php          – Ügyféloldali QR-szkennelés (pontfelvétel)
class-ppv-rewards.php       – Beváltások admin dashboardja
class-ppv-stats.php         – Statisztikák dashboardja
class-ppv-user-level.php    – VIP szintrendszer (Starter → Bronze → Silver → Gold → Platinum)
class-ppv-filiale.php       – Fiókok (több helyszín) kezelése
class-ppv-bonus-days.php    – Bónusznapok (duplázott pont-napok) beállítása
class-ppv-push.php          – Firebase push értesítések
class-ppv-pwa.php           – PWA (Service Worker, manifest)
class-ppv-ably.php          – Ably real-time integráció
class-ppv-stripe.php        – Stripe webhook + előfizetés-kezelés
class-ppv-ai-support.php    – AI support chat (Claude API)
class-ppv-ai-engine.php     – AI motor (max 500 token, Claude API hívás)
class-ppv-repair-core.php   – Repair Form modul routing + DB migrációk
class-ppv-repair-form.php   – Nyilvános javítási megrendelő form
class-ppv-repair-admin.php  – Szervizbolt admin dashboard
class-ppv-repair-email-sender.php – Email értesítők (ügyfél + bolt)
class-ppv-partner.php       – Partner program (jutalék, referral)
class-ppv-partner-dashboard.php   – Partner statisztika oldal (HMAC-tokennel)
class-ppv-lang.php          – Többnyelvűség kezelése
class-ppv-session.php       – Session kezelés (bolt azonosítás)
class-ppv-device-fingerprint.php  – Eszköz-ujjlenyomat (visszaélés-megelőzés)
```

---

## FŐ RENDSZER – PunktePass hűségprogram

### 1. Pontgyűjtés – QR Szkennelés

**Ügyfél oldalán:**
- Az ügyfél regisztrál a PunktePass rendszerbe → egyedi QR-kódot kap
- A QR-kódot bemutatja a boltnál → a kereskedő szkenneri leolvassa
- Pontok automatikusan jóváíródnak az ügyfél fiókján

**Kereskedő oldalán (Kassenscanner):**
- Dedikált scanner oldal (`/qr-center`) – csak a kereskedőnek
- Kamera megnyitása → QR beolvasása → pontok jóváírása egyetlen koppintással
- Kampányok: időszakos bónuszpontok (pl. "2x pont november 1-15.")
- Fiókok (Filialen) közötti váltás – ha a kereskedőnek több helyszíne van

**Technikai részletek:**
- `PPV_QR` osztály trait-alapú architektúrával (Scanner, Campaigns, Users, Devices, REST)
- REST API végpontok (`/ppv/v1/...`) a JS frontendnek
- Valós idejű szinkron: Ably WebSocket csatornán keresztül (a kasszagép azonnal lát minden eseményt)
- Eszköz-ujjlenyomat (`PPV_Device_Fingerprint`) védi a rendszert a visszaéléstől

### 2. VIP Szintrendszer

A felhasználók bolt-specifikusan VIP szintet érnek el a **lifetime scan-szám** alapján (soha nem csökken):

| Szint | Min. scan | Bónusz lehetőség |
|---|---|---|
| Starter | 0–24 | Nincs |
| Bronze | 25–49 | Bolt-specifikus % bónusz |
| Silver | 50–74 | Magasabb % bónusz |
| Gold | 75–99 | Még magasabb % bónusz |
| Platinum | 100+ | Maximum bónusz |

- Minden boltnak saját VIP-beállítása van (engedélyezés + bónusz %-ok)
- A bónusz az egyes szkennelések után adott pontokhoz adódik hozzá
- `PPV_User_Level` osztály statikus konstansokkal, bolt-szintű és globális számítással
- DB migráció: `lifetime_points` oszlop hozzáadva, visszamenőleg kiszámítva a meglévő usereknek

### 3. Jutalmak (Belohnungen / Rewards)

- A kereskedő beállítja: hány pontért milyen jutalmat ad (pl. 10 pont = kávé ingyen)
- Az ügyfél a saját dashboardjáról kezdeményezi a beváltást
- A kereskedő jóváhagyja vagy elutasítja (`/ppv/v1/einloesungen/update`)
- Dashboard statisztikák: mai / heti / havi beváltások + érték
- Bizonylat-generálás havi szinten (PDF)
- Ably real-time support: az admin azonnal látja az új beváltási kérelmet

### 4. Statisztikák

`PPV_Stats` osztály, 5 perces transient cache-sel:

- Napi scan-összesítő grafikon
- Top 5 legaktívabb ügyfél
- Csúcsidők elemzése (mikor van a legtöbb scanner forgalom)
- Trend (heti összehasonlítás)
- Vásárlói visszatérési ráta (konverzió)
- CSV export
- Filiale-tudatos: csak az adott helyszín adatait mutatja

### 5. Bónusznapok (Bonus Days)

- A kereskedő megad bizonyos napokat vagy időszakokat, amikor duplázott (vagy más szorzójú) pontokat ad
- Admin UI (`[ppv_bonus_days]` shortcode)
- Automatikusan érvényesül szkenneléskor

### 6. Fiókok (Filialen) – Több helyszín kezelése

- Egy kereskedői fiókhoz több "fióktelep" rendelhető (`parent_store_id` a DB-ben)
- Minden fióknak saját QR-kódja, saját scanner-munkamenete
- A jutalmakat a főbolt állítja be → a fiókok öröklik
- VIP szintszámítás: a főbolt + fiókok scanjai összeadódnak
- Határ: `max_filialen` (előfizetési csomagtól függ, növelhető adminon keresztül)

### 7. Push Értesítések

`PPV_Push` osztály, Firebase Cloud Messaging V1 API:
- A kereskedő heti 1 push értesítést küldhet az összes ügyfelenek
- JWT-alapú autentikáció (Google service account)
- iOS (APNs), Android és web (Service Worker) egyaránt támogatott
- Heti küldési korlát üzleti logikával

### 8. PWA (Progressive Web App)

- `manifest.json` + `sw.js` (Service Worker) – plugin mappából kiszolgálva
- Az alkalmazás "Add to Home Screen"-ként telepíthető
- Offline cache stratégia
- iOS és Android meta tag-ek
- Firebase Messaging Service Worker a push értesítésekhez

### 9. Real-time (Ably)

- Minden kereskedői old al Ably WebSocket csatornán hallgat
- Esemény típusok: új beváltás, új scan, rendszer üzenetek
- Token-autentikáció: a backend generál ideiglenes token-t (`ajax_ably_auth`)
- Megosztott kapcsolat-manager (`ppv-ably-manager.js`) – egy WebSocket kapcsolat az egész oldalon

### 10. AI Support Chat

`PPV_AI_Support` + `PPV_AI_Engine` osztályok:

**Fő widget** (kereskedők számára):
- Bottom navigációban "Support" gomb nyitja meg
- Ismeri az egész PunktePass rendszert (részletes system prompt)
- Oldal-tudatos: tudja éppen melyik oldalon áll a user
- Többnyelvű válaszok (DE/HU/RO)
- Eszkaláció: ha nem tud válaszolni → `[ESCALATE]` marker → WhatsApp + Email gombok jelennek meg
- Bolt neve session-ből: személyre szabott válaszok

**Repair widget** (szervizbolt adminoknak):
- Lebegő FAB gomb a `/formular/admin` oldalon
- Saját, repair-specifikus system prompt (`get_repair_system_prompt()`)
- `context=repair` paraméterrel vált a backend

**Technikai korlátok:**
- Max 500 token/válasz (gyors, tömör)
- Claude API (Anthropic)
- Mindig frissíteni kell a system promptot, ha új funkció kerül a rendszerbe

### 11. Többnyelvűség

`PPV_Lang` osztály:
- Támogatott nyelvek: Német (DE), Magyar (HU), Román (RO), Angol (EN), Olasz (IT)
- Cookie-alapú nyelvváltás (`ppv_lang`)
- Extra nyelvi fájlok lazy-load módban (pl. `ppv-repair-lang`)
- Az AI chat is fordítva ad választ az adott nyelven

### 12. Stripe Előfizetés-kezelés

`PPV_Stripe` osztály, webhook-alapon:
- REST endpoint: `/punktepass/v1/stripe-webhook`
- Stripe esemény aláírás-validálás (HMAC)
- Előfizetési események kezelése: `customer.subscription.updated`, `invoice.payment_failed`, stb.
- A bolt prémium státusza a `ppv_stores` táblában frissül

### 13. Partner Program

- Partnerek regisztrálnak → egyedi `partner_code`-ot kapnak
- Ajánlott bolton keresztül regisztráló boltok után jutalékot kapnak
- HMAC-tokennel védett partner dashboard oldal (nem kell login)
- Statisztikák: ajánlott boltok száma, beküldött formok, jutalék

---

## REPAIR FORM RENDSZER – Szervizbolt modul

### Áttekintés

Teljesen különálló alrendszer a PunktePass fő plugin-en belül. Egy szervizbolt (pl. mobiltelefon-javító) egyedi javítási megrendelő oldalt kap, ahol az ügyfelek digitálisan adják le a javítandó eszközüket.

**URL struktúra:**
```
/formular                    → Regisztrációs oldal (új bolt regisztrál)
/formular/admin              → Admin dashboard (bejelentkezett szervizbolt)
/formular/admin/login        → Admin bejelentkezés
/formular/{slug}             → Nyilvános javítási form (bolt-specifikus)
/formular/{slug}/datenschutz → Adatvédelmi nyilatkozat
/formular/{slug}/agb         → ÁSZF
/formular/{slug}/impressum   → Impresszum
/formular/partner/dashboard  → Partner statisztika oldal
```

Minden URL a WordPress sablont megkerüli (`init` hook, 1-es prioritás) – teljes standalone PHP HTML.

### A nyilvános javítási form (`/formular/{slug}`)

`PPV_Repair_Form` osztály:

- Teljesen testreszabható arculat (bolt logó, szín, cím, alcím)
- Mezők konfigurálhatók be/ki (JSON: device_brand, device_model, IMEI, PIN-minta, tartozékok, telefon, cím, fotó)
- Visszatérő ügyfelek felismerése: email-cím alapú autocomplete (előkitöltés)
- QR-kód prefill: ha az ügyfél a saját PunktePass QR-kódját szkenneli be, az adatok automatikusan kitöltődnek
- AI-alapú elemzés: az ügyfél leírja a hibát → AI azonnal javasol javítási kategóriát
- PunktePass integráció: a form leadása pontokat is adhat a PunktePass fiókra (konfigurálható)
- Többnyelvű: DE/HU/RO
- Havi form-limit: csomagtól függő, havonta resetelődik (WordPress cron)

**Benyújtás folyamata:**
1. Ügyfél kitölti a formot → AJAX submit
2. Backend menti az adatbázisba
3. Email értesítő a boltnak (új megrendelés)
4. Visszaigazoló email az ügyfélnek

### Repair Admin Dashboard (`/formular/admin`)

`PPV_Repair_Admin` osztály:

**Bejelentkezés:**
- Email/jelszó alapú login
- Google OAuth (Google Sign-In)
- Apple Sign-In
- Munkamenet-alapú autentikáció

**Dashboard funkciók:**
- Aktív megrendelések listája (kártyás nézet)
- Státuszok: Új → Folyamatban → Kész → Lezárva
- Státuszváltás → automatikus email az ügyfélnek
- Megrendelés részletei: ügyfél adatai, eszköz adatai, megjegyzések
- Keresés, szűrés státusz szerint
- AI chat support (lebegő FAB gomb, repair-specifikus tudással)
- Formbeállítások szerkesztése (mezők be/ki, arculat)
- Havi statisztikák

**Visszajelzés rendszer:**
- 24 órával az "elkészült" státusz után automatikus email megy az ügyfélnek
- Kéri az értékelést (Google Review link)
- WordPress cron job, óránkénti futással

### Email értesítők

`PPV_Repair_Email_Sender` osztály, PHPMailer alapokon:

- Új megrendelés → bolt értesítő (összesítő + ügyfél adatai)
- Státuszváltozás → ügyfél értesítő (testreszabható szöveg)
- Visszajelzés kérés → 24h után (Google Review link)
- Összes email HTML-formázott, többnyelvű

### eRepairshop – Önálló microsite

Az `erepairshop/` mappában egy teljesen független PHP alkalmazás is található (nem WordPress, nem plugin):
- Tailwind CSS alapú, modern megjelenés
- Lauingen (DE) városban lévő konkrét javítóbolt weboldalai
- Schema.org strukturált adatok (LocalBusiness)
- Kapcsolatfelvételi form (PHPMailer)
- QR kód generálás (phpqrcode library)
- PIN csere, bejegyzés törlés, "kész" jelölés

---

## Adatbázis struktúra (főbb táblák)

```
ppv_users          – PunktePass végfelhasználók (email, QR kód, lifetime_points)
ppv_stores         – Kereskedők (bolt adatai, VIP beállítások, Stripe ID, max_filialen)
ppv_points         – Pont tranzakciók (user_id, store_id, points, timestamp)
ppv_rewards        – Jutalom definíciók (store_id, pont-határ, jutalomleírás)
ppv_einloesungen   – Beváltási kérelmek (user_id, store_id, státusz, timestamp)
ppv_campaigns      – QR kampányok (bónuszpont időszakok)
ppv_devices        – Regisztrált POS eszközök (scanner terminálok)
ppv_partners       – Partner program (partner_code, jutalék %)
ppv_repair_orders  – Javítási megrendelések (ügyfél + eszköz + státusz)
ppv_repair_stores  – Repair admin fiókok
```

**DB migráció stratégia:**
- `PPV_Core::run_db_migrations()` verziószám alapján fut
- `get_option('ppv_db_migration_version')` tárolja az utolsó migrációt
- Migráció: oszlop hozzáadás, index létrehozás, visszamenőleges adatszámítás

---

## Biztonsági megoldások

- **Nonce-ok**: minden AJAX kéréshez WordPress nonce
- **Permission callbacks**: REST végpontokhoz `PPV_Permissions` osztály (`check_handler`, `check_handler_with_nonce`)
- **Eszköz-ujjlenyomat**: `PPV_Device_Fingerprint` – visszaélés (többszörös pont-felvétel) megelőzése
- **Stripe webhook aláírás**: HMAC-SHA256 validálás
- **Partner dashboard HMAC token**: bejelentkezés nélkül is biztonságos hozzáférés
- **Session-alapú autentikáció**: repair admin szekció
- **Rate limiting**: push értesítésnél heti 1 limit per bolt

---

## Frontend architektúra

A JS kód **moduláris**, fájlonként egy funkció – például:
```
ppv-qr-core.js          – QR scanner alap
ppv-qr-camera.js        – Kamera kezelés
ppv-qr-campaigns.js     – Kampány UI
ppv-ably-manager.js     – Megosztott WebSocket kapcsolat
ppv-rewards.js          – Beváltás UI
ppv-stats.js            – Statisztika grafikonok
ppv-repair-admin.js     – Repair dashboard JS
ppv-ai-support.js       – AI chat widget JS
ppv-bottom-nav.js       – Bottom navigáció
ppv-pwa.js              – PWA telepítési logika
ppv-push-bridge.js      – Firebase push regisztráció
```

**Asset verziókezelés** (`PPV_Core::asset_version()`):
- Fejlesztői módban: `filemtime()` – minden fájlváltozás után új cache
- Produkciós módban: plugin verziószám vagy kényszer-verzió

---

## Deploy workflow

```bash
# Változások kivetele egy adott branchből a szerverre:
git fetch origin [BRANCH] && git checkout FETCH_HEAD -- [FÁJLOK]

# Példa:
git fetch origin claude/review-code-promotion-01LWAg2uwMeFjk2rVRzXUTtk \
  && git checkout FETCH_HEAD -- assets/css/ppv-theme-light.css assets/js/pp-profile-lite.js
```

---

## Ismert technikai kihívások és tanulságok

### Mobile autocomplete – nem megoldott probléma
A `/formular/{slug}` javítási formon az email autocomplete és Nominatim cím-autocomplete **nem működik érintőképernyős eszközökön** (tablet Fully Kiosk, mobil böngészők). Desktopon egérrel működik.

Kipróbált és nem bevált megoldások: touchstart/mousedown események, `<datalist>` natív HTML, document-level click dismiss, fetch helyett XMLHttpRequest, blur timeout növelés.

### WordPress specifikus tanulságok
- `WP_REST_Response` JSON-t ad vissza – nyers HTML kimenethez `echo + exit` kell
- MySQL ENUM: ismeretlen értéknél üres string tárolódik (non-strict módban)
- WordPress AJAX: mind `wp_ajax_`, mind `wp_ajax_nopriv_` kell nyilvános endpointokhoz
- `INFORMATION_SCHEMA` / `SHOW COLUMNS` lekérdezések drágák – `get_option()` flag-gel kell cache-elni

### AI System Prompt karbantartás
Az AI support chat a system prompt-ból tudja a rendszer összes funkcióját. **Ha új funkció kerül be, a system promptot is frissíteni kell** (`get_system_prompt()` és `get_repair_system_prompt()` metódusokban).

---

## Összefoglalás – Mit fejlesztettem?

Ez egy **komplex, production-szintű SaaS rendszer**, amelyet a következő területeken dolgoztam:

1. Teljes WordPress plugin architektúra megtervezése és implementálása (~90 PHP osztály)
2. Egyedi hűségpont-rendszer: QR-szkennelés, VIP szintek, jutalmak, bónusznapok
3. Standalone javítási megrendelő rendszer (külön alrendszer, saját routing-gal)
4. Real-time kommunikáció Ably WebSocket segítségével
5. Firebase Push értesítés integráció (FCM V1 API)
6. AI support chat integrálása Claude API-val (context-aware, többnyelvű)
7. PWA implementáció (Service Worker, manifest, offline cache)
8. Stripe fizetési webhook kezelés
9. Többnyelvű (DE/HU/RO/EN) rendszer saját fordítási motorral
10. Multi-location (fiókok) kezelése adatbázis-migrációkkal
11. Biztonsági réteg: nonce, HMAC, eszköz-ujjlenyomat, rate limiting
12. Partner program jutalék-kalkulációval és HMAC-védett dashboarddal
