# PunktePass - Teljes Rendszerleírás (AI Kontextus Dokumentum)

> **Cél:** Ez a dokumentum arra szolgál, hogy egy AI asszisztens (pl. Claude) teljes képet kapjon a PunktePass rendszerről, és segíteni tudjon cikkek írásában, reklámszövegek készítésében, marketing anyagok előkészítésében, közösségi média posztokban, stb.

---

## 1. MI A PUNKTEPASS?

**PunktePass** egy **digitális törzsvásárlói/hűségpontrendszer** helyi üzletek számára. Egy WordPress plugin, ami teljes körű SaaS megoldást nyújt kereskedőknek ügyfélhűség építéséhez.

**Weboldal:** [punktepass.de](https://punktepass.de)
**Mottó:** „Digitales Bonusprogramm für Ihren Shop" (Digitális bonuszprogram az Ön üzlete számára)

### Rövid összefoglaló:
- QR kód alapú pontgyűjtés fizikai üzletekben
- PWA alkalmazás (Progressive Web App) - nincs letöltés az App Store-ból
- Teljes pénztári integráció (POS) dedikált készülékkel
- Jutalmak, kampányok, statisztikák egy helyen
- Többnyelvű: német, magyar, román, angol, olasz
- Beépített Repair/Szerviz modul javítóműhelyeknek

---

## 2. HOGYAN MŰKÖDIK A PONTGYŰJTÉS? (Fizikai folyamat)

### A bolt oldalán:
1. A kereskedő kap/használ egy **dedikált telefont vagy tabletet** a kasszánál
2. A készülék **hátsó kamerája a vásárló felé néz**
3. A kamerára egy **"PunktePass MiniKamera"** csíptethető lencsefeltét kerül, ami optimalizálja a QR kód olvasást
4. A készüléken fut a PunktePass webalkalmazás (QR Center) szkenner módban

### A vásárló oldalán:
1. A vásárló megnyitja a PunktePass-t a telefonján (PWA böngészőben) vagy felmutatja a kinyomtatott QR kódját
2. A pénztárgép melletti készülék automatikusan beolvassa a QR kódot
3. **A pontok azonnal jóváíródnak** - mind a kereskedő, mind a vásárló látja a megerősítést valós időben (Ably real-time)

### Pontok:
- A kereskedő szabadon beállítja, hány pontot ad szkennelésénként (0-20 pont/szken)
- Maximum 20 pont adható egy szkennelésre (biztonsági korlát)
- A pontok üzletenként gyűlnek (ha valaki több üzletben gyűjt, mindegyik külön számít)

---

## 3. A JUTALMAK/PRÉMIUMOK RENDSZERE

### Hogyan működik:
- A kereskedő létrehozza a jutalmakat a rendszerben (pl. "Gratis Kaffee", "10€ Rabatt")
- Minden jutalom meghatározott pontszámot igényel (pl. 100 pont = ingyenes kávé)
- Amikor a vásárló összegyűjti a szükséges pontokat, a profiljában megjelenik: **"BEREIT"** (Kész)
- A vásárló felmutatja a QR kódját a boltban, a kereskedő a rendszerben jóváhagyja a beváltást

### Jutalom típusok:
- **Százalékos kedvezmény** (pl. 15% kedvezmény)
- **Fix összegű kedvezmény** (pl. 10€ le)
- **Ingyenes termék** (pl. gratis kávé, gratis pizza szelet)

### Kampányok:
- Időben korlátozott akciók (start/end dátummal)
- Filialenként (telephelyenként) vagy globálisan alkalmazható

---

## 4. VIP SZINTRENDSZER

A rendszer automatikus VIP szinteket kínál a gyakori vásárlóknak:

| Szint | Név (DE) | Név (HU) |
|-------|----------|----------|
| 1 | Starter | Starter |
| 2 | Bronze | Bronz |
| 3 | Silber | Ezüst |
| 4 | Gold | Arany |
| 5 | Platin | Platina |

### VIP bónuszok (kereskedő állítja be):
1. **Fix pontos bónusz** szintenként (pl. Bronz: +1 pont, Arany: +3 pont extra minden szkeneléskor)
2. **Streak bónusz** (minden X-edik szkenelésre szorzó vagy fix bónusz, szintenként)

---

## 5. KERESKEDŐ OLDAL (Handler/Händler felület)

### Alsó navigáció (5 gomb):
1. **Start** → QR Center (fő irányítópult)
2. **Belohnungen** (Jutalmak) → Beváltások kezelése
3. **Profil** → Bolt beállítások
4. **Statistik** → Analitika
5. **Support** → AI chat asszisztens

### QR Center (/qr-center) - 5 fül:
1. **Kassenscanner** - Valós idejű szkenelési aktivitás, utolsó szkenelések, CSV export
2. **Geräte** (Készülékek) - Regisztrált készülékek kezelése, új készülék hozzáadása
3. **Prämien** (Jutalmak) - Jutalmak létrehozása/szerkesztése/törlése
4. **Scanner Benutzer** - Alkalmazotti scanner fiókok kezelése
5. **VIP Einstellungen** - VIP bónusz rendszer beállítások

### Bolt profil (/mein-profil) - 6 fül:
1. **Allgemein** - Alapadatok (név, cím, kategória, GPS koordináták)
2. **Öffnungszeiten** - Nyitvatartás (H-V)
3. **Bilder & Medien** - Logó, galéria képek
4. **Kontakt & Social** - Telefon, email, weboldal, WhatsApp, Facebook, Instagram, TikTok
5. **Marketing** - Automatizált kampányok (lásd részletesen lentebb)
6. **Einstellungen** - Aktív/látható, szabadság mód, időzóna, jelszócsere

### Statisztikák (/statistik) - 5 fül:
1. **Übersicht** - Mai/heti/havi statisztikák, top 5 ügyfél, csúcsidők, trendek
2. **Erweitert** - Haladó adatelemzés, CSV export
3. **Mitarbeiter** - Alkalmazottak teljesítménye
4. **Verdächtige Scans** - Gyanús szkenelések észlelése
5. **Geräte** - Készülék aktivitás

---

## 6. MARKETING AUTOMATIZÁCIÓ

### 6.1 Google Review kérés
- A kereskedő beállítja a ponthatárt (pl. 50 lifetime pont után)
- Automatikus kérés küldése WhatsApp-on vagy emailben
- Bónuszpont jár a review-ért
- Naponta egyszer futó cron, reggel 10:00-kor
- Minden ügyfél max 1x kap kérést boltonként

### 6.2 Születésnapi bónusz
- Automatikus bónusz a vásárló születésnapján
- Típusok: dupla pont / fix pont / ingyenes termék
- Személyre szabott üzenet
- Anti-abuse: minimum 320 nap két bónusz között

### 6.3 Comeback kampány
- Inaktív vásárlók visszacsalogatása
- Beállítható inaktivitási idő: 14/30/60/90 nap
- Automatikus üzenet küldése a jutalom ajánlattal
- Típusok: dupla pont / fix pont / ingyenes termék

### 6.4 Push értesítések
- Firebase Cloud Messaging (FCM V1 API)
- iOS, Android, Web támogatás
- Heti 1 push limit boltonként
- Admin felület tömeges küldéshez
- Platform-bontású statisztikák

### 6.5 Referral (ajánlói) program
- Egyedi ajánlói link minden kereskedőnek (/r/{code}/{store_key})
- Cookie + session alapú nyomkövetés (30 nap)
- Jutalom típusok: pontok vagy ajándék
- Opcionális manuális jóváhagyás
- Beállítható grace period
- Önajánlás elleni védelem

### 6.6 Marketing eszközök
- **QR plakát generátor** - Nagyfelbontású nyomtatható plakát QR kóddal
- **Szórólap PDF** - A4-es szórólap a bolt adataival
- **Social media kép** - 1080x1080 PNG Instagramra/Facebookra
- **Digitális QR link** - Megosztható URL
- **Embed kód** - iframe kódrészlet weboldalakhoz

---

## 7. KOMMUNIKÁCIÓS CSATORNÁK

### WhatsApp integráció
- Meta WhatsApp Cloud API (v22.0)
- Sablon üzenetek, szöveges üzenetek (24 órás ablakban), interaktív gombok
- Bejövő üzenet kezelés webhook-kal
- Admin chat felület (/formular/admin)
- Google Review kéréseknél elsődleges csatorna

### Email rendszer
- Professzionális email küldő eszköz (/admin/email-sender)
- Sablon mentés és újrafelhasználás
- Tömeges küldés
- Csatolmány támogatás
- Teljes audit napló
- Duplikátum szűrés

### AI Support Chat
- Beépített AI asszisztens a kereskedői felületen
- Claude/Anthropic alapú
- Kontextus-tudatos (tudja melyik oldalon áll a felhasználó, melyik bolt)
- Vizuális útmutatás (kiemelés, navigálás, fülváltás)
- Eszkaláció: WhatsApp + Email gombok
- Többnyelvű (DE, HU, RO, EN, IT)
- Rate limit: 10 üzenet / 10 perc

---

## 8. A REPAIR/SZERVIZ MODUL (eRepairShop)

Ez egy **teljesen önálló modul** a PunktePass-on belül, ami **javítóműhelyeknek** (handy shop, computer szerviz, autó szerviz stb.) nyújt komplett digitális ügyintézést.

### 8.1 Nyilvános szervizlap (/formular/{bolt-neve})
Minden bolt kap egy saját, márkázott online szervizlapot.

**Alap mezők:**
- Ügyfél neve, email, telefon
- Készülék márka, modell
- Hibaleírás (szabad szöveg + előre definiált gyorsgombok)

**Opcionális mezők (kereskedő kapcsolja be):**
- IMEI szám
- Tartozékok (checklist)
- Fotó feltöltés (készülék állapot)
- Aláírás (érintőképernyős)
- PIN/jelszó minta (muster rajzoló)
- Készülék szín
- Költséghatár
- Vásárlás dátuma
- Prioritás
- Állapotfelmérés (részletes checklist)

**KFZ/Járműszerviz mód:**
- Rendszám
- Alvázszám (VIN)
- Kilométeróra állás
- Első regisztráció dátuma
- TÜV/műszaki vizsga érvényesség
- Járműspecifikus állapotfelmérés

**PC/Számítógép szerviz mód:**
- Alaplap, CPU, RAM, SSD, GPU, kijelző, billentyűzet, ventilátor, tápegység, portok állapotfelmérés

**Egyéb funkciók:**
- QR kód generálás minden javításhoz (nyomonkövetési kód)
- Offline mód: internetkimaradás esetén sorba állítja a beküldéseket
- PunktePass integrált pontgyűjtés (javítás leadásakor is kap pontot)
- Visszatérő ügyfél automatikus felismerése (email alapján)
- Nominatim címkereső autokitöltés
- Többnyelvű form (DE, HU, RO, EN, IT)
- AI-alapú hibadiagnózis (ügyfél számára)

### 8.2 Admin Dashboard (/formular/admin)

**8 fül:**

#### Reparaturen (Javítások) fül:
- Kártya nézetes javítási lista
- Keresés: ügyfélnév, telefon, készülék
- Státusz szűrő
- **Státusz workflow:** Új → Folyamatban → Alkatrészre vár → Kész → Kiadva → Törölve
- Belső megjegyzések
- Nyomtatható javítási jegy (QR kóddal)
- Élő nyomkövető link másolása (ügyfél számára)
- Automatikus frissítés 15 másodpercenként
- Feedback email: automatikus küldés 24 órával a "Kész" státusz után
- **"Teil angekommen" (Alkatrész megérkezett)** gomb az "Alkatrészre vár" státuszú javításoknál
- Időpont-egyeztetés badge-ek (piros: Widget-ből jött, nincs időpont; szürke: nincs időpont; zöld: van időpont)

#### Rechnungen (Számlák) fül:
- Számla (Rechnung) és árajánlat (Angebot) létrehozás
- 2 lépéses varázsló: 1) Ügyfél adatok 2) Tételek + összegek
- Automatikus számlagenerálás "Kész" státusznál
- Egyedi számlaszám prefix (pl. RE-001)
- Tételsorok: szolgáltatás + mennyiség × egységár
- ÁFA kezelés (beállítható kulcs, kisvállalkozói mentesség)
- Garancia dátum + feltételek (PDF-en megjelenik)
- Email küldés PDF-fel
- Tömeges email
- Fizetési emlékeztető
- PDF letöltés, CSV export, tömeges PDF ZIP-ben
- Státusz: Piszkozat → Elküldve → Fizetve → Sztornózva

#### Kunden (Ügyfelek) fül:
- Ügyféladatok szerkesztése
- Keresés meglévő ügyfelek között

#### Termine (Időpontok) fül:
- Havi naptár nézet
- Oldalsáv az adott nap időpontjaival
- Kétféle időpont: kézi (új gombbal) és javítási (automatikus)
- Időpont típusok: Általános, Javítás, Átvétel, Konzultáció, Árajánlat
- Státusz: Tervezett → Megerősítve → Elvégezve / Lemondva
- 7 szín kódolás
- Email értesítés ügyfélnek

#### Einstellungen (Beállítások) fül:
- Form mezők ki/bekapcsolása
- Márkák kezelése (egyedi márkalista)
- Probléma kategóriák (gyors gombok a formon)
- Számla sablonok, ÁFA beállítás
- Becsült javítási idő
- KFZ/PC mód kapcsoló
- Egyedi CSS
- **Widget rendszer** (4 mód):
  - Katalógus/Árlista: teljes katalógus árakkal
  - Lebegő gomb: oldalszéli FAB gomb
  - Inline banner: beágyazott sáv
  - Egyszerű gomb: CSS szelektoros gomb
- Widget adat import (CSV/AI generált)

#### Ankauf (Felvásárlás) fül:
- Használt készülék felvásárlási modul
- Saját számlagenerálás

#### Partner fül:
- Partner bolt kezelés
- Co-branding a szervizlapon

#### Filialen (Fióktelepek) fül:
- Többtelephelyes működés
- Minden fiók saját URL, beállítások, szkenner készülékek

### 8.3 Nyilvános státusz követés
- Minden javítás kap egy egyedi QR kódot és linket
- Az ügyfél valós időben nyomon tudja követni a javítás állapotát
- Nincs szükség bejelentkezésre

---

## 9. VÁSÁRLÓ OLDAL (User Experience)

### Regisztráció és bejelentkezés:
- Email + jelszó
- Google bejelentkezés
- Facebook bejelentkezés
- TikTok bejelentkezés
- Apple Sign In

### Vásárló PWA alkalmazás:
- Progresszív webalkalmazás - telepíthető a kezdőképernyőre
- Nincs App Store letöltés szükséges
- Offline alapfunkciók

### Vásárlói funkciók:
- **Saját QR kód** megjelenítése (mutatni a pénztárnál)
- **Pontok nyomkövetése** üzletenként
- **Jutalmak megtekintése** (BEREIT = beváltható, vagy hány pont hiányzik még)
- **Beváltási előzmények**
- **Nyelvi beállítás** (DE, HU, RO, EN)
- **Profil kezelés**
- **Push értesítések** (pontjóváírás, jutalom, kampányok)
- **Bolt értékelés** (csillagos rating)

### Alsó navigáció (vásárló):
1. Pontjaim
2. Jutalmak
3. QR kód
4. Beállítások

---

## 10. ÜZLETI MODELL ÉS ÁRAZÁS

### Próbaidőszak:
- **30 napos ingyenes próba** minden új kereskedőnek
- Teljes funkcionalitás a próba alatt
- Visszaszámlálós banner a QR Centeren

### Árazás:
- **39€ nettó / hó** + 19% ÁFA = **46,41€ bruttó / hó** (német ÁFA-val)
- Külföldi EU-vállalkozásnál: 39€ nettó ÁFA nélkül (Reverse Charge)
- Havi vagy éves fizetési lehetőség
- Fizetési módok: **Stripe**, **PayPal**, banki átutalás

### Fizikai csomag (opcionális):
- Dedikált smartphone a kassza mellé (kölcsönzés vagy megvásárlás)
- PunktePass MiniKamera (csíptethető lencse)
- Telefontartó/állvány

### Kereskedői regisztráció:
- Online regisztráció (/formular → regisztráció)
- Händlervertrag (kereskedői szerződés) automatikus generálás
- 30 napos trial automatikus indulás
- Partner értékesítési csatorna (jutalékos partnerek)

---

## 11. SEO ÉS ONLINE JELENLÉT

### Blog rendszer:
- URL: /blog/, /blog/{cikk-url}/, /blog/kategorie/{kategória}/
- SEO optimalizált (meta tagek, Open Graph, Twitter Card, JSON-LD)
- Olvasási idő becslés
- Kapcsolódó cikkek
- Social sharing gombok (Facebook, Twitter, LinkedIn, WhatsApp, link másolás)
- Olvasási folyamatjelző sáv
- CTA (Call-to-Action) szekciók: "Bereit für Ihr Treueprogramm?"
- Automatikus sitemap (/blog-sitemap.xml)

### SEO infrastruktúra:
- Google Search Console verifikáció
- Dinamikus sitemap-ek (formular-sitemap.xml, blog-sitemap.xml)
- IndexNow támogatás
- JSON-LD Structured Data: Organization, WebSite, SoftwareApplication, LocalBusiness, FAQPage
- Open Graph és Twitter Card meta tagek
- Robots.txt szabályok
- Helyi SEO: minden javítóbolt saját oldala helyi vállalkozás sémával

### Publikus oldalak:
- /haendler - Kereskedői információk
- /preise - Árazás
- /kontakt - Kapcsolat
- /so-funktionierts - Hogyan működik
- /landing/ - Meta hirdetési céloldal
- /demo/ - Demo oldal (többnyelvű)
- /sales/punktepass-partner-info.html - Partner (B2B) info

---

## 12. TECHNIKAI RÉSZLETEK (háttér)

- **Platform:** WordPress plugin (PHP)
- **Frontend:** Standalone HTML renderelés (nem függ a WP témától)
- **Real-time:** Ably WebSocket
- **Push:** Firebase Cloud Messaging V1
- **AI:** Anthropic Claude API
- **WhatsApp:** Meta WhatsApp Cloud API
- **Fizetés:** Stripe + PayPal
- **Auth:** Session + JWT Bearer token + OAuth (Google, Facebook, TikTok, Apple)
- **Nyelv:** DE, HU, RO, EN, IT
- **Biztonság:** CSRF védelem, rate limiting, device fingerprinting, titkosított tokenek
- **Cache:** LiteSpeed kompatibilis, transient caching

---

## 13. ÖSSZEFOGLALÓ - MIÉRT JÓ A PUNKTEPASS?

### Kereskedőknek:
- **Egyszerű kezelés** - nincs bonyolult hardware, telefon + MiniKamera elegendő
- **Azonnal indul** - 30 napos ingyenes próba, percek alatt beállítható
- **Több ügyfél visszatér** - automatikus hűségprogram, pontgyűjtés
- **Marketing automatizálás** - születésnapi bónusz, comeback kampány, push, referral
- **Statisztikák** - kik a top ügyfelek, mikor a csúcsidő, hányan jönnek vissza
- **Google értékelések** - automatikus review kérés
- **Többnyelvű** - magyar, román, német, angol ügyfeleknek is

### Javítóműhelyeknek (extra):
- **Digitális szervizlap** - vége a papíralapú felvételnek
- **Számlázás** - teljes számlakezelés, PDF, email küldés
- **Időpontkezelés** - naptár, emlékeztető emailek
- **Élő nyomkövetés** - ügyfél valós időben látja a javítás státuszát
- **Widget** - beágyazható szerviz widget bármely weboldalra
- **Felvásárlás** - használt készülék vásárlás modul
- **AI diagnózis** - gépi hibabehatárolás az ügyfélnek

### Vásárlóknak:
- **Ingyenes** - nincs letöltés, nincs regisztrációs díj
- **Egyszerű** - QR kód felmutatás a kasszánál
- **Átlátható** - látja a pontjait, jutalmait, előzményeit
- **Több boltban** - egy fiókkal több üzletben is gyűjthet
- **Push értesítés** - tudja ha közel van egy jutalomhoz

---

## 14. HASZNOS KIFEJEZÉSEK / SZÓKINCS

| Magyar | Német | Angol |
|--------|-------|-------|
| Pontgyűjtés | Punkte sammeln | Collect points |
| Jutalom/Prémium | Belohnung/Prämie | Reward |
| Beváltás | Einlösung | Redemption |
| Kereskedő | Händler | Merchant/Handler |
| Pénztárgép szkenner | Kassenscanner | POS Scanner |
| Törzsvásárló | Stammkunde | Loyal customer |
| Hűségprogram | Treueprogramm | Loyalty program |
| Bonuszpont | Bonuspunkt | Bonus point |
| Szervizlap | Reparaturauftrag | Repair order |
| Árajánlat | Angebot | Quote |
| Számla | Rechnung | Invoice |
| Fióktelep | Filiale | Branch |
| Nyitvatartás | Öffnungszeiten | Opening hours |
| Készülék | Gerät | Device |
| Születésnapi bónusz | Geburtstags-Bonus | Birthday bonus |
| Visszacsalogató kampány | Comeback-Kampagne | Comeback campaign |
| Ajánlói program | Empfehlungsprogramm | Referral program |

---

## 15. TIPIKUS MARKETING ÜZENETEK / IRÁNYOK

### B2B (kereskedőknek szóló):
- "Vásárlói hűségprogram 5 perc alatt, appletöltés nélkül"
- "39€/hó-ért több visszatérő vásárló"
- "Digitális stempelkártya - végre nem veszik el a papír"
- "Automatikus marketing: születésnapi bónusz, comeback kampány, push értesítés"
- "Javítóműhely? Digitális szervizlap + számlázás + pontgyűjtés egy csomagban"

### B2C (vásárlóknak szóló):
- "Gyűjts pontokat a kedvenc boltjaidban - egyetlen QR kóddal"
- "Ingyenes jutalmak: kávé, kedvezmény, ajándék - te döntöd el mire váltod"
- "Nincs app letöltés - nyisd meg a böngészőben és máris indul"
- "Látod a pontjaidat, a jutalmaidat, mindent valós időben"

### Repair/Szerviz üzenetek:
- "Kövesd valós időben a javítás állapotát - QR kóddal"
- "Digitális szervizfelvétel: nincs több olvashatatlan papírblokk"
- "Online számla, email értesítés, időpont-egyeztetés - minden egy helyen"
- "Beágyazható szerviz widget a weboldaladra - az ügyfelek onnan is beadhatják"

---

*Ez a dokumentum a PunktePass v1.0.4 rendszer alapján készült (2026. február).*
