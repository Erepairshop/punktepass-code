# PunktePass Kassza Integráció - Románia (Datecs)

> **Státusz:** Tervezés
> **Utolsó frissítés:** 2025-12-08
> **Cél:** Teljes kassza integráció román fiscal nyomtatókkal

---

## 1. Projekt Áttekintés

### Mi a cél?
Olyan Android kassza alkalmazás fejlesztése, ami:
- Termékeket kezel és összesít
- QR kóddal felismeri a PunktePass felhasználót
- Automatikusan levonja a kedvezményt a nyugtából
- Fiscal (adóügyi) nyugtát nyomtat Datecs készülékre
- Pontokat ír jóvá a vásárlás után

### Hogyan működik a gyakorlatban?
```
1. Kasszás beüti a termékeket         → 15.50 RON
2. Ügyfél mutatja a QR kódját         → "Szia János! 230 pontod van"
3. Rendszer ellenőrzi van-e reward    → 5 RON kedvezmény elérhető
4. Kasszás alkalmazza a kedvezményt   → -5.00 RON
5. Végösszeg                          → 10.50 RON
6. Fizetés (készpénz/kártya)
7. Fiscal nyugta nyomtatás:
   ─────────────────────────────
   Termékek összesen:    15.50 RON
   PunktePass kedvezmény: -5.00 RON
   ─────────────────────────────
   FIZETENDŐ:            10.50 RON

   +15 pont jóváírva
   Új egyenleg: 245 pont
   ─────────────────────────────
8. Pont jóváírás a backend-en
```

---

## 2. Hardver Követelmények

### Minimális konfiguráció
| Eszköz | Típus | Becsült ár |
|--------|-------|------------|
| Android készülék | Telefon vagy tablet (Android 7+) | ~100-200€ |
| Datecs Fiscal Printer | FP-950MX (Bluetooth) | ~350€ |
| **Összesen** | | **~450-550€** |

### Alternatív konfigurációk
| Konfiguráció | Leírás | Ár |
|--------------|--------|-----|
| ECR (pénztárgép) | Datecs DP-25 MX - önálló, de kevésbé integrálható | ~250€ |
| All-in-One | Datecs BC50 - tablet + nyomtató + kártyaolvasó egyben | ~800€+ (NDA kell) |

### Datecs FP-950MX specifikáció
- Bluetooth 2.1 + USB kapcsolat
- 58mm papírszélesség
- Román fiscal előírásoknak megfelelő
- DUDE protokoll támogatás

---

## 3. Szoftver Követelmények

### Datecs SDK
| Verzió | Fájl | Megjegyzés |
|--------|------|------------|
| Android SDK v4.0.3 | `DUDE for Android SDK GENERAL.7z` | 2025-04-17 |
| Windows DUDE | `dude-setup-2024-10-16v1-1-0.zip` | Szimulátor + dokumentáció |
| Script példák | `Scripts.7z` | Parancs példák |

### Fontos SDK osztályok
```
com.datecsro.fiscalprinter.SDK.FiscalDevice    - Készülék kapcsolat
com.datecsro.fiscalprinter.SDK.FiscalResponse  - Válasz kezelés
com.datecsro.fiscalprinter.SDK.FiscalException - Hibakezelés
```

### Discount típusok (Const.DiscountType)
| Érték | Név | Leírás | PunktePass használat |
|-------|-----|--------|---------------------|
| 0 | noDiscount | Nincs kedvezmény | - |
| 1 | surchargePercentage | Felár %-ban | - |
| 2 | discountPercentage | Kedvezmény %-ban | Lehet használni |
| 3 | surchargeSum | Felár összegben | - |
| 4 | **discountSum** | **Kedvezmény összegben** | **EZ KELL!** |
| 5 | forbidden | Tiltott | - |
| 6 | special_PLU_discount | Speciális PLU kedvezmény | - |

### Fizetési módok (Const.PaymentType)
| Érték | Név | Leírás | PunktePass használat |
|-------|-----|--------|---------------------|
| 0 | CASH | Készpénz | Normál fizetés |
| 1 | CARD | Bankkártya | Normál fizetés |
| 2 | CREDIT | Hitel | - |
| 3 | MEAL_VOUCHERS | Étkezési jegy | - |
| 4 | VALUE_TICKETS | Értékjegy | - |
| 5 | **VOUCHER** | Voucher | **PunktePass reward** |
| 6 | MODERN_PAYMENT | Modern fizetés | - |
| 7 | CASH_IN_ADVANCE | Előleg | - |
| 8 | OTHER_METHODS | Egyéb | - |
| 9 | Foreign_currency | Külföldi pénznem | - |

---

## 4. Architektúra

### Rendszer felépítés
```
┌─────────────────────────────────────────────────────────────────┐
│                    ANDROID KASSZA APP                           │
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────────────────┐ │
│  │   UI Layer  │  │  Business   │  │    Data Layer           │ │
│  │             │  │   Logic     │  │                         │ │
│  │ • Termékek  │  │ • Kosár     │  │ • Room DB (offline)     │ │
│  │ • Kosár    │──▶│ • Számítás │──▶│ • PunktePass API        │ │
│  │ • Scanner   │  │ • Discount  │  │ • Datecs SDK            │ │
│  │ • Fizetés   │  │             │  │                         │ │
│  └─────────────┘  └─────────────┘  └─────────────────────────┘ │
│                                              │                  │
└──────────────────────────────────────────────┼──────────────────┘
                                               │
                    ┌──────────────────────────┴───────────────────┐
                    │                                              │
                    ▼                                              ▼
        ┌─────────────────────┐                      ┌─────────────────────┐
        │  PunktePass Backend │                      │   Datecs Printer    │
        │  punktepass.de/api  │                      │     FP-950MX        │
        │                     │                      │    (Bluetooth)      │
        │  • User lookup      │                      │                     │
        │  • Points credit    │                      │  • Fiscal receipt   │
        │  • Reward redeem    │                      │  • Daily reports    │
        └─────────────────────┘                      └─────────────────────┘
```

### App struktúra
```
PunktePassPOS/
├── app/
│   ├── src/main/
│   │   ├── java/de/punktepass/pos/
│   │   │   │
│   │   │   ├── MainActivity.kt              # Fő képernyő - termék lista
│   │   │   ├── CheckoutActivity.kt          # Fizetés képernyő
│   │   │   ├── SettingsActivity.kt          # Beállítások
│   │   │   │
│   │   │   ├── scanner/
│   │   │   │   ├── QRScannerActivity.kt     # QR kód scanner
│   │   │   │   └── ScannerViewModel.kt
│   │   │   │
│   │   │   ├── cart/
│   │   │   │   ├── CartManager.kt           # Kosár kezelés
│   │   │   │   ├── CartItem.kt              # Kosár elem model
│   │   │   │   └── CartAdapter.kt           # RecyclerView adapter
│   │   │   │
│   │   │   ├── products/
│   │   │   │   ├── ProductRepository.kt     # Termék adatbázis
│   │   │   │   ├── Product.kt               # Termék model
│   │   │   │   └── ProductAdapter.kt
│   │   │   │
│   │   │   ├── datecs/
│   │   │   │   ├── DatecsManager.kt         # Nyomtató kapcsolat kezelés
│   │   │   │   ├── FiscalPrinter.kt         # Nyugta nyomtatás logika
│   │   │   │   ├── BluetoothHelper.kt       # BT kapcsolat
│   │   │   │   └── PrinterSimulator.kt      # Teszt mód (nincs hardver)
│   │   │   │
│   │   │   ├── api/
│   │   │   │   ├── PunktePassApi.kt         # Retrofit API interface
│   │   │   │   ├── ApiClient.kt             # HTTP kliens
│   │   │   │   └── models/
│   │   │   │       ├── UserResponse.kt
│   │   │   │       ├── CheckoutRequest.kt
│   │   │   │       └── PointsResponse.kt
│   │   │   │
│   │   │   ├── db/
│   │   │   │   ├── AppDatabase.kt           # Room database
│   │   │   │   ├── ProductDao.kt
│   │   │   │   └── TransactionDao.kt
│   │   │   │
│   │   │   └── utils/
│   │   │       ├── Constants.kt
│   │   │       ├── Extensions.kt
│   │   │       └── PriceFormatter.kt
│   │   │
│   │   └── res/
│   │       ├── layout/
│   │       │   ├── activity_main.xml
│   │       │   ├── activity_checkout.xml
│   │       │   ├── activity_scanner.xml
│   │       │   ├── item_product.xml
│   │       │   └── item_cart.xml
│   │       ├── values/
│   │       │   ├── strings.xml
│   │       │   ├── colors.xml
│   │       │   └── styles.xml
│   │       └── drawable/
│   │
│   └── build.gradle.kts
│
├── gradle/
├── build.gradle.kts
└── settings.gradle.kts
```

---

## 5. Funkciók listája

### MVP (Minimum Viable Product)
- [ ] **Bluetooth kapcsolat** Datecs nyomtatóval
- [ ] **Termék kezelés** - hozzáadás, szerkesztés, törlés
- [ ] **Kosár kezelés** - termékek, mennyiség, összeg
- [ ] **QR Scanner** - PunktePass felhasználó azonosítás
- [ ] **PunktePass API** - felhasználó lekérés, pont jóváírás
- [ ] **Kedvezmény számítás** - reward alkalmazása
- [ ] **Fiscal nyugta** - Datecs nyomtatás
- [ ] **Szimulátor mód** - teszteléshez hardver nélkül

### Későbbi fejlesztések (v2)
- [ ] Offline mód - nincs internet, később szinkronizál
- [ ] Napi jelentések (Z-report)
- [ ] Több operátor támogatás
- [ ] Vonalkód scanner támogatás
- [ ] Készlet kezelés
- [ ] Bankkártya terminál integráció

---

## 6. API Endpoints (PunktePass Backend)

### Új endpoint-ok kellenek:

#### 1. Felhasználó lekérése QR alapján
```
GET /api/v1/pos/customer?qr={qr_code}&store_id={store_id}

Response:
{
    "success": true,
    "user": {
        "id": 123,
        "name": "János Kovács",
        "points_balance": 230,
        "available_rewards": [
            {
                "id": 5,
                "title": "5 RON kedvezmény",
                "discount_amount": 5.00,
                "min_purchase": 10.00
            }
        ]
    }
}
```

#### 2. Checkout / Tranzakció véglegesítés
```
POST /api/v1/pos/checkout

Request:
{
    "store_id": 123,
    "user_id": 456,
    "cart_total": 15.50,
    "applied_reward_id": 5,        // opcionális
    "discount_amount": 5.00,       // opcionális
    "final_total": 10.50,
    "payment_method": "cash",
    "fiscal_receipt_number": "0001234"
}

Response:
{
    "success": true,
    "points_added": 15,
    "new_balance": 245,
    "transaction_id": "TXN-789"
}
```

#### 3. Tranzakció sztornó
```
POST /api/v1/pos/void

Request:
{
    "store_id": 123,
    "transaction_id": "TXN-789",
    "reason": "Customer request"
}
```

---

## 7. Datecs Kommunikáció

### Nyugta nyomtatás lépései

```kotlin
// 1. Kapcsolódás a nyomtatóhoz (Bluetooth)
val fiscalDevice = FiscalDevice()
fiscalDevice.connect(bluetoothDevice)

// 2. Fiscal nyugta megnyitása
val transaction = FiscalTransaction(fiscalDevice)
transaction.openFiscalReceipt(
    operatorId = "0001",
    tillNumber = "1",
    receiptType = Const.RecTypes.sale,
    clientId = "",           // Opcionális: ügyfél adószám
    clientName = ""          // Opcionális: ügyfél név
)

// 3. Termékek hozzáadása
val sale = FiscalSale(fiscalDevice)

// Egyszerű termék (kedvezmény nélkül)
sale.add(
    pluName = "Kenyér",
    taxCd = "1",             // ÁFA kategória
    department = "0",
    price = "2.50",
    discountType = Const.DiscountType.noDiscount,
    discountValue = "",
    unit = "db"
)

// Termék kedvezménnyel
sale.add(
    pluName = "Tej",
    taxCd = "1",
    department = "0",
    price = "3.00",
    quantity = "2.000",
    discountType = Const.DiscountType.discountPercentage,
    discountValue = "10.00",  // 10% kedvezmény
    unit = "db"
)

// 4. Subtotal + PunktePass kedvezmény (teljes kosárra)
val subtotal = sale.printSubtotal(
    display = true,
    discountType = Const.DiscountType.discountSum,
    discountValue = "5.00"   // 5 RON PunktePass kedvezmény
)

// 5. Fizetés
val paymentResult = sale.saleTotal(
    paymentType = Const.PaymentType.CASH,
    tenderAmount = "10.50"   // Kapott összeg
)
// paymentResult["change"] = visszajáró

// 6. Nyugta zárása
transaction.closeFiscalReceipt()

// 7. Kapcsolat bontása
fiscalDevice.disconnect()
```

### Nem-fiscal szöveg nyomtatása (pont infó)
```kotlin
// Nyugta után nem-fiscal blokkban
val nonFiscal = NonFiscalReceipt(fiscalDevice)
nonFiscal.open()
nonFiscal.printText("─────────────────────")
nonFiscal.printText("PunktePass")
nonFiscal.printText("+15 pont jóváírva")
nonFiscal.printText("Egyenleg: 245 pont")
nonFiscal.printText("─────────────────────")
nonFiscal.close()
```

---

## 8. Fejlesztési Fázisok

### Fázis 1: Alap infrastruktúra (1 hét)
- [ ] Android projekt létrehozása
- [ ] Datecs SDK integrálása
- [ ] Bluetooth kapcsolat kezelés
- [ ] Szimulátor mód implementálása
- [ ] Alap UI (Material Design)

### Fázis 2: Termék & Kosár (1 hét)
- [ ] Room adatbázis (termékek)
- [ ] Termék CRUD műveletek
- [ ] Kosár kezelés
- [ ] Összeg számítás

### Fázis 3: PunktePass integráció (1 hét)
- [ ] QR Scanner (CameraX + ML Kit)
- [ ] PunktePass API kliens (Retrofit)
- [ ] Felhasználó azonosítás
- [ ] Pont jóváírás
- [ ] Kedvezmény alkalmazás

### Fázis 4: Fiscal nyomtatás (1 hét)
- [ ] Nyugta összeállítás
- [ ] Datecs parancsok küldése
- [ ] Hibakezelés
- [ ] Újranyomtatás

### Fázis 5: Tesztelés & Tanúsítás (2-4 hét)
- [ ] Belső tesztelés
- [ ] Datecs készülék teszt
- [ ] Tanúsítás: https://bnp.ici.ro/
- [ ] Dokumentáció

---

## 9. Tanúsítás (Románia)

### Követelmények
1. Szoftver regisztráció: https://bnp.ici.ro/
2. ANAF kompatibilitás
3. Datecs DUDE protokoll használata

### Folyamat
1. App fejlesztés befejezése
2. Teszt Datecs készülékkel
3. Dokumentáció összeállítása
4. Online regisztráció BNP-nél
5. Jóváhagyás (~2-4 hét)

---

## 10. Költségbecslés

### Fejlesztés
| Tétel | Idő | Megjegyzés |
|-------|-----|------------|
| Alap infrastruktúra | 1 hét | Projekt, SDK, Bluetooth |
| Termék & Kosár | 1 hét | DB, UI, logika |
| PunktePass integráció | 1 hét | API, Scanner |
| Fiscal nyomtatás | 1 hét | Datecs parancsok |
| Tesztelés | 1-2 hét | Debug, fix |
| **Összesen** | **5-6 hét** | |

### Hardver (partner költség)
| Tétel | Ár |
|-------|-----|
| Android tablet (ajánlott: Samsung Tab A) | ~150€ |
| Datecs FP-950MX | ~350€ |
| **Összesen** | **~500€** |

---

## 11. Kérdések / Tennivalók

### Datecs-nek feltenni:
- [ ] Van-e szoftver szimulátor teszteléshez?
- [ ] Tudnak-e teszt készüléket adni?
- [ ] Mi a tanúsítás pontos folyamata?
- [ ] Van-e magyar/angol dokumentáció?

### Döntések:
- [ ] Hol legyen a forráskód? (új repo vagy punktepass-code/android-pos/)
- [ ] Milyen Android verzió legyen a minimum? (ajánlott: Android 7+)
- [ ] Kell-e offline mód az MVP-be?
- [ ] Kell-e több nyelv támogatás? (román, magyar, német)

---

## 12. Kapcsolódó fájlok

- Datecs Android SDK: `DUDE for Android SDK GENERAL.7z`
- Windows DUDE: `dude-setup-2024-10-16v1-1-0.zip`
- Script példák: `Scripts.7z`
- Ez a dokumentum: `docs/DATECS-ROMANIA-INTEGRATION.md`

---

## 13. Kontaktok

### Datecs Romania
- Email: (a kapott email cím)
- Web: https://www.datecs.ro/

### Tanúsítás
- BNP NICI: https://bnp.ici.ro/

---

*Dokumentum verzió: 1.0*
*Készítette: Claude / PunktePass Team*
