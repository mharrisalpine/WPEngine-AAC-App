# AAC Member Portal Codebase Reference

## Overview

This project combines:

- a React/Vite member application in [`src/`](/Users/mharris/Desktop/WordPress%20Page%20Matched%20Web%20App/src)
- a WordPress plugin wrapper in [`wordpress/aac-member-portal/`](/Users/mharris/Desktop/WordPress%20Page%20Matched%20Web%20App/wordpress/aac-member-portal)
- Paid Memberships Pro as the live membership, billing, and checkout engine

The React app is the member-facing experience. The WordPress plugin:

- mounts the React bundle into WordPress
- injects runtime config into the app
- exposes REST endpoints for auth and member profile data
- skins PMPro pages so they visually match the app
- extends PMPro checkout with AAC-specific fields, pricing rules, and summaries

## Project Structure

### Frontend app

- [`src/App.jsx`](/Users/mharris/Desktop/WordPress%20Page%20Matched%20Web%20App/src/App.jsx)
  - top-level route shell and app layout behavior
- [`src/main.jsx`](/Users/mharris/Desktop/WordPress%20Page%20Matched%20Web%20App/src/main.jsx)
  - app bootstrap and routes
- [`src/contexts/AppAuthContext.jsx`](/Users/mharris/Desktop/WordPress%20Page%20Matched%20Web%20App/src/contexts/AppAuthContext.jsx)
  - session, login, logout, password-change, profile refresh
- [`src/lib/memberApi.js`](/Users/mharris/Desktop/WordPress%20Page%20Matched%20Web%20App/src/lib/memberApi.js)
  - frontend wrappers around WordPress REST endpoints
- [`src/lib/apiClient.js`](/Users/mharris/Desktop/WordPress%20Page%20Matched%20Web%20App/src/lib/apiClient.js)
  - request transport, nonce handling, auth retry logic
- [`src/components/`](/Users/mharris/Desktop/WordPress%20Page%20Matched%20Web%20App/src/components)
  - reusable UI such as header, sidebar, membership card, navigation
- [`src/pages/`](/Users/mharris/Desktop/WordPress%20Page%20Matched%20Web%20App/src/pages)
  - route screens like profile, account, donate, grants, lodging

### WordPress plugin

- [`wordpress/aac-member-portal/aac-member-portal.php`](/Users/mharris/Desktop/WordPress%20Page%20Matched%20Web%20App/wordpress/aac-member-portal/aac-member-portal.php)
  - main plugin bootstrap, PMPro checkout customization, plugin settings wiring, deploy/runtime config
- [`wordpress/aac-member-portal/includes/class-aac-member-portal-api.php`](/Users/mharris/Desktop/WordPress%20Page%20Matched%20Web%20App/wordpress/aac-member-portal/includes/class-aac-member-portal-api.php)
  - REST endpoints, profile assembly, auth, password change, contact form, member data shaping
- [`wordpress/aac-member-portal/includes/class-aac-member-portal-pmpro.php`](/Users/mharris/Desktop/WordPress%20Page%20Matched%20Web%20App/wordpress/aac-member-portal/includes/class-aac-member-portal-pmpro.php)
  - PMPro membership reads, billing URLs, transactions, card-on-file lookup
- [`wordpress/aac-member-portal/includes/class-aac-member-portal-admin.php`](/Users/mharris/Desktop/WordPress%20Page%20Matched%20Web%20App/wordpress/aac-member-portal/includes/class-aac-member-portal-admin.php)
  - WordPress admin settings module for content, layout, navigation, and contact settings
- [`wordpress/aac-member-portal/templates/fullscreen-portal.php`](/Users/mharris/Desktop/WordPress%20Page%20Matched%20Web%20App/wordpress/aac-member-portal/templates/fullscreen-portal.php)
  - full-page AAC shell for PMPro/public pages
- [`wordpress/aac-member-portal/templates/managed-shell-content.php`](/Users/mharris/Desktop/WordPress%20Page%20Matched%20Web%20App/wordpress/aac-member-portal/templates/managed-shell-content.php)
  - inline AAC shell wrapper for managed PMPro content

### Build and packaging

- [`tools/package-wordpress-plugin.js`](/Users/mharris/Desktop/WordPress%20Page%20Matched%20Web%20App/tools/package-wordpress-plugin.js)
  - copies built frontend assets into the plugin package
- [`dist/`](/Users/mharris/Desktop/WordPress%20Page%20Matched%20Web%20App/dist)
  - generated Vite app output
- [`wordpress/aac-member-portal/app/`](/Users/mharris/Desktop/WordPress%20Page%20Matched%20Web%20App/wordpress/aac-member-portal/app)
  - packaged app assets inside the WordPress plugin

## Runtime Flow

### 1. App boot

WordPress renders the shortcode or managed shell page and injects config into `window.AAC_MEMBER_PORTAL_CONFIG`.

The frontend reads that config and uses it for:

- REST base URL
- REST nonce
- shell URLs
- WordPress/PMPro integration flags
- admin-configured content/navigation

### 2. Authentication

The app authenticates against WordPress users through the custom REST API.

Primary flow:

1. React calls [`memberApi.js`](/Users/mharris/Desktop/WordPress%20Page%20Matched%20Web%20App/src/lib/memberApi.js)
2. requests go through [`apiClient.js`](/Users/mharris/Desktop/WordPress%20Page%20Matched%20Web%20App/src/lib/apiClient.js)
3. WordPress authenticates and returns normalized user/profile payloads
4. PMPro membership data is merged into that payload on the backend

### 3. Member profile data

The profile returned by `/wp-json/aac/v1/me` contains:

- `account_info`
- `profile_info`
- `benefits_info`
- `membership_actions`
- `grant_applications`
- `family_membership`
- `connected_accounts`

The frontend should treat this payload as the main source of truth for member rendering.

## Data Model

### WordPress user meta used by the portal

- `aac_account_info`
- `aac_profile_info`
- `aac_benefits_info`
- `aac_grant_applications`
- `aac_magazine_addons`
- `aac_membership_discount_type`
- `aac_partner_family_config`
- `aac_connected_accounts`

### Reportable flat meta

The plugin also stores report-friendly flat values:

- `aac_tshirt_size`
- `aac_publication_pref`
- `aac_guidebook_pref`
- `aac_magazine_subscription_labels`
- `aac_has_alpinist_subscription`
- `aac_has_backcountry_subscription`
- `aac_partner_family_mode`
- `aac_partner_family_additional_adult`
- `aac_partner_family_dependents`

These are intended for exports, reporting, CRM syncs, or admin filtering.

## PMPro Integration

### Membership levels currently recognized by the portal

- `Free`
- `Supporter`
- `Partner`
- `Partner Family`
- `Leader`
- `Advocate`
- `GRF`

`GRF` is treated as a hidden/manual tier in the frontend. It is recognized in the portal data model and benefit mapping, but it is not shown in public signup or self-service upgrade selectors.

### PMPro pages styled by the portal shell

- membership account
- membership billing
- membership checkout
- membership cancel
- membership orders
- membership confirmation

### PMPro checkout customizations

The plugin currently augments checkout with:

- membership discounts
- Partner-family configuration
- magazine add-ons
- donation selector
- profile and member preference field grouping
- order summary UI

## Partner Family Membership

The Partner-family flow is implemented in the WordPress plugin so that pricing affects both:

- the initial charge
- the recurring PMPro billing amount

### Rules

- only available through the Partner checkout flow
- family mode can include:
  - one additional adult at 40% off Partner list price
  - up to three dependents at `$45/year` each
- when family mode is selected, the plugin can shift the checkout request onto PMPro's `Partner Family` level before PMPro processes the order

### Stored family data

Family configuration is stored in:

- `aac_partner_family_config`

Connected-account invitation slots are stored in:

- `aac_connected_accounts`

Each connected-account record currently stores:

- type
- label
- status
- invite code
- optional child user info
- recurring price contribution

### Current scope of child-account support

The current implementation generates invitation codes and lists family-member slots in the parent profile. It establishes the data model needed for future child-account redemption/claiming. If a full self-service invite redemption flow is needed later, build that as a separate onboarding step on top of the stored `invite_code` records.

## Admin Settings Module

The plugin exposes an `AAC Portal` admin area for non-code changes.

Current configurable areas include:

- shared content blocks
- contact form recipient
- sidebar and nav presentation
- visibility/order of supported nav items
- selected design tokens and shell content

The admin settings are intended to reduce code edits for routine content/design changes.

## Security Notes

Current hardened areas:

- public auth routes are rate-limited
- password reset no longer reveals whether an email exists
- profile email/core WordPress user sync is fail-safe
- fake backend fallback was removed from production behavior

Known long-term maintenance hotspot:

- PMPro checkout DOM reshaping in the managed shell templates. PMPro markup or add-on updates can require template maintenance.

## Build and Deploy

### Local build

```bash
npm run build
```

### Package WordPress plugin

```bash
npm run package:wordpress
```

This copies the current frontend bundle into:

- [`wordpress/aac-member-portal/app/`](/Users/mharris/Desktop/WordPress%20Page%20Matched%20Web%20App/wordpress/aac-member-portal/app)

### Deploy

Current staging deploys in this project use:

- the packaged zip at [`wordpress/aac-member-portal.zip`](/Users/mharris/Desktop/WordPress%20Page%20Matched%20Web%20App/wordpress/aac-member-portal.zip)
- the helper script in [`tools/deploy-wordpress-plugin.sh`](/Users/mharris/Desktop/WordPress%20Page%20Matched%20Web%20App/tools/deploy-wordpress-plugin.sh)

## Git and Rollback

Recommended workflow:

1. create a local commit for each stable portal update
2. tag major deploy checkpoints
3. push to a remote GitHub repository once a remote URL and token-based auth are configured

If no Git remote is configured, local commits and tags still provide rollback points.

## Suggested Next Enhancements

- self-service child-account invite redemption
- automated integration tests for PMPro checkout shell behavior
- CRM sync plugin with explicit ownership rules
- admin UI for family membership reporting and invite management
