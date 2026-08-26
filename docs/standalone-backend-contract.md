# Standalone Backend Contract

This app can now target a standalone backend instead of WordPress.

The frontend has been refactored to use generic member and store APIs:

- Auth context: `src/contexts/AppAuthContext.jsx`
- Member API: `src/lib/memberApi.js`
- Store API: `src/api/StoreApi.js`

## Runtime configuration

Set these environment variables for a standalone deployment:

```bash
VITE_BACKEND_MODE=standalone
VITE_MEMBER_API_BASE=https://api.your-domain.com
VITE_COMMERCE_PROVIDER=shopify
```

Optional runtime config from the hosting page:

```html
<script>
  window.AAC_MEMBER_PORTAL_CONFIG = {
    apiBase: "https://api.your-domain.com",
    backendMode: "standalone",
    commerceProvider: "shopify"
  };
</script>
```

## Auth and member profile endpoints

### `POST /login`

Request:

```json
{
  "email": "member@example.com",
  "password": "super-secret"
}
```

Response:

```json
{
  "token": "jwt-or-session-token",
  "session": {
    "user": {
      "id": "usr_123",
      "email": "member@example.com"
    }
  },
  "user": {
    "id": "usr_123",
    "email": "member@example.com"
  },
  "profile": {
    "account_info": {
      "first_name": "Jane",
      "last_name": "Climber",
      "name": "Jane Climber",
      "email": "member@example.com",
      "photo_url": "",
      "phone": "",
      "street": "",
      "city": "",
      "state": "",
      "zip": "",
      "country": "US",
      "size": "M",
      "publication_pref": "Digital",
      "auto_renew": true,
      "payment_method": "Visa ending in 4242"
    },
    "profile_info": {
      "member_id": "AAC-12345",
      "tier": "Partner",
      "renewal_date": "2027-03-27",
      "status": "Active"
    },
    "benefits_info": {
      "rescue_amount": 50000,
      "medical_amount": 5000
    },
    "membership_actions": {
      "account_url": "https://billing.stripe.com/p/session/...",
      "billing_url": "https://billing.stripe.com/p/session/...",
      "cancel_url": "https://billing.stripe.com/p/session/...",
      "current_level_id": "price_partner",
      "current_level_checkout_url": "https://checkout.stripe.com/...",
      "levels": {
        "Supporter": {
          "checkout_url": "https://checkout.stripe.com/..."
        },
        "Partner": {
          "checkout_url": "https://checkout.stripe.com/..."
        },
        "Leader": {
          "checkout_url": "https://checkout.stripe.com/..."
        },
        "Lifetime": {
          "checkout_url": "https://checkout.stripe.com/..."
        }
      }
    }
  }
}
```

### `POST /register`

Request:

```json
{
  "email": "member@example.com",
  "password": "super-secret",
  "first_name": "Jane",
  "last_name": "Climber"
}
```

Response: same shape as `POST /login`

### `POST /logout`

Response:

```json
{
  "success": true
}
```

### `POST /reset-password`

Request:

```json
{
  "email": "member@example.com"
}
```

### `GET /me`

Returns the same payload shape as `POST /login`.

### `PATCH /profile`

Request:

```json
{
  "account_info": {
    "first_name": "Jane",
    "last_name": "Climber",
    "phone": "555-555-5555",
    "street": "123 Main St",
    "city": "Golden",
    "state": "CO",
    "zip": "80401",
    "country": "US"
  }
}
```

The backend should:

1. persist the member profile
2. return the updated `profile`

## Stripe-backed membership, donation, and billing

The frontend now expects hosted action URLs instead of owning membership state changes itself.

### Membership handling

Populate `profile.membership_actions` from your backend using Stripe:

- `levels.{TierName}.checkout_url`
- `account_url`
- `billing_url`
- `cancel_url`

Recommended backend behavior:

- Join / upgrade / downgrade / renew:
  - create Stripe Checkout sessions server-side
  - return hosted Checkout URLs
- Manage billing / cancel:
  - create Stripe Customer Portal sessions server-side
  - return hosted portal URLs

The frontend will open those URLs directly.

## Contact and content endpoints

### `POST /contact`

Request:

```json
{
  "name": "Jane Climber",
  "email": "member@example.com",
  "message": "I need help with my account."
}
```

### `GET /podcasts`

Response:

```json
{
  "podcasts": [
    {
      "title": "Episode title",
      "published_at": "2026-03-20",
      "description": "Episode summary",
      "embed_url": "https://...",
      "source_url": "https://..."
    }
  ]
}
```

## Shopify-backed store endpoints

`src/api/StoreApi.js` now supports a standalone backend contract.

If `VITE_COMMERCE_PROVIDER=shopify`, implement these endpoints on your backend:

### `GET /store/products`

Response should match the existing product list shape used by the UI:

```json
{
  "count": 2,
  "offset": 0,
  "limit": 20,
  "products": [
    {
      "id": "prod_123",
      "title": "AAC Camp Mug",
      "subtitle": "Enamel mug",
      "description": "<p>Product HTML</p>",
      "image": "https://...",
      "price_in_cents": 2400,
      "currency": "USD",
      "purchasable": true,
      "order": 1,
      "images": [],
      "options": [],
      "variants": [],
      "collections": [],
      "additional_info": [],
      "custom_fields": [],
      "related_products": [],
      "updated_at": "2026-03-27T00:00:00.000Z"
    }
  ]
}
```

### `GET /store/products/:id`

Return the product detail payload in the same shape the current product detail screen expects.

### `GET /store/products/quantities`

Query params:

- `product_ids`
- `fields`

Return the quantity data the store UI uses for inventory.

### Recommended backend Shopify flow

1. Backend reads products from Shopify Storefront API
2. Backend normalizes them to the current frontend shape
3. Frontend continues rendering without knowing Shopify specifics

## Transactions endpoint

Recommended for replacing the current local transaction ledger:

### `GET /transactions`

Response:

```json
{
  "transactions": [
    {
      "id": "txn_123",
      "kind": "Membership",
      "amount": 95,
      "description": "Partner membership renewal",
      "status": "Paid",
      "referenceId": "sub_123",
      "createdAt": "2026-03-27T18:00:00.000Z",
      "metadata": {}
    }
  ]
}
```

## Event endpoints

Recommended standalone event contract:

### `GET /events`

Return event cards for the current event screen.

### `POST /events/:id/register`

Request:

```json
{
  "registration": {
    "first_name": "Jane",
    "last_name": "Climber",
    "email": "member@example.com"
  }
}
```

If paid:

- backend creates Stripe Checkout session
- return hosted URL

If free:

- create the registration directly
- return success

## Webhooks the backend should support

### `POST /webhooks/stripe`

Handle:

- checkout.session.completed
- customer.subscription.created
- customer.subscription.updated
- customer.subscription.deleted
- invoice.paid

### `POST /webhooks/shopify`

Handle:

- orders/create
- orders/paid
- refunds/create
