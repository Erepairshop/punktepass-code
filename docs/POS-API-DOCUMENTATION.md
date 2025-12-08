# PunktePass POS Gateway API Documentation

**Version:** 1.0
**Base URL:** `https://punktepass.de/wp-json/punktepass/v1/pos-gateway`

---

## Overview

PunktePass POS Gateway enables external Point of Sale systems to integrate with the PunktePass customer loyalty program. This API allows POS systems to:

- Look up customers by QR code scan or name search
- Retrieve customer point balance and available rewards
- Apply rewards (discounts) to transactions
- Report completed transactions for point earning

## Authentication

All API requests require authentication via API key:

```
Header: X-POS-API-Key: pk_live_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
```

For webhook verification, include:
```
Header: X-Verification-Token: vt_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
```

## Rate Limits

- 60 requests per minute per gateway
- Rate limit headers included in responses

---

## Endpoints

### 1. Customer Lookup by QR Code

Scan customer's PunktePass QR code to retrieve their profile and available rewards.

**POST** `/customer/qr-lookup`

**Request:**
```json
{
  "qr_code": "PP-U-abc123xyz"
}
```

**Response (200 OK):**
```json
{
  "success": true,
  "customer": {
    "account_code": "12345",
    "first_name": "Max",
    "last_name": "Mustermann",
    "email": "max@example.com",
    "points_balance": 850,
    "tier": "GOLD",
    "rewards": [
      {
        "id": "1",
        "name": "5€ Rabatt",
        "description": "5 Euro Rabatt auf Ihren Einkauf",
        "type": "money_off",
        "value": 5.00,
        "points_required": 500
      },
      {
        "id": "2",
        "name": "Gratis Kaffee",
        "description": "Ein Kaffee gratis",
        "type": "free_product",
        "product_skus": ["COFFEE-001", "COFFEE-002"],
        "points_required": 300
      }
    ]
  },
  "message": "Willkommen zurück, Max!"
}
```

---

### 2. Customer Search by Name

Search for customers by name or email (for manual lookup).

**POST** `/customer/search`

**Request:**
```json
{
  "query": "Max Muster",
  "limit": 10
}
```

**Response (200 OK):**
```json
{
  "success": true,
  "customers": [
    {
      "account_code": "12345",
      "first_name": "Max",
      "last_name": "Mustermann",
      "email": "max@example.com",
      "points_balance": 850
    }
  ]
}
```

---

### 3. Get Customer Rewards

Retrieve available rewards for a specific customer.

**GET** `/customer/{id}/rewards?cart_total=25.00`

**Parameters:**
- `id` (path): Customer account code
- `cart_total` (query, optional): Current cart total to filter applicable rewards

**Response (200 OK):**
```json
{
  "success": true,
  "rewards": [
    {
      "id": "1",
      "name": "5€ Rabatt",
      "description": "5 Euro Rabatt auf Ihren Einkauf",
      "type": "money_off",
      "value": 5.00,
      "points_required": 500
    }
  ]
}
```

---

### 4. Apply Reward

Apply a reward to the current transaction.

**POST** `/reward/apply`

**Request:**
```json
{
  "customer_id": "12345",
  "reward_id": "1",
  "cart_total": 25.00
}
```

**Response (200 OK):**
```json
{
  "success": true,
  "reward_id": "1",
  "reward_name": "5€ Rabatt",
  "discount_type": "money_off",
  "discount_value": 5.00,
  "new_total": 20.00,
  "points_deducted": 500
}
```

**Error Response (400):**
```json
{
  "success": false,
  "error": "insufficient_points",
  "message": "Not enough points",
  "required": 500,
  "available": 350
}
```

---

### 5. Transaction Complete

Report a completed transaction to earn points for the customer.

**POST** `/transaction/complete`

**Headers:**
```
X-POS-API-Key: pk_live_xxx
X-Verification-Token: vt_xxx
```

**Request:**
```json
{
  "sale_id": "POS-TX-2024-001",
  "account_code": "12345",
  "subtotal": 25.00,
  "discount": {
    "reward_id": "1",
    "amount": 5.00
  },
  "total": 20.00,
  "currency": "EUR",
  "timestamp": "2024-01-15T14:30:00+01:00"
}
```

**Response (200 OK):**
```json
{
  "success": true,
  "points_earned": 25,
  "points_redeemed": 500,
  "new_points_balance": 375,
  "message": "Vielen Dank! Sie haben 25 Punkte gesammelt."
}
```

---

### 6. Transaction Cancel/Refund

Cancel a transaction and reverse points.

**POST** `/transaction/cancel`

**Request:**
```json
{
  "transaction_id": "POS-TX-2024-001"
}
```

**Response (200 OK):**
```json
{
  "success": true,
  "points_refunded": 500,
  "points_reversed": 25
}
```

---

## Reward Types

| Type | Description | Value Field |
|------|-------------|-------------|
| `money_off` | Fixed amount discount | `value` (decimal) |
| `free_product` | Free product by SKU | `product_skus` (array) |

---

## Error Codes

| Code | HTTP Status | Description |
|------|-------------|-------------|
| `missing_qr_code` | 400 | QR code not provided |
| `customer_not_found` | 404 | No customer found for QR code |
| `reward_not_found` | 400 | Reward doesn't exist or is inactive |
| `insufficient_points` | 400 | Customer doesn't have enough points |
| `invalid_signature` | 403 | Verification token mismatch |
| `rate_limit_exceeded` | 429 | Too many requests |

---

## Integration Flow

```
┌─────────────┐    1. Scan QR    ┌─────────────────┐
│   SumUp     │ ───────────────► │   PunktePass    │
│   POS       │ ◄─────────────── │   API           │
│   Terminal  │   2. Customer    └─────────────────┘
│             │      + Rewards           │
│             │                          │
│             │   3. Apply Reward        │
│             │ ─────────────────────────►
│             │ ◄─────────────────────────
│             │   4. Discount Amount     │
│             │                          │
│             │   5. Transaction         │
│             │      Complete            │
│             │ ─────────────────────────►
│             │ ◄─────────────────────────
│             │   6. Points Earned       │
└─────────────┘                          │
```

---

## Security Requirements

1. **HTTPS Only** - All API calls must use HTTPS
2. **API Key Protection** - Never expose API keys in client-side code
3. **Verification Token** - Validate all webhook requests
4. **IP Whitelist** (optional) - Restrict API access by IP

---

## Support

- **Technical Support:** support@punktepass.de
- **Integration Questions:** integration@punktepass.de
- **Documentation:** https://punktepass.de/api-docs/

---

## Changelog

### Version 1.0 (2024-01)
- Initial release
- SumUp Third-Party Loyalty Gateway compatible
- Support for money_off and free_product rewards
