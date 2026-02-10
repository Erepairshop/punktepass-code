# PunktePass - Projekt Információk Claude-nak

## 🔑 KRITIKUS: Authentication Rendszer

**NEM WordPress felhasználók vannak!**

- ❌ **NINCS** `wp_users` tábla használat
- ✅ **VAN** custom `wp_ppv_users` tábla
- ✅ Session/token alapú bejelentkezés
- ✅ QR kód alapú authentication
- ✅ User ID tárolás: `$_SESSION['ppv_user_id']`
- ✅ Token tárolás: `$_SESSION['ppv_user_token']`

### User adatok lekérése:
```php
PPV_User_Settings::get_ppv_user_id()  // Session-ból vagy token-ből
PPV_User_Settings::get_ppv_user($user_id)  // DB lekérdezés
```

## 🏗️ Projekt Struktúra

### Fő plugin: PunktePass
- **Cél**: Pontgyűjtő/hűségkártya rendszer
- **Főbb funkciók**:
  - QR kód alapú bejelentkezés
  - Pont gyűjtés üzletekben
  - Belépések követése
  - Jutalmak/kuponok rendszer
  - User settings/profil kezelés

### Könyvtárszerkezet:
```
punktepass-code/
├── includes/
│   ├── class-ppv-user-settings.php    # User Settings oldal
│   ├── class-ppv-user-dashboard.php   # Dashboard
│   ├── class-ppv-bottom-nav.php       # Alsó navigáció
│   ├── class-ppv-session.php          # Session kezelés
│   └── lang/                          # Nyelvek (DE, HU, RO)
├── assets/
│   ├── css/
│   │   ├── ppv-theme-light.css       # 341KB minified global CSS
│   │   ├── ppv-user-settings.css     # 15KB dedikált settings CSS
│   │   └── ...
│   └── js/
│       ├── ppv-user-settings.js
│       ├── ppv-theme-handler.js
│       └── ...
└── punktepass.php                     # Main plugin file
```

## 🎨 CSS Rendszer - FONTOS!

### Probléma: Nagy minified CSS
- `ppv-theme-light.css` = **341KB**, egyetlen sorban, nehezen karbantartható
- Megoldás: Dedikált CSS fájlok külön oldalakhoz

### CSS Whitelist rendszer
**punktepass.php** tartalmaz egy whitelist-et:
```php
$whitelist = [
    'ppv-theme-light',
    'ppv-user-settings',  // User settings oldal
    'ppv-handler',
    'remix-icons',
    // ...
];
```
⚠️ **Új CSS hozzáadásakor mindig frissítsd a whitelist-et!**

### Asset versioning:
```php
PPV_Core::asset_version(PPV_PLUGIN_DIR . 'assets/css/file.css')
```
Ez a fájl módosítási idejét használja verzióként → cache busting!

## 🚨 ELEMENTOR PROBLÉMA

**⚠️ KRITIKUS: Elementor shortcode widget escape-eli a kimenetet!**

### Probléma:
- Elementor Shortcode widget **HTML escape-eli** a PHP kimenetét
- Az inputok **nem jelennek meg a DOM-ban** (document.querySelector visszaad null-t)
- Minden HTML szöveggé konvertálódik

### Megoldás:
✅ **KÖZVETLENÜL használd a PHP shortcode-ot**, ne Elementor widget-et
✅ Vagy használj **Elementor HTML widget-et** raw HTML kimenethez

### Használat:
```php
// WordPress oldal template-ben:
<?php echo do_shortcode('[ppv_user_settings]'); ?>

// VAGY közvetlenül hívd a függvényt:
<?php echo PPV_User_Settings::render_settings_page(); ?>
```

## 📄 Főbb Oldalak

### /einstellungen (User Settings)
- **Shortcode**: `[ppv_user_settings]`
- **PHP Class**: `PPV_User_Settings`
- **CSS**: `ppv-user-settings.css`
- **JS**: `ppv-user-settings.js`
- **Tartalom**:
  - Avatar upload
  - Személyes adatok (név, email, születésnap)
  - Jelszó változtatás
  - Cím
  - Értesítési beállítások (toggle switches)
  - WhatsApp notification (telefonszám)
  - Adatvédelmi beállítások
  - Eszközök kezelése
  - Fiók törlés
  - **FAQ szekció** (accordion)

### /meine-punkte (Dashboard)
- Pontok megjelenítése
- QR kód
- Üzletek listája
- Jutalmak

### /belohnungen (Rewards)
- Kuponok
- Ajándékok

## 🔧 Git Workflow

### Branch naming:
```bash
claude/feature-name-sessionId
```
Példa: `claude/scanner-login-name-support-fpzvP`

### FONTOS: Push csak claude/* branch-ekre!
```bash
git push -u origin claude/branch-name
```
⚠️ A branch névnek **claude/** prefixszel kell kezdődnie!

### Commit message formátum:
```
FIX: Toggle switch layout improved
ADD: FAQ section to user settings
REMOVE: Debug code from production
RESTORE: December 6 version with FAQ
```

## 🚀 Deploy Parancs - SSH

### Formátum:
```bash
git fetch origin [BRANCH] && git checkout FETCH_HEAD -- [FILES]
```

### Példa (egy fájl):
```bash
git fetch origin claude/scanner-login-name-support-fpzvP && git checkout FETCH_HEAD -- includes/class-ppv-user-settings.php
```

### Példa (több fájl):
```bash
git fetch origin claude/scanner-login-name-support-fpzvP && git checkout FETCH_HEAD -- includes/class-ppv-user-settings.php assets/css/ppv-user-settings.css
```

### ⚠️ MINDIG CACHE TÖRLÉS UTÁN!
- Browser: `Ctrl+Shift+R` vagy `Cmd+Shift+R`
- WordPress cache plugin is törlendő

## 🌐 Nyelvek

### Támogatott nyelvek:
- 🇷🇴 Román (RO) - **alapértelmezett** (2026-01-28-tól)
- 🇩🇪 Német (DE)
- 🇭🇺 Magyar (HU)

### Nyelv detektálás prioritás:
1. REST header (X-PPV-Lang) - API hívásokhoz
2. GET param (?lang=ro) - redirect vagy manuális váltás
3. Cookie (ppv_lang)
4. Session
5. **Browser Accept-Language** (q-value prioritással!)
6. Default: **Román**

### Fontos: Browser nyelvfelismerés
A rendszer figyelembe veszi az Accept-Language header q-értékeit:
```
hu-HU,hu;q=0.9,de;q=0.8 → Magyar lesz (nem német!)
```

### Fordítások helye:
```php
includes/lang/ppv-lang-de.php
includes/lang/ppv-lang-hu.php
includes/lang/ppv-lang-ro.php
```

### Használat:
```php
PPV_User_Settings::t('key_name')
// vagy
PPV_Lang::t('key_name')
```

## 🐛 Gyakori Problémák

### 1. Inputok nem látszanak
**Ok**: Elementor escape-eli a shortcode-ot
**Megoldás**: Használj közvetlen PHP shortcode-ot, ne Elementor widget-et

### 2. CSS változások nem látszanak
**Ok**: Browser vagy WordPress cache
**Megoldás**:
```bash
Ctrl+Shift+R  # Browser cache törlés
```
+ WordPress cache plugin flush

### 3. CSS nem töltődik be
**Ok**: Nincs a whitelist-en
**Megoldás**: Add hozzá a `punktepass.php` whitelist-hez:
```php
$whitelist = [
    // ...
    'ppv-new-style',  // ← Új CSS handle
];
```

### 4. Asset verzió nem frissül
**Ok**: Asset versioning cache
**Megoldás**: Módosítsd a fájlt → file modification time változik → új verzió

## 📋 Debug Módszerek

### Console ellenőrzés:
```javascript
// Input létezik-e?
document.querySelector('input[name="name"]')  // null = NEM létezik

// Computed style
getComputedStyle(document.querySelector('input[name="name"]'))

// Height
document.querySelector('input[name="name"]').offsetHeight  // 0 = rejtett
```

### PHP Debug:
```php
ppv_log("🔍 Debug message");  // Custom log függvény
error_log(print_r($data, true));  // Standard PHP log
```

### Ne használj:
❌ Inline debug HTML-t ami szövegként jelenik meg
❌ Style tag-eket a shortcode kimenetben (Elementor escape-eli)
✅ Külön teszt shortcode-okat debugging-hoz

## 🎯 Best Practices

### CSS:
- ✅ Dedikált CSS fájlok oldalanként (ppv-user-settings.css)
- ✅ `!important` használata csak végső esetben
- ✅ BEM vagy prefix naming (ppv-*)
- ❌ Ne módosítsd a 341KB-os minified CSS-t közvetlenül

### PHP:
- ✅ Mindig `esc_attr()`, `esc_html()`, `esc_url()` használata
- ✅ Nonce ellenőrzés AJAX-nál
- ✅ Session indítás ellenőrzéssel: `if (session_status() === PHP_SESSION_NONE) @session_start();`
- ❌ Ne használj WordPress user functions-t (`wp_get_current_user()`)

### JavaScript:
- ✅ jQuery használható (WordPress tartalmazza)
- ✅ `wp_add_inline_script()` adatok átadásához
- ✅ Event delegation hosszú listákhoz
- ❌ Ne manipuláld a DOM-ot úgy hogy inputok törlődjék

## ⚡ Performance Optimalizálás

### Jelenlegi PageSpeed Score (2026-01-16):
| Kategória | Mobil | Desktop |
|-----------|-------|---------|
| Performance | 57 | 93+ |
| Accessibility | 95 | 95 |
| Best Practices | 96 | 96 |
| SEO | 92 | 92 |

### Képek - WebP használat
- ✅ `logo.webp` - használd PNG helyett
- ✅ `store-default.webp` (35 KB) - PNG volt 905 KB!
- ✅ Különböző méretek: `-48.webp`, `-64.webp`, `-128.webp`, `-256.webp`
- 🛠️ Optimalizáló script: `tools/optimize-images.php`

### RemixIcon - Központosított betöltés
⚠️ **NE tölts be RemixIcon-t külön fájlokban!**

A `punktepass.php` globálisan betölti:
```php
wp_enqueue_style('remixicons', 'https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css', [], '3.5.0');
```

Ha új komponensben kell ikon, csak használd - már be van töltve!

### LiteSpeed Cache Beállítások

#### Cache → Excludes - NE cache-eld ezeket:
```
/user_dashboard
/meine-punkte
/belohnungen
/einstellungen
/qr-center
/rewards
/mein-profil
/statistik
/login
/signup
/logout
/pos-admin
/store/
/wp-json/
```

#### Fontos beállítások:
- **Cache Logged-in Users**: OFF (session-alapú auth!)
- **Cache REST API**: OFF (dinamikus adatok!)
- **Cache Mobile**: ON
- **Browser Cache**: ON
- **JS Minify**: ON
- **JS Deferred**: ON (NE Delayed!)
- **CSS Minify**: ON
- **CSS Combine**: OFF (problémás!)
- **Font Display**: Swap

### Teljesítmény limitációk
- 🔴 **341KB CSS** (`ppv-theme-light.css`) - render-blocking, de NE próbáld tömöríteni/splittelni (korábban elromlott)
- 🔴 **Unused JS/CSS** - code splitting nélkül nehéz javítani
- ✅ **CLS: 0.002** - kiváló (RemixIcon egységesítés megoldotta)
- ✅ **TBT: 50ms** - kiváló

## 📄 Összes Oldal/Route Lista

### User oldalak (session-alapú):
| URL | Shortcode | PHP Class |
|-----|-----------|-----------|
| `/user_dashboard` | `[ppv_user_dashboard]` | `PPV_User_Dashboard` |
| `/meine-punkte` | `[ppv_my_points]` | `PPV_My_Points` |
| `/belohnungen` | `[ppv_rewards_page]` | `PPV_Belohnungen` |
| `/einstellungen` | `[ppv_user_settings]` | `PPV_User_Settings` |

### Handler/Vendor oldalak:
| URL | Shortcode | PHP Class |
|-----|-----------|-----------|
| `/qr-center` | `[ppv_qr_center]` | `PPV_QR` |
| `/rewards` | `[ppv_rewards]` | `PPV_Rewards` |
| `/mein-profil` | `[pp_store_profile]` | `PPV_Profile_Lite` |
| `/statistik` | `[ppv_stats_dashboard]` | `PPV_Stats` |

### Auth oldalak:
| URL | Shortcode | PHP Class |
|-----|-----------|-----------|
| `/login` | `[ppv_login_form]` | `PPV_Login` |
| `/signup` | `[ppv_signup]` | `PPV_Signup` |
| `/logout` | - | `PPV_Logout` |

### Publikus oldalak (cache-elhető):
- `/datenschutz`, `/agb`, `/impressum`
- `/store/{slug}` - publikus store oldal

## 🍪 Cookie Kezelés - FONTOS!

### Nyelv cookie (PPV_Lang)
```php
// ✅ HELYES - domain nélkül (konzisztens JS-sel)
setcookie('PPV_Lang', $lang, time() + 86400*365, '/');

// ❌ HIBÁS - domain paraméterrel
setcookie('PPV_Lang', $lang, time() + 86400*365, '/', '.punktepass.de');
```

**⚠️ Ne használj domain paramétert!** A JS (`ppv-handler.js`) domain nélkül állítja a cookie-t:
```javascript
document.cookie = "PPV_Lang=" + lang + ";path=/;max-age=31536000";
```

Ha PHP-ben domain-nel, JS-ben domain nélkül állítod → **két külön cookie jön létre** → nyelv nem vált megfelelően!

## 💡 Tips Rendszer (User Tippek)

### Shortcode:
```php
[ppv_user_tips]
```

### AJAX endpoint-ok:
- `GET /wp-json/ppv/v1/tips/pending` - Függőben lévő tippek
- `POST /wp-json/ppv/v1/tips/dismiss` - Tipp elrejtése

### PHP Class:
- `includes/class-ppv-user-tips.php`

### Frontend viselkedés:
- Tippek **NEM tűnnek el automatikusan** (nincs auto-hide)
- Emoji ikonok használata (pl. 💡, ✅) RemixIcon helyett (CLS optimalizálás)
- X gombbal bezárható, `dismissed` státuszba kerül

## 🔐 REST API Permission Callbacks

### Helyes használat:
```php
'permission_callback' => [$this, 'check_logged_in_user']
```

### A check függvény:
```php
public function check_logged_in_user() {
    $user_id = PPV_User_Settings::get_ppv_user_id();
    return !empty($user_id);
}
```

**⚠️ Használj létező metódust!** Ne `check_user`, az nem létezik → 500 error!

## 📞 Kapcsolat / Megjegyzések

- **Ügyfél nyelve**: Magyar
- **Projekt nyelv**: Német/Magyar/Román (multi-language)
- **Kód nyelv**: Angol (kommentek, változók)
- **Git commit**: Angol
- **Hosting**: Hostinger (LiteSpeed szerver)

## 📱 iOS App - Codemagic CI/CD

### Sikeres Build: 2026-01-28
- **Verzió**: 1.5
- **Build szám**: automatikusan növelve
- **Platform**: Mac mini M2
- **Workflow**: iOS Build & TestFlight

### Codemagic Konfiguráció
Fájl: `codemagic.yaml`

```yaml
workflows:
  ios-workflow:
    name: iOS Build & TestFlight
    instance_type: mac_mini_m2
    integrations:
      app_store_connect: Punktepass
    scripts:
      - keychain initialize
      - app-store-connect fetch-signing-files "de.erepairshop.punktepass" --type IOS_APP_STORE --create
      - keychain add-certificates
      - xcode-project use-profiles
      - xcodebuild archive...
      - xcodebuild -exportArchive...
    publishing:
      app_store_connect:
        submit_to_testflight: true
```

### Push Notifications - Firebase Setup
1. **Firebase Console**: Project Settings → Cloud Messaging
2. **APNs Authentication Key** (.p8 fájl) feltöltve
   - Key ID: `B5G6757QMH`
   - Team ID: `2694KKB97H`
3. **FCM V1 API** használatban (Service Account)

### Fontos fájlok:
- `Xcode/PunktePass.xcworkspace` - Xcode projekt
- `Xcode/PunktePass/Info.plist` - App konfiguráció
- `Xcode/PunktePass/AppDelegate.swift` - Firebase init, push handling
- `Xcode/PunktePass/PushNotifications.swift` - FCM token kezelés

### TestFlight
- Automatikus feltöltés sikeres build után
- Beta Testers csoport értesítése
- Email notifikáció: borota25@gmail.com

## 🔗 Külső API Integráció - TANULSÁG (eRepairShop)

### Probléma
Az eRepairShop (erepairshop.de) repair form-ból kell PunktePass API-t hívni (punktepass.de) cross-domain cURL-lel.

### Mi NEM működött:
1. **WordPress REST API** (`/wp-json/punktepass/v1/repair-bonus`) → **HTTP 401**
   - Ok: A `punktepass.php` fájlban van egy **globális `rest_authentication_errors` filter** (141-216 sor)
   - Ez MINDEN nem autentikált REST API kérést BLOKKOL
   - Van `$anon_endpoints` whitelist, de OPcache miatt nem mindig frissül
   - **Hostinger shared hosting** esetén a WAF/proxy is strip-eli a custom headereket

2. **API key küldés többféleképpen** (URL param, header, body) → szintén 401, mert a filter a route előtt fut

### Mi MŰKÖDIK:
**Standalone PHP endpoint**: `api-repair-bonus.php`
- Közvetlenül tölti be a `wp-load.php`-t → WordPress DB + wp_mail() elérhető
- **MEGKERÜLI a teljes WP REST API-t** (nincs middleware, nincs filter)
- Saját API key validáció közvetlenül a `ppv_stores` táblából
- URL: `https://punktepass.de/wp-content/plugins/punktepass/api-repair-bonus.php`

### Szabály:
> **Külső domain-ről érkező API hívásokhoz MINDIG standalone PHP endpointot használj,
> NE WordPress REST API-t!** A globális auth filter miatt a REST API nem használható
> külső, nem-autentikált kérésekhez (még whitelist-tel sem megbízhatóan).

### Fájlok:
| Fájl | Hely | Funkció |
|------|------|---------|
| `api-repair-bonus.php` | Plugin gyökér | Standalone API endpoint (szerveren) |
| `erepairshop/punktepass_integration.php` | eRepairShop | cURL kliens (hívó oldal) |
| `erepairshop/send_mail.php` | eRepairShop | Form handler + debug output |

### API paraméterek:
```php
POST https://punktepass.de/wp-content/plugins/punktepass/api-repair-bonus.php?api_key=XXX
Content-Type: application/json
{
    "email": "customer@example.com",
    "name": "Customer Name",
    "store_id": 9,
    "points": 2,
    "reference": "Reparatur-Formular Bonus",
    "api_key": "XXX"
}
```

### Store 9 = eRepairShop
- API key: `7b6e6938a91011f0bca9a33a376863b7`
- Bonus pontok: 2 pont reparáturánként
- 4 pont = 10 EUR kedvezmény

### QR Center megjelenés:
A `api-repair-bonus.php` a `ppv_pos_log` táblába is ír (`type = 'qr_scan'`),
így a repair bonus megjelenik a QR Center "Letzte Scans" listájában is.

## 📱 Mobile Autocomplete (Repair Form) - NEM MŰKÖDIK

A `/formular/{slug}` repair form custom JS autocomplete (email keresés DB-ből + Nominatim cím) **nem működik touch eszközökön** (Fully Kiosk tablet, mobil böngészők). Desktopon egérrel működik.

### Ami kipróbálva és NEM működött:
- `touchstart`/`mousedown` events + `preventDefault`
- `?.` optional chaining eltávolítás (régi WebView kompatibilitás)
- `keyup` event az `input` mellé
- `scrollIntoView` on focus
- `fetch()` → `XMLHttpRequest` csere (WebView kompatibilitás)
- `blur` timeout növelés (200→400ms)
- `<datalist>` natív HTML elem (WebView-ban nem megbízható)
- Document-level click dismiss (`blur` handler helyett)
- `autocomplete="off"` → `autocomplete="email"/"street-address"` (Android Autofill)
- `onclick` handler `mousedown`/`touchstart` helyett

### Ami még hátra van (nem próbáltuk):
- Chrome DevTools csatlakoztatás Fully Kiosk WebView-hoz (`chrome://inspect`) - ez kellene a debughoz
- Fully Kiosk **"Enable Webview Contents Debugging"** beállítás bekapcsolása
- Suggestions FÖLÉ az input-nak (`bottom:100%` a `top:100%` helyett)
- Full-screen modal a suggestion dropdown helyett
- `pointer-events: auto` és magasabb z-index

### Tanulság:
> A mobil WebView touch event handling alapvetően más mint desktop. A `blur` → `click` sorrend, a virtuális billentyűzet és a WebView korlátozások miatt a hagyományos dropdown autocomplete nem működik megbízhatóan. Natív `<datalist>` sem megbízható WebView-ban. Következő lépés: devtools csatlakoztatás a pontos hiba megtalálásához.

## 🔧 Egyéb Tanulságok (2026-02)

### WP_REST_Response HTML probléma
- `WP_REST_Response` JSON-ként serializál → nyers HTML-hez `echo` + `exit` kell
- Pl. approval page: `echo $html; exit;` a `return new WP_REST_Response($html)` helyett

### MySQL ENUM gotcha
- Ismeretlen ENUM érték beszúrásakor MySQL (non-strict mode) **üres stringet** tárol, nem hibát dob
- Migráció: `ALTER TABLE ... MODIFY COLUMN ... ENUM('add','remove','mobile_scanner','new_slot')`

### Device limit számítás
- `MAX_DEVICES_PER_USER (2) + max_filialen` (terv limit, nem tényleges fiókok száma)
- A `max_filialen` a store/parent store `ppv_stores` táblából jön

### Approval email rendszer
- `send_approval_notification_email()` a `class-ppv-device-fingerprint.php`-ben
- Többnyelvű (DE/HU/RO) a store `country` mező alapján
- Mindkét approval útvonalból hívva: standalone admin + REST API email link

### Performance cache pattern
- INFORMATION_SCHEMA / SHOW COLUMNS lekérdezéseket `get_option()` flag-ekkel cache-elni
- Pl: `if (get_option('ppv_points_idx_v','0') === '1') return;`

---

**Utolsó frissítés**: 2026-02-09
**Készítette**: Claude Code
**Projekt**: PunktePass (Erepairshop/punktepass-code)
