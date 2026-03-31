# AgentClerk Backend — Shopify Platform Support Requirements

## Overview

The backend at `app.agentclerk.io/api` currently serves WordPress plugin installs. To support the new Shopify app, the backend needs platform awareness on install registration and a few new endpoints. Most existing endpoints (billing, AI proxy, fees, promo codes) work unchanged.

**Principle:** The Shopify app authenticates with the same `X-AgentClerk-Secret` + `X-AgentClerk-Site` headers as WordPress. The backend doesn't need to know about Shopify OAuth or access tokens — the app server handles that.

---

## Database Schema Change

Add platform tracking to the installs table:

```sql
ALTER TABLE installs ADD COLUMN platform VARCHAR(20) NOT NULL DEFAULT 'wordpress';
ALTER TABLE installs ADD COLUMN shopify_shop_id VARCHAR(100) NULL;
ALTER TABLE installs ADD COLUMN shopify_shop_domain VARCHAR(255) NULL;
CREATE INDEX idx_installs_shopify_domain ON installs(shopify_shop_domain);
```

---

## Modified Endpoints

### 1. `POST /api/installs/register` — Add Platform Fields

**Current request body (WordPress):**
```json
{
  "siteUrl": "https://example.com",
  "tier": "byok",
  "stripePaymentMethodId": "pm_xxx"
}
```

**New request body (Shopify):**
```json
{
  "siteUrl": "https://mystore.myshopify.com",
  "tier": "byok",
  "platform": "shopify",
  "shopifyShopId": "gid://shopify/Shop/12345",
  "shopifyShopDomain": "mystore.myshopify.com",
  "stripePaymentMethodId": "pm_xxx",
  "promoCode": "BETA50"
}
```

**Changes:**
- Accept optional `platform` field (default: `"wordpress"`)
- Accept optional `shopifyShopId` and `shopifyShopDomain`
- Store these on the install record
- Response is identical — returns `installSecret`, `stripePublishableKey`, etc.

**WordPress installs are unaffected** — they don't send `platform`, so it defaults to `"wordpress"`.

### 2. `GET /api/billing/status` — Add License Fields (if not already)

**Ensure the response includes:**
```json
{
  "billingStatus": "active",
  "accruedFees": 0.00,
  "cardLast4": "4242",
  "stripeCustomerId": "cus_xxx",
  "licenseStatus": "active",
  "licenseKey": "AC-XXXX-XXXX-XXXX"
}
```

The WordPress plugin now reads `licenseStatus` and `licenseKey` from this response (added in recent update). The Shopify app will do the same. If these fields are already returned, no change needed.

---

## New Endpoints

### 3. `POST /api/installs/verify-shopify` — Verify Shopify Install

**Purpose:** When a Shopify merchant installs the app, the app server calls this to register the install and get/create the install secret. This is the Shopify equivalent of the WordPress `register_install` flow but without Stripe (billing comes later).

**Request:**
```json
{
  "shopDomain": "mystore.myshopify.com",
  "shopifyShopId": "gid://shopify/Shop/12345",
  "shopName": "My Store",
  "email": "owner@mystore.com",
  "platform": "shopify"
}
```

**Auth:** No `X-AgentClerk-Secret` header (the install doesn't exist yet). Authenticate via a shared app-level secret or by verifying the Shopify shop exists.

**Response — New install:**
```json
{
  "installSecret": "newly-generated-secret",
  "isNew": true
}
```

**Response — Returning install (reinstall):**
```json
{
  "installSecret": "existing-secret",
  "isNew": false,
  "tier": "byok",
  "licenseStatus": "active",
  "billingStatus": "active"
}
```

**Logic:**
1. Check if an install exists for this `shopifyShopDomain`
2. If not, create a new install record with `platform: "shopify"`, generate `installSecret`
3. If yes, return the existing install secret and current status
4. Never delete install records on uninstall — just mark inactive

### 4. `POST /api/installs/uninstall` — Mark Install Inactive

**Purpose:** Called by the Shopify app's webhook handler when a merchant uninstalls.

**Request:**
```json
{
  "platform": "shopify",
  "shopDomain": "mystore.myshopify.com",
  "shopifyShopId": "gid://shopify/Shop/12345"
}
```

**Auth:** `X-AgentClerk-Secret` + `X-AgentClerk-Site` headers (the app server sends these before it loses the tokens).

**Response:**
```json
{
  "status": "deactivated"
}
```

**Logic:**
- Set install status to `"inactive"` or `"uninstalled"`
- Do NOT delete the record — merchant may reinstall
- Stop billing for this install
- Keep conversation/fee history for potential reinstatement

---

## Unchanged Endpoints

These work as-is for both WordPress and Shopify. The Shopify app calls them with the same headers:

| Endpoint | Method | Purpose |
|---|---|---|
| `/api/billing/status` | GET | Poll billing + license status |
| `/api/billing/turnkey-checkout` | POST | Create Stripe checkout for TurnKey tier |
| `/api/billing/card-update` | POST | Stripe card update portal |
| `/api/license/checkout` | POST | Create Stripe checkout for lifetime license |
| `/api/license/activate` | POST | Activate lifetime license |
| `/api/agent/chat` | POST | TurnKey AI proxy (Anthropic) |
| `/api/fees` | POST | Record transaction fee |
| `/api/promo/validate` | POST | Validate promo code |
| `/api/plugin/info` | GET | Self-hosted update check (WordPress only, ignored by Shopify) |

---

## Fee Recording — Shopify Differences

When a Shopify order is completed via an AgentClerk quote, the app server calls `/api/fees`:

```json
{
  "wcOrderId": null,
  "shopifyOrderId": "gid://shopify/Order/12345",
  "productName": "Pro Plan",
  "productId": "gid://shopify/Product/67890",
  "saleAmount": 99.00,
  "feeAmount": 0.99,
  "buyerType": "human",
  "platform": "shopify"
}
```

**Changes to `/api/fees`:**
- Accept optional `shopifyOrderId` field
- Accept optional `platform` field
- `wcOrderId` can be null for Shopify orders
- Fee calculation logic is the same (1% min $1 for BYOK, 1.5% min $1.99 for TurnKey, $0 for lifetime)

---

## Admin Dashboard — Shopify Installs

If the backend has an admin dashboard for managing installs:

- Add a `platform` column/filter to the installs list
- Show Shopify shop domain alongside WordPress site URL
- The "Grant Lifetime License" action works the same — it sets `licenseStatus: "active"` on the install record, and the Shopify app picks it up via `/billing/status` polling (same as WordPress)

---

## Summary of Changes

| Change | Type | Effort |
|---|---|---|
| Add `platform`, `shopify_shop_id`, `shopify_shop_domain` to installs table | Schema | Small |
| Accept `platform` field in `POST /installs/register` | Modify | Small |
| New `POST /installs/verify-shopify` endpoint | New | Medium |
| New `POST /installs/uninstall` endpoint | New | Small |
| Accept `shopifyOrderId` + `platform` in `POST /fees` | Modify | Small |
| Ensure `licenseStatus` + `licenseKey` in `GET /billing/status` response | Verify | Trivial |
| Admin dashboard platform filter | UI | Small |

**Total effort:** ~1-2 days of backend work. No changes to AI proxy, billing logic, or promo code handling.

---

## Timeline Dependency

The Shopify app can be developed in parallel with these backend changes. The app will need:

1. `POST /installs/verify-shopify` — needed at app install time (day 1)
2. `GET /billing/status` with license fields — needed for settings page
3. Everything else — needed when features are built (billing, fees, etc.)

The app can use mock responses during development until the backend endpoints are ready.
