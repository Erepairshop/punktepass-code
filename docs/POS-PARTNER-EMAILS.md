# POS Partner Application Emails

## 1. SumUp Third-Party Loyalty Partnership Request

**To:** integration@sumup.com, pos.cs.uk.ie@sumup.com
**CC:** partnersupport@sumup.com
**Subject:** Third-Party Loyalty Gateway Partnership Request - PunktePass

---

Dear SumUp Integration Team,

We are writing to request partnership integration with the SumUp Third-Party Loyalty Gateway for our loyalty platform, **PunktePass**.

### About PunktePass

PunktePass is a customer loyalty program operating in Germany, Austria, and Romania. We provide local businesses with a digital loyalty solution that allows customers to earn and redeem points at participating merchants.

- **Website:** https://punktepass.de
- **Active merchants:** 50+ stores in DACH region
- **Customer base:** 5,000+ registered users
- **Industry focus:** Retail, gastronomy, services

### Technical Integration

We have already developed our API to be fully compatible with the SumUp Third-Party Loyalty Gateway specification:

- **API Base URL:** `https://punktepass.de/wp-json/punktepass/v1/pos-gateway`
- **Supported endpoints:**
  - Customer lookup by QR code
  - Rewards retrieval
  - Reward application (money_off, free_product)
  - Transaction completion webhook
  - Transaction cancellation

- **Authentication:** API key + Verification-Token header
- **Response format:** JSON (SumUp compatible)

### Integration Documentation

Full API documentation is available at: https://punktepass.de/api-docs/pos-gateway/

### Request

We would like to:
1. Register as an official Third-Party Loyalty provider
2. Request a demo/test account for integration testing
3. Obtain the necessary Vendor-Id token if required

### Merchant Interest

Several of our existing merchants are already using SumUp POS terminals and have expressed interest in integrating PunktePass with their SumUp systems.

### Contact Information

**Company:** PunktePass
**Contact Person:** [Your Name]
**Email:** integration@punktepass.de
**Phone:** [Your Phone]
**Address:** [Your Business Address]

We look forward to your response and the opportunity to integrate with SumUp.

Best regards,
[Your Name]
PunktePass Integration Team

---

## 2. ready2order Developer Registration Follow-up

**To:** api@ready2order.com
**Subject:** ready2order API Integration Request - PunktePass Loyalty Program

---

Dear ready2order API Team,

We have registered on the ready2order Developer Portal and would like to integrate our loyalty platform, **PunktePass**, with ready2order POS systems.

### Integration Purpose

We want to enable ready2order merchants to:
- Scan customer QR codes at checkout
- Display customer point balance
- Apply loyalty rewards/discounts
- Automatically earn points on purchases

### Technical Setup

- **Developer Account:** [Your registered email]
- **API Integration Type:** OAuth2 / Account Token
- **Webhook Endpoint:** `https://punktepass.de/wp-json/punktepass/v1/pos-gateway/webhook`

### Questions

1. Is there an official "Loyalty Integration" module in ready2order?
2. Can we trigger actions when a sale is completed (webhook/event)?
3. Is there documentation for displaying custom UI in the POS app?

### About PunktePass

PunktePass is a loyalty program serving businesses in Germany, Austria, and Romania. We have existing merchants using ready2order who want to connect their loyalty program.

Thank you for your support.

Best regards,
[Your Name]
PunktePass

---

## 3. Zettle (PayPal) Developer Application

**To:** zettle-integrations@paypal.com
**Subject:** Zettle Loyalty Integration Partner Request - PunktePass

---

Dear Zettle Integrations Team,

We are interested in becoming an integration partner for Zettle POS systems to connect our customer loyalty platform, PunktePass.

### Company Information

- **Company:** PunktePass
- **Location:** Germany
- **Business Type:** Customer Loyalty SaaS Platform
- **Website:** https://punktepass.de

### Integration Scope

We would like to integrate with Zettle to:
- Read customer data via QR code scan
- Apply loyalty discounts at checkout
- Receive transaction webhooks for point earning

### Technical Capabilities

- REST API ready for integration
- OAuth2 / API key authentication supported
- GDPR compliant data handling
- Webhook endpoints available

### Questions

1. Does Zettle support third-party loyalty integrations?
2. What is the process to become an integration partner?
3. Is there a developer sandbox available?

We look forward to hearing from you.

Best regards,
[Your Name]
PunktePass

---

## Email Templates - Quick Reference

| POS System | Primary Email | Response Time |
|------------|---------------|---------------|
| **SumUp** | integration@sumup.com | 1-2 weeks |
| **ready2order** | api@ready2order.com | 1 week |
| **Zettle** | zettle-integrations@paypal.com | 2-4 weeks |

---

## Follow-up Schedule

- **Day 0:** Send initial email
- **Day 7:** Follow-up if no response
- **Day 14:** Second follow-up with merchant reference
- **Day 21:** Phone call (if number available)

---

## Checklist Before Sending

- [ ] Replace [Your Name] with actual name
- [ ] Replace [Your Phone] with actual phone
- [ ] Replace [Your Business Address] with actual address
- [ ] Verify https://punktepass.de is accessible
- [ ] Test API endpoint is working
- [ ] Prepare demo account for testing
