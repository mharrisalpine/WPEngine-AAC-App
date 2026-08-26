# AAC Member Portal: WordPress Hosting

This project is designed to run inside WordPress as an embedded React app.

The packaged WordPress plugin lives in:

- `wordpress/aac-member-portal/`

That plugin does two jobs:

1. Exposes the REST API the React app uses for login and member profile data.
2. Embeds the built React app on a WordPress page using a shortcode.

## Recommended architecture

Use this flow in production:

1. WordPress handles the logged-in session with normal WordPress cookies.
2. The React app is embedded on a WordPress page.
3. The app calls `GET /wp-json/aac/v1/me` and other `aac/v1` endpoints on the same site.
4. WordPress reads membership data from your WordPress membership plugin and member profile data from WordPress user meta.
5. Any external CRM or third-party sync is handled by a separate WordPress plugin or custom WordPress hooks.

## Build the plugin package

From the project root:

```bash
npm install
npm run package:wordpress
```

That will:

1. Build the React app into `dist/`
2. Copy the built frontend into:
   - `wordpress/aac-member-portal/app/`

After running that command, the WordPress plugin folder is ready to zip and install.

## Install in WordPress

### Option 1: ZIP install

1. Zip the folder `wordpress/aac-member-portal/`
2. In WordPress admin go to `Plugins > Add New > Upload Plugin`
3. Upload the zip
4. Activate `AAC Member Portal`

### Option 2: Manual install

1. Copy `wordpress/aac-member-portal/` into `wp-content/plugins/`
2. In WordPress admin go to `Plugins`
3. Activate `AAC Member Portal`

## Create the member portal page

1. Create a new WordPress page, for example:
   - `Member Portal`
2. Add this shortcode to the page content:

```text
[aac_member_portal]
```

3. Publish the page
4. Visit the page while logged in as a member

The plugin injects:

- the built React bundle
- the CSS bundle
- runtime config
- a mount element with ID `aac-member-portal-root`

## Routing inside WordPress

The WordPress embed plugin forces the React app into hash routing mode:

- `/#/`
- `/#/store`
- `/#/account`

That avoids WordPress rewrite conflicts and prevents 404s for client-side routes.

## Authentication behavior

The embedded app uses same-site WordPress authentication by default.

When the page loads:

1. WordPress has already established a login cookie.
2. The app calls `GET /wp-json/aac/v1/me`
3. The plugin checks `is_user_logged_in()`
4. If logged in, WordPress returns the normalized member profile

This means the app can use the current WordPress session instead of maintaining a separate auth system.

## Membership plugin integration (Paid Memberships Pro)

The packaged API integrates with **Paid Memberships Pro** via:

- `wordpress/aac-member-portal/includes/class-aac-member-portal-pmpro.php`

PMPro active memberships drive:

- `profile_info.tier` (membership title)
- `profile_info.renewal_date` (from PMPro expiration when available)
- `profile_info.status`

Name memberships to align with benefit tiers where possible: **Supporter**, **Partner**, **Leader**, **Advocate**.

If PMPro is not active, users show as **Inactive** with tier **Supporter** until you store overrides in user meta or use the `aac_member_portal_profile` filter.

## Membership actions

The frontend prefers live PMPro URLs over the local demo checkout flow when PMPro is active. The API includes:

- `membership_actions.account_url`
- `membership_actions.billing_url`
- `membership_actions.cancel_url`
- `membership_actions.levels.{MembershipName}.checkout_url`

If PMPro URLs are not available, the app falls back to the local demo membership flow so local testing still works.

## Extending the portal with another WordPress plugin

This plugin does not sync Salesforce or any other CRM directly. If you want to connect the member portal to Salesforce or another system, implement that in a separate WordPress plugin or theme code.

Useful extension points:

- Action: `aac_member_portal_member_logged_in`
- Action: `aac_member_portal_member_registered`
- Action: `aac_member_portal_profile_updated`
- Filter: `aac_member_portal_profile`

Useful user meta keys:

- `aac_account_info`
- `aac_profile_info`
- `aac_benefits_info`
- `aac_member_id`

Recommended pattern:

1. Let this plugin own the UI and member API.
2. Let Paid Memberships Pro own membership purchases and billing URLs.
3. Let your separate WordPress plugin push or pull data from Salesforce.
4. Store any synced fields in user meta or merge them with `aac_member_portal_profile`.

## Member data shape returned to the app

The app expects this JSON shape:

```json
{
  "user": {
    "id": 123,
    "email": "member@example.com"
  },
  "session": {
    "user": {
      "id": 123,
      "email": "member@example.com"
    }
  },
  "profile": {
    "account_info": {
      "first_name": "Jane",
      "last_name": "Climber",
      "name": "Jane Climber",
      "email": "member@example.com",
      "phone": "555-555-5555",
      "street": "123 Main St",
      "city": "Golden",
      "state": "CO",
      "zip": "80401",
      "country": "US",
      "photo_url": "",
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
      "medical_amount": 5000
    }
  }
}
```
