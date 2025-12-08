# PunktePass Kassza - Fejlesztési Terv

> **Státusz:** Tervezés
> **Utolsó frissítés:** 2025-12-08

---

## 1. Projekt Áttekintés

### Két fő komponens:

| # | Projekt | Piac | Prioritás |
|---|---------|------|-----------|
| 1 | **PunktePass Kassza** (webes) | Románia + bárhol | MAGAS |
| 2 | **POS API Integráció** | Németország | KÖZEPES |

---

## 2. PunktePass Kassza (Webes POS)

### 2.1 Cél
Egyszerű, modern kassza szoftver ami:
- Böngészőben fut (Chrome)
- Hasonló a Datecs Modul TOUCH-hoz
- Beépített PunktePass integráció
- FiscalNet-en keresztül nyomtat

### 2.2 UI Terv (Datecs-hez hasonló)

```
┌─────────────────────────────────────────────────────────────────────────┐
│  PunktePass Kassza                              ChioscTomi    14:35    │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                          │
│  ┌─────────────────────────────────────┐  ┌───────────────────────────┐ │
│  │                                     │  │  KATEGÓRIÁK               │ │
│  │  KOSÁR                              │  │  ┌───────┐ ┌───────┐      │ │
│  │  ─────────────────────────────────  │  │  │GASTRO │ │ PAINE │      │ │
│  │  Paine de casa 500g      1x  5.50  │  │  └───────┘ └───────┘      │ │
│  │  Paine Picnic            2x 13.00  │  │  ┌───────┐ ┌───────┐      │ │
│  │  Lapte                   1x  6.00  │  │  │LACTATE│ │BAUTURI│      │ │
│  │  ─────────────────────────────────  │  │  └───────┘ └───────┘      │ │
│  │                                     │  │                           │ │
│  │  Subtotal:              24.50 RON  │  ├───────────────────────────┤ │
│  │  🎁 PunktePass:         -5.00 RON  │  │  TERMÉKEK                 │ │
│  │  ─────────────────────────────────  │  │  ┌─────────────────────┐ │ │
│  │  ÖSSZESEN:              19.50 RON  │  │  │ Paine de casa 500g  │ │ │
│  │                                     │  │  │       5.50 RON      │ │ │
│  │  👤 János K. (230 pont)            │  │  └─────────────────────┘ │ │
│  │  +24 pont jóváírásra kerül         │  │  ┌─────────────────────┐ │ │
│  │                                     │  │  │ Paine Picnic 500g   │ │ │
│  └─────────────────────────────────────┘  │  │       6.50 RON      │ │ │
│                                            │  └─────────────────────┘ │ │
│  ┌────────┐ ┌────────┐ ┌────────┐         │  ┌─────────────────────┐ │ │
│  │🔍 QR   │ │💵 Fizet│ │❌ Törlés│         │  │ Lapte 1L            │ │ │
│  │ Scan   │ │        │ │        │         │  │       6.00 RON      │ │ │
│  └────────┘ └────────┘ └────────┘         │  └─────────────────────┘ │ │
│                                            └───────────────────────────┘ │
└─────────────────────────────────────────────────────────────────────────┘
```

### 2.3 Funkciók

#### MVP (Első verzió):
- [ ] Termék kezelés (név, ár, kategória, ÁFA)
- [ ] Kategóriák (színes gombok, mint Modul TOUCH)
- [ ] Kosár kezelés (hozzáad, töröl, mennyiség)
- [ ] QR Scanner (kamera vagy USB scanner)
- [ ] PunktePass ügyfél azonosítás
- [ ] Kedvezmény automatikus alkalmazás
- [ ] Fizetés (készpénz, kártya)
- [ ] FiscalNet API → Nyugta nyomtatás
- [ ] CSV Import (termékek tömeges feltöltése)

#### Későbbi verziók:
- [ ] Készlet kezelés
- [ ] Napi/havi jelentések
- [ ] Több operátor
- [ ] Offline mód
- [ ] Vonalkód scanner támogatás
- [ ] Ügyfél kijelző

### 2.4 Technológia

| Rész | Technológia |
|------|-------------|
| Frontend | HTML/CSS/JavaScript (vanilla vagy Vue.js) |
| Backend | PHP (WordPress REST API) |
| Adatbázis | MySQL (ppv_pos_* táblák) |
| Nyomtatás | FiscalNet HTTP API (localhost) |
| Stílus | Dark theme (mint a Standalone Admin) |

### 2.5 Adatbázis táblák

```sql
-- Termékek
CREATE TABLE ppv_pos_products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    store_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    category_id INT,
    vat_code VARCHAR(10) DEFAULT '1',
    barcode VARCHAR(50),
    unit VARCHAR(20) DEFAULT 'db',
    active TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Kategóriák
CREATE TABLE ppv_pos_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    store_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    color VARCHAR(20) DEFAULT '#4a90d9',
    sort_order INT DEFAULT 0
);

-- Tranzakciók
CREATE TABLE ppv_pos_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    store_id INT NOT NULL,
    user_id INT,                      -- PunktePass user (ha van)
    subtotal DECIMAL(10,2) NOT NULL,
    discount DECIMAL(10,2) DEFAULT 0,
    total DECIMAL(10,2) NOT NULL,
    payment_method VARCHAR(20),
    fiscal_receipt_no VARCHAR(50),
    points_earned INT DEFAULT 0,
    reward_id INT,                    -- Ha használt kedvezményt
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Tranzakció tételek
CREATE TABLE ppv_pos_transaction_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    transaction_id INT NOT NULL,
    product_id INT NOT NULL,
    product_name VARCHAR(255),
    quantity DECIMAL(10,3) DEFAULT 1,
    unit_price DECIMAL(10,2),
    total_price DECIMAL(10,2)
);
```

### 2.6 FiscalNet Integráció

```javascript
// Nyugta nyomtatás FiscalNet-en keresztül
async function printReceipt(cart, customer, discount) {
    const receiptData = {
        items: cart.map(item => ({
            name: item.name,
            quantity: item.quantity,
            price: item.price,
            vat: item.vat_code
        })),
        discount: discount,
        payments: [{
            type: 'cash',  // vagy 'card'
            amount: cart.total - discount
        }],
        footer: customer ? [
            `PunktePass: +${customer.points_to_earn} pont`,
            `Egyenleg: ${customer.new_balance} pont`
        ] : []
    };

    // FiscalNet HTTP API hívás
    const response = await fetch('http://localhost:65400/api/Receipt', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(receiptData)
    });

    return response.json();
}
```

---

## 3. Német POS API Integráció

### 3.1 Támogatott rendszerek

| Kassza | API | Prioritás |
|--------|-----|-----------|
| SumUp | Third-Party Loyalty Gateway | MAGAS |
| ready2order | REST API + Webhooks | MAGAS |
| Zettle | Developer API | KÖZEPES |
| Lightspeed | Loyalty API | ALACSONY |

### 3.2 Unified POS API

Egységes végpontok minden kassza típushoz:

```
POST /api/v1/pos/lookup
POST /api/v1/pos/apply-discount
POST /api/v1/pos/complete-transaction
GET  /api/v1/pos/customer/{qr_code}
```

### 3.3 Adapter minta (SumUp)

```php
class SumUp_Adapter implements POS_Adapter_Interface {

    public function lookup_customer($qr_code, $store_id) {
        // PunktePass user keresés
        $user = PPV_Users::get_by_qr($qr_code);

        // SumUp formátumban visszaadás
        return [
            'customer_id' => $user->id,
            'name' => $user->display_name,
            'points' => $user->points_balance,
            'available_rewards' => $this->get_rewards($user, $store_id)
        ];
    }

    public function apply_discount($user_id, $reward_id, $cart_total) {
        // Kedvezmény alkalmazása
        $reward = PPV_Rewards::get($reward_id);
        return [
            'discount_amount' => $reward->discount_value,
            'final_total' => $cart_total - $reward->discount_value
        ];
    }
}
```

---

## 4. Fejlesztési Ütemterv

### Fázis 1: PunktePass Kassza Alap (2 hét)
- [ ] Adatbázis táblák létrehozása
- [ ] Termék CRUD (create, read, update, delete)
- [ ] Kategória kezelés
- [ ] Alap UI (kosár, termékek, kategóriák)
- [ ] CSV Import funkció

### Fázis 2: PunktePass Integráció (1 hét)
- [ ] QR Scanner (kamera + USB)
- [ ] Ügyfél azonosítás API
- [ ] Kedvezmény kiválasztás UI
- [ ] Pont jóváírás

### Fázis 3: FiscalNet Integráció (1 hét)
- [ ] FiscalNet API dokumentáció feldolgozása
- [ ] Nyugta küldés implementálása
- [ ] Hibakezelés
- [ ] Teszt nyomtatás

### Fázis 4: Német POS API (2 hét)
- [ ] Unified POS API endpoints
- [ ] SumUp adapter
- [ ] ready2order adapter
- [ ] Webhook kezelés
- [ ] Teszt szimulátor

### Fázis 5: Tesztelés & Finomítás (1 hét)
- [ ] Valós tesztelés Romániában
- [ ] Bug fixek
- [ ] UI finomítások
- [ ] Dokumentáció

**Összesen: ~7 hét**

---

## 5. URL Struktúra

| URL | Funkció |
|-----|---------|
| `/pos` vagy `kassza.punktepass.de` | Kassza alkalmazás |
| `/pos/settings` | Beállítások (termékek, kategóriák) |
| `/pos/reports` | Jelentések |
| `/admin/pos-simulator` | Teszt szimulátor (fejlesztéshez) |

---

## 6. Fájl Struktúra

```
punktepass-code/
├── includes/
│   ├── pos/
│   │   ├── class-ppv-pos.php              # Fő POS osztály
│   │   ├── class-ppv-pos-products.php     # Termék kezelés
│   │   ├── class-ppv-pos-categories.php   # Kategória kezelés
│   │   ├── class-ppv-pos-transactions.php # Tranzakciók
│   │   ├── class-ppv-pos-fiscalnet.php    # FiscalNet integráció
│   │   └── adapters/
│   │       ├── class-adapter-interface.php
│   │       ├── class-sumup-adapter.php
│   │       └── class-ready2order-adapter.php
│   │
│   └── admin/standalone/
│       ├── pos-app.php                    # Kassza UI
│       ├── pos-settings.php               # Beállítások
│       └── pos-simulator.php              # Teszt szimulátor
│
├── assets/
│   ├── css/
│   │   └── ppv-pos.css                    # Kassza stílusok
│   └── js/
│       ├── ppv-pos-app.js                 # Kassza logika
│       ├── ppv-pos-scanner.js             # QR scanner
│       └── ppv-pos-fiscalnet.js           # FiscalNet kommunikáció
│
└── docs/
    ├── DATECS-ROMANIA-INTEGRATION.md
    └── PUNKTEPASS-KASSZA-PLAN.md          # Ez a dokumentum
```

---

## 7. Következő Lépések

1. **UI prototípus** készítése (hasonló a Modul TOUCH-hoz)
2. **Adatbázis táblák** létrehozása
3. **Termék kezelés** implementálása
4. **FiscalNet API** tesztelése

---

*Dokumentum verzió: 1.0*
