# 🎨 Modern Icons Setup - PunktePass

## Remix Icon CDN telepítése

### 1️⃣ HTML `<head>` tag-be helyezd el:

```html
<!-- Remix Icon CDN - Modern Icons -->
<link href="https://cdn.jsdelivr.net/npm/remixicon@4.0.0/fonts/remixicon.css" rel="stylesheet">

<!-- PunktePass Icon Animations -->
<link href="<?php echo PPV_URL; ?>assets/css/icons-animation.css?ver=<?php echo PPV_VERSION; ?>" rel="stylesheet">
```

### 2️⃣ WordPress plugin esetén (functions.php vagy enqueue):

```php
// Remix Icon CDN
wp_enqueue_style(
    'remix-icons',
    'https://cdn.jsdelivr.net/npm/remixicon@4.0.0/fonts/remixicon.css',
    [],
    '4.0.0'
);

// PunktePass Icon Animations
wp_enqueue_style(
    'ppv-icons-animation',
    PPV_URL . 'assets/css/icons-animation.css',
    ['remix-icons'],
    PPV_VERSION
);

// PunktePass Dashboard JS
wp_enqueue_script(
    'ppv-user-dashboard',
    PPV_URL . 'assets/js/user-dashboard.js',
    [],
    PPV_VERSION,
    true
);
```

---

## 🎯 Használt ikonok listája

### Navigáció és vezérlés:
- `ri-arrow-left-s-line` - Balra nyíl (lightbox)
- `ri-arrow-right-s-line` - Jobbra nyíl (lightbox)
- `ri-arrow-down-s-line` - Le nyíl (toggle)
- `ri-close-line` - Bezár (X)

### QR és scan:
- `ri-qr-code-line` - QR kód ikon
- `ri-download-line` - Letöltés (QR megjelenítés)

### Üzletek és helyszínek:
- `ri-store-2-fill` - Üzlet ikon
- `ri-store-3-line` - Üzlet (üres állapot)
- `ri-map-pin-line` - Térkép pin (cím, szükséges pont)
- `ri-map-pin-distance-line` - Távolság pin
- `ri-route-fill` - Útvonal

### Jutalmak és kampányok:
- `ri-gift-line` - Ajándék (jutalmak)
- `ri-gift-fill` - Ajándék kitöltött (jutalom részletek)
- `ri-megaphone-line` - Megafon (kampányok)

### Idő és dátum:
- `ri-time-line` - Óra (nyitvatartás, pending)
- `ri-calendar-line` - Naptár (kampány dátum)

### Pénzügyi:
- `ri-coins-line` - Érmék (pont per scan)
- `ri-money-dollar-circle-line` - Pénz (bonus)

### Kommunikáció:
- `ri-phone-fill` - Telefon (hívás)
- `ri-global-line` - Világ (weboldal)

### Social media:
- `ri-facebook-circle-fill` - Facebook
- `ri-instagram-fill` - Instagram
- `ri-tiktok-fill` - TikTok

### Egyéb:
- `ri-ruler-line` - Vonalzó (távolság slider)
- `ri-lightbulb-line` - Villanyégő (tipp, speciális ajánlat)
- `ri-file-text-line` - Dokumentum (leírás)
- `ri-loader-4-line` - Betöltő (loading spinner)
- `ri-error-warning-line` - Figyelmeztetés (hiba, duplikáció)
- `ri-close-circle-line` - Bezár kör (hiba toast)
- `ri-emotion-happy-line` - Mosolygó (siker toast)
- `ri-checkbox-blank-circle-fill` - Kör (nyitva/zárva státusz)

---

## ✨ Animációk

### Spinner (forgó):
```html
<i class="ri-loader-4-line ri-spin"></i>
```

### Pulse (pulzáló):
```html
<i class="ri-heart-fill ri-pulse"></i>
```

---

## 🌐 Remix Icon Dokumentáció

- **Weboldal:** https://remixicon.com/
- **GitHub:** https://github.com/Remix-Design/RemixIcon
- **CDN:** https://cdn.jsdelivr.net/npm/remixicon@4.0.0/fonts/remixicon.css
- **Icon keresés:** https://remixicon.com/ (2800+ ikon)

---

## 📝 Emoji-k lecserélve Remix Icon-ra

| Régi Emoji | Új Remix Icon | Használat |
|------------|---------------|-----------|
| 🎁 | `ri-gift-line` | Jutalmak |
| 📢 | `ri-megaphone-line` | Kampányok |
| 🟢 | `ri-checkbox-blank-circle-fill` (zöld) | Nyitva |
| 🔴 | `ri-checkbox-blank-circle-fill` (piros) | Zárva |
| 📏 | `ri-ruler-line` | Távolság |
| 🎉 | `ri-emotion-happy-line` | Siker toast |
| ⚠️ | `ri-error-warning-line` | Figyelmeztetés |
| ❌ | `ri-close-circle-line` | Hiba |
| ⏳ | `ri-time-line` | Pending |
| 📍 | `ri-map-pin-line` | Helyszín |
| 📅 | `ri-calendar-line` | Dátum |
| 💰 | `ri-coins-line` | Pont/pénz |
| 💡 | `ri-lightbulb-line` | Tipp |
| 📝 | `ri-file-text-line` | Leírás |

---

## ✅ Előnyök

1. **Következetes dizájn** - Minden ikon ugyanabból a családból
2. **Skálázható** - Vektoros, bármilyen méretben éles
3. **Könnyű** - Csak egy CSS fájl (~50KB gzip)
4. **Gyors** - CDN-ről töltődik, gyorsítótárazható
5. **Modern** - 2800+ professzionális ikon
6. **Ingyenes** - MIT licensz, kereskedelmi használatra is

---

## 🔄 Alternatívák

Ha Remix Icon helyett mást szeretnél:

### Font Awesome (https://fontawesome.com/)
```html
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
```

### Material Icons (https://fonts.google.com/icons)
```html
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined">
```

### Lucide Icons (https://lucide.dev/)
```html
<script src="https://unpkg.com/lucide@latest"></script>
```

---

## 📞 Támogatás

Ha kérdésed van az ikonokkal kapcsolatban:
1. Nézd meg a Remix Icon dokumentációt: https://remixicon.com/
2. Keress a 2800+ ikon között
3. Használd a CSS animációkat a `icons-animation.css` fájlból

---

**Verzió:** 1.0
**Utolsó frissítés:** 2025-01-16
**Licenc:** MIT (Remix Icon)
