# Automatikus GitHub → Hostinger Telepítés

**Status:** ✅ Aktív - Automatikus deployment beállítva!

## Beállítás lépései

### 1. GitHub Secrets beállítása

Menj a GitHub repository-dra:
1. Kattints a **Settings** (Beállítások) menüre
2. Bal oldali menüben válaszd ki: **Secrets and variables** → **Actions**
3. Kattints a **New repository secret** gombra
4. Add hozzá a következő secreteket:

#### Szükséges Secrets:

**FTP_SERVER**
- Érték: A Hostinger FTP szerver címe
- Példa: `ftp.yourdomain.com` vagy `123.456.789.0`
- Hol találod: Hostinger → Hosting → FTP Accounts

**FTP_USERNAME**
- Érték: FTP felhasználónév
- Példa: `u123456789` vagy `yourdomain@yourdomain.com`
- Hol találod: Hostinger → Hosting → FTP Accounts

**FTP_PASSWORD**
- Érték: FTP jelszó
- Hol találod: Hostinger → Hosting → FTP Accounts (Create new vagy reset password)

**FTP_SERVER_DIR**
- Érték: A WordPress plugin mappa elérési útja a szerveren
- Példa: `/public_html/wp-content/plugins/punktepass/`
- FONTOS: A végén legyen `/` jel!

### 2. Hostinger FTP adatok lekérése

1. Jelentkezz be a **Hostinger** fiókodba
2. Menj a **Hosting** → **Manage** menüpontra
3. Bal oldali menüben kattints az **FTP Accounts** menüre
4. Itt látod az FTP adatokat, vagy létrehozhatsz új FTP felhasználót

### 3. Működés

Miután beállítottad a GitHub Secreteket:

- Minden alkalommal, amikor pusholsz a GitHub repository-ba
- Automatikusan elindul a deployment workflow
- A fájlok feltöltődnek a Hostinger szerverére FTP-n keresztül
- A `.git`, `node_modules` és egyéb felesleges fájlok nem kerülnek fel

### 4. Deployment ellenőrzése

1. Menj a GitHub repository-dra
2. Kattints az **Actions** fülre
3. Itt látod a futó és befejezett deployment-eket
4. Ha valami hiba van, itt látod a részletes logokat

### 5. Troubleshooting

**Ha a deployment sikertelen:**

1. Ellenőrizd a GitHub Actions log-ot
2. Nézd meg, hogy jók-e a FTP adatok
3. Teszteld FTP kapcsolatot FileZilla programmal
4. Ellenőrizd, hogy a `FTP_SERVER_DIR` útvonal létezik-e a szerveren

**FTP tesztelés FileZilla-val:**
- Host: FTP_SERVER értéke
- Username: FTP_USERNAME értéke
- Password: FTP_PASSWORD értéke
- Port: 21 (FTP) vagy 22 (SFTP)

## Alternatív megoldás: SSH deployment

Ha SSH-t szeretnél használni FTP helyett (gyorsabb, biztonságososabb):

1. Generálj SSH kulcsot a Hostinger-en
2. Add hozzá a GitHub Secrets-hez: `SSH_PRIVATE_KEY`, `SSH_HOST`, `SSH_USERNAME`
3. Módosítsd a `.github/workflows/deploy.yml` fájlt SSH deployment action-re

Szólj, ha ezt a megoldást szeretnéd!

---

## OAuth Beállítások (Facebook & TikTok)

Miután beállítottad az OAuth alkalmazásokat:

### wp-config.php módszer (ajánlott):

```php
define('PPV_FACEBOOK_APP_ID', 'your-facebook-app-id');
define('PPV_FACEBOOK_APP_SECRET', 'your-facebook-app-secret');
define('PPV_TIKTOK_CLIENT_KEY', 'your-tiktok-client-key');
define('PPV_TIKTOK_CLIENT_SECRET', 'your-tiktok-client-secret');
```

### WordPress Admin módszer:

Vagy beállíthatod WordPress admin felületen a PunktePass beállításokban.
