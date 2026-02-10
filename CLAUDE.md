# PunktePass - Claude Code Notes

## Deploy Method

```bash
git fetch origin [BRANCH] && git checkout FETCH_HEAD -- [FILES]
```

### Example:
```bash
git fetch origin claude/review-code-promotion-01LWAg2uwMeFjk2rVRzXUTtk && git checkout FETCH_HEAD -- assets/css/ppv-theme-light.css assets/js/pp-profile-lite.js
```

## Tanulságok / Known Issues

### Mobile autocomplete (repair form) - NEM MŰKÖDIK
A `/formular/{slug}` repair form custom JS autocomplete (email keresés + Nominatim cím) **nem működik touch eszközökön** (tablet Fully Kiosk, mobil böngészők). Desktopon egérrel megy.

**Ami kipróbálva és NEM működött:**
- touchstart/mousedown events + preventDefault
- `?.` optional chaining eltávolítás (régi WebView fix)
- keyup event az input mellé
- scrollIntoView on focus
- fetch → XMLHttpRequest csere
- blur timeout növelés (200→400ms)
- `<datalist>` natív HTML elem
- Document-level click dismiss (blur helyett)
- autocomplete="off" → "email"/"street-address"

**Ami még hátra van (nem próbáltuk):**
- Chrome DevTools csatlakoztatás Fully Kiosk WebView-hoz (`chrome://inspect`)
- Suggestions FÖLÉ az input-nak (bottom:100% a top:100% helyett)
- Full-screen modal a dropdown helyett
- Fully Kiosk "Enable Webview Contents Debugging" beállítás

### Egyéb tanulságok
- `WP_REST_Response` JSON-ként serializál - nyers HTML-hez `echo + exit` kell
- MySQL ENUM: ismeretlen érték üres stringet tárol (non-strict mode)
- WordPress AJAX: `wp_ajax_` és `wp_ajax_nopriv_` is kell publikus endpointokhoz
- Device limit: `MAX_DEVICES_PER_USER (2) + max_filialen` (terv limit, nem tényleges fiókok száma)
- Approval email: `send_approval_notification_email()` device-fingerprint-ben, többnyelvű (DE/HU/RO)
- Performance: INFORMATION_SCHEMA/SHOW COLUMNS lekérdezéseket `get_option()` flag-ekkel cache-elni

## ⚠️ FONTOS: Deploy parancs megadása

**Minden fájlmódosítás után KÖTELEZŐ megadni az SSH deploy parancsot!**

Amikor fájlokat módosítasz és pusholsz, MINDIG írd ki a deploy parancsot a módosított fájlokkal:

```bash
git fetch origin [AKTUÁLIS_BRANCH] && git checkout FETCH_HEAD -- [MÓDOSÍTOTT_FÁJLOK]
```
