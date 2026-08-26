# AAC Member Portal WordPress Plugin

Use this plugin folder as the installable WordPress package for the AAC member portal.

## Build frontend assets into this plugin

From the project root:

```bash
npm run package:wordpress
```

That copies the latest `dist/` frontend build into:

- `wordpress/aac-member-portal/app/`

## Install

1. Install and activate **Paid Memberships Pro** on the same site (membership tiers, payments, account page). To show the optional donation step during checkout, also install and configure the **Paid Memberships Pro Donations Add On**; the portal styles the add-on fields but no longer creates fallback donation fields itself. To let members choose whether checkout is recurring, install and configure the **Paid Memberships Pro Auto-Renewal Checkbox Add On**; the portal styles the add-on checkbox but does not create fallback auto-renewal fields. To manage upgrade credits and renewal-date downgrades, install and configure the **Paid Memberships Pro Proration and Delayed Downgrades Add On**; the portal routes level changes through PMPro checkout and does not calculate its own upgrade proration credit.
2. Zip this folder
3. Upload it in WordPress under `Plugins > Add New > Upload Plugin`
4. Activate `AAC Member Portal`
5. Create a page with a **Shortcode** block (not Custom HTML) containing:

```text
[aac_member_portal]
```

Custom HTML blocks do **not** run shortcodes by default, so the portal shell will never load.

### Page is blank / app does not render

1. Confirm `app/assets/index-*.js` exists under this plugin (re-run `npm run package:wordpress` if missing). After each build, **replace the whole `app/` folder** (or delete `app/assets/*` before upload). If **two** `index-*.js` files exist, older WordPress versions of the plugin picked the wrong one alphabetically; current plugin code uses the **newest file by date**. Clear any CDN/host cache so the browser does not keep `index-??????.js?ver=1.0.0` from an old deploy.
2. Use the **Shortcode** block as above; page builders may need their **Shortcode** widget.
3. Open the browser **developer console** on that page: look for 404s on the plugin JS/CSS or **Content-Security-Policy** blocks on `type="module"` scripts.
4. Temporarily disable **minify/combine JavaScript** plugins (Autoptimize, WP Rocket, etc.) for that page; some break `type="module"` or move inline config after the bundle.
5. Ensure the theme calls **`wp_footer()`** (standard themes do).

After updating the React app, always rebuild and copy assets into this plugin again before redeploying.

## WordPress login and MySQL

Member access uses **WordPress authentication** (same-site cookies plus `X-WP-Nonce` on REST calls from the embedded app). User accounts live in the site’s **MySQL** database: `wp_users`, `wp_usermeta`, and session handling managed by WordPress. Portal-specific fields are stored in user meta such as `aac_account_info`, `aac_profile_info`, and `aac_benefits_info`.

## Extending the portal with another WordPress plugin

This plugin no longer performs Salesforce or CRM sync directly. If you want to sync member data with another system, do that in a separate WordPress plugin and use the portal’s WordPress hooks and user meta.

Useful integration points:

- Action: `aac_member_portal_profile_updated`
- Filter: `aac_member_portal_profile`
- User meta used by the app:
  - `aac_account_info`
  - `aac_profile_info`
  - `aac_benefits_info`

With **Paid Memberships Pro** active, membership **tier / renewal / status** come from PMPro by default. Without PMPro, the portal falls back to values stored in WordPress user meta.

## Docs

Full install and hosting instructions:

- `docs/wordpress-hosting.md`
- `docs/codebase-reference.md`
- `docs/change-management.md`
- `docs/family-membership.md`
