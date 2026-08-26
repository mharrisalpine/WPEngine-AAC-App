# WordPress Member API Contract

This frontend now expects a WordPress-backed member API under `VITE_WORDPRESS_API_BASE`.

If no environment variable is provided, it defaults to `/wp-json/aac/v1`.

## Authentication

### `POST /login`

Request:

```json
{
  "email": "member@example.com",
  "password": "secret"
}
```

Response:

```json
{
  "token": "optional-jwt-token",
  "session": {
    "user": {
      "id": 123,
      "email": "member@example.com"
    }
  },
  "user": {
    "id": 123,
    "email": "member@example.com"
  },
  "profile": {
    "account_info": {},
    "profile_info": {},
    "benefits_info": {}
  }
}
```

Notes:
- Cookie auth and bearer token auth can both work. The frontend sends `credentials: include` on every request.
- If you use JWT, return `token` and the app will store it in local storage.

### `POST /logout`

Response:

```json
{
  "success": true
}
```

### `POST /register`

Request:

```json
{
  "email": "member@example.com",
  "password": "secret",
  "username": "membername"
}
```

Response:

```json
{
  "requires_email_verification": false,
  "token": "optional-jwt-token",
  "session": {
    "user": {
      "id": 123,
      "email": "member@example.com"
    }
  },
  "user": {
    "id": 123,
    "email": "member@example.com"
  },
  "profile": {
    "account_info": {},
    "profile_info": {},
    "benefits_info": {}
  }
}
```

### `POST /reset-password`

Request:

```json
{
  "email": "member@example.com"
}
```

Response:

```json
{
  "success": true
}
```

## Member data

### `GET /me`

Response:

```json
{
  "session": {
    "user": {
      "id": 123,
      "email": "member@example.com"
    }
  },
  "user": {
    "id": 123,
    "email": "member@example.com"
  },
  "profile": {
    "account_info": {
      "name": "Jane Member",
      "email": "member@example.com",
      "photo_url": "https://example.com/avatar.jpg",
      "phone": "555-555-5555",
      "address": "123 Main St",
      "city": "Boulder",
      "state": "CO",
      "zip": "80301",
      "country": "US",
      "size": "M",
      "publication_pref": "Digital",
      "auto_renew": true,
      "payment_method": "Visa ending in 4242"
    },
    "profile_info": {
      "member_id": "AAC-12345",
      "tier": "Partner",
      "renewal_date": "2026-11-01",
      "status": "Active"
    },
    "benefits_info": {
      "rescue_amount": 50000,
      "medical_amount": 10000
    }
  }
}
```

### `PATCH /profile`

Request:

```json
{
  "account_info": {
    "name": "Jane Member",
    "phone": "555-555-5555"
  }
}
```

Response:

```json
{
  "success": true,
  "profile": {
    "account_info": {},
    "profile_info": {},
    "benefits_info": {}
  }
}
```

## Other app endpoints

### `POST /contact`

Request:

```json
{
  "name": "Jane Member",
  "email": "member@example.com",
  "message": "I need help with my membership."
}
```

Response:

```json
{
  "success": true
}
```

### `GET /podcasts`

Response:

```json
{
  "podcasts": [
    {
      "embed_url": "https://open.spotify.com/embed/episode/..."
    }
  ]
}
```
