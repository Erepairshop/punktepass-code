# PunktePass - Feladatlista

> Utolsó frissítés: 2024-11-26

---

## 📊 Projekt Áttekintés

| Kategória | Érték |
|-----------|-------|
| PHP fájlok | 74 db (42,874 sor) |
| JavaScript | 57 db (18,019 sor) |
| CSS | 10 db (21,207 sor) |
| Összméret | ~57MB |

---

## 🔴 KRITIKUS JAVÍTANDÓK

### 1. Biztonsági problémák
- [ ] Ably API kulcs kiszedése env változóba (`punktepass.php:42`)
- [ ] XSS sebezhetőség javítása admin oldalon (`class-ppv-admin-pending-scans.php`)
- [ ] Form inputok sanitizálása (`pp-profile-loader.php`)

### 2. Elavult fájlok törlése
- [ ] `includes/class-ppv-scanner.old.php`
- [ ] `includes/class-ppv-pos-gateway.old.php`
- [ ] `assets/js/ppv-scanner.old.js`
- [ ] `assets/css/ppv-theme-dark.css` (üres)
- [ ] `assets/css/theme-dark-new.css` (üres)

---

## 🟡 JAVÍTANDÓ PROBLÉMÁK

### CSS/Styling
| Probléma | Hatás | Státusz |
|----------|-------|---------|
| Inkonzisztens CSS változók | `--pp-primary`, `--color-primary`, `--ppv-primary` keveredik | ⬜ |
| Dark mode beágyazva light CSS-be | 18,000+ soros fájl, nehéz karbantartani | ⬜ |
| Nincs `prefers-color-scheme` | Nem figyel a rendszer dark mode beállításra | ⬜ |
| Tablet breakpoint hiányos | 640px-1024px között kevés stílus | ⬜ |

### JavaScript
| Probléma | Darabszám | Státusz |
|----------|-----------|---------|
| Console.log hívások | 474 db (törölni production-ból) | ⬜ |
| `var` használat | Több fájlban (helyett `const` / `let`) | ⬜ |
| setInterval memory leak | 17 db interval nincs tisztítva | ⬜ |
| Try/catch hiányzik | API hívásoknál sok helyen | ⬜ |

### Teljesítmény
| Fájl | Méret | Javaslat | Státusz |
|------|-------|----------|---------|
| logo.png | 1.5MB | WebP-re cserélni (400KB) | ⬜ |
| ppv-qr.js | 60KB+ | Darabolni kellene | ⬜ |
| ppv-theme-light.css | 18KB | Critical CSS kivonni | ⬜ |

---

## 🟢 JAVASOLT FEJLESZTÉSEK

### 1. UI/UX Modernizáció
- [ ] Pull-to-refresh mobilon
- [ ] Skeleton loading kártyákhoz
- [ ] Haptic feedback gomboknál (mobilon)
- [ ] Swipe gestures kártyák között
- [ ] Bottom sheet modal helyett mobilon
- [ ] Floating action button QR-hez

### 2. Funkcionális bővítések
- [ ] Offline mode Service Worker-rel
- [ ] Push notifications új pont/jutalom esetén
- [ ] Widgetek gyors eléréshez
- [ ] Siri/Google Assistant integráció
- [ ] Apple/Google Wallet pass export
- [ ] Referral rendszer barát meghívás

### 3. Technikai javítások
- [ ] CSS változók egységesítése
- [ ] Dark mode külön fájlba
- [ ] Bundle/minify JS fájlok
- [ ] WebP képek fallback-kel
- [ ] Rate limiting API-hoz
- [ ] Error logging rendszer

### 4. Accessibility (Akadálymentesség)
- [ ] ARIA labelek komplex komponensekhez
- [ ] Billentyűzet navigáció
- [ ] Színkontraszt ellenőrzés
- [ ] Screen reader támogatás

---

## ⚡ PRIORITÁSI SORREND

### 🔥 Azonnal (1 hét)
- [ ] 1. Ably API kulcs kiszedése
- [ ] 2. XSS fix admin oldalon
- [ ] 3. Elavult fájlok törlése
- [ ] 4. Console.log-ok eltávolítása

### ✨ Rövid táv (2-3 hét)
- [ ] 1. CSS változók egységesítése
- [ ] 2. Képek optimalizálása (WebP)
- [ ] 3. JS error handling javítása
- [ ] 4. Tablet breakpointok

### 🎯 Közép táv (1 hónap)
- [ ] 1. Dark mode refaktor
- [ ] 2. Nagy JS fájlok darabolása
- [ ] 3. API rate limiting
- [ ] 4. Offline support alapok

### 🚀 Hosszú táv (roadmap)
- [ ] 1. Push notifications
- [ ] 2. Wallet integráció
- [ ] 3. jQuery eltávolítása
- [ ] 4. Test suite építése

---

## 💡 Gyors győzelmek (Quick Wins)

> Ezeket könnyű megcsinálni és nagy hatásuk van:

- [ ] **Skeleton loading** - 30 perc munka, profi hatás
- [ ] **Pull-to-refresh** - iOS/Android feeling
- [ ] **Haptic feedback** - `navigator.vibrate()` gombokra
- [ ] **Better error messages** - felhasználóbarát hibaüzenetek
- [ ] **Loading states** - minden gombra spinner

---

## 📝 Jegyzet

_Ide jöhetnek további megjegyzések a fejlesztés során..._
