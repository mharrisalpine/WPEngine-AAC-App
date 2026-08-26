# AAC Member App

The AAC Member App is the American Alpine Club's member-facing React application and its WordPress plugin backend. It provides an app-style interface while WordPress and Paid Memberships Pro (PMPro) remain responsible for authentication, membership records, checkout, billing, and subscriptions.

## What the app provides

- Public membership landing and join experiences
- WordPress sign-in, password reset, and account security
- Member profile and membership management
- Membership upgrades, renewals, cancellation, and billing links
- Family plans and linked member accounts
- Member benefits, discounts, publications, and rescue coverage
- AAC-branded PMPro checkout and account screens
- WordPress administration for portal content, settings, imports, and member reporting

## Architecture

### React frontend

The frontend lives in `src/` and uses React, Vite, React Router, Tailwind CSS, and Radix UI components.

WordPress injects runtime configuration when it loads the app. The frontend then calls the plugin's REST endpoints using the member's WordPress session and a WordPress REST nonce. During standalone local development, selected API calls can fall back to the fake member database in `src/lib/fakeMemberDb.js`.

Important frontend areas:

- `src/main.jsx` — application bootstrap and routes
- `src/App.jsx` — primary application shell
- `src/pages/` — route-level member experiences
- `src/components/` — shared interface components
- `src/contexts/AppAuthContext.jsx` — authentication and profile state
- `src/lib/memberApi.js` — member REST API calls
- `src/lib/backendConfig.js` — WordPress runtime/API configuration
- `src/lib/portalSettings.js` — portal content and design defaults

### WordPress plugin

The installable plugin lives in `wordpress/aac-member-portal/`. It:

- Registers the `[aac_member_portal]` shortcode
- Loads the compiled React assets from `wordpress/aac-member-portal/app/`
- Provides authenticated REST endpoints for the frontend
- Normalizes WordPress user data and PMPro membership data
- Customizes PMPro checkout and managed account pages
- Implements linked family-account behavior
- Provides AAC Portal administration, imports, exports, settings, and diagnostics

Important backend files:

- `aac-member-portal.php` — plugin bootstrap, hooks, checkout behavior, and routing
- `includes/class-aac-member-portal-api.php` — REST endpoints and member payloads
- `includes/class-aac-member-portal-admin.php` — WordPress administration
- `includes/class-aac-member-portal-pmpro.php` — PMPro integration
- `includes/class-aac-member-portal-member-database.php` — reporting database and exports
- `includes/class-aac-member-portal-group-accounts.php` — family and linked accounts
- `templates/` — AAC wrappers for checkout and managed PMPro pages

### Paid Memberships Pro

PMPro is the operational membership system inside WordPress. It owns membership levels, orders, payments, renewals, expiration, subscription state, and billing management. The AAC plugin adds the club-specific presentation and business rules around those capabilities.

### Data flow

For a signed-in member:

1. WordPress renders the portal shortcode and injects runtime configuration.
2. The React bundle starts and requests the current member profile.
3. The plugin combines WordPress user metadata, AAC profile records, and PMPro membership/order data.
4. The API returns a normalized member payload.
5. React renders the appropriate profile, membership, publication, benefit, and linked-account screens.

For checkout:

1. A visitor selects a membership option in the React app.
2. The app opens the corresponding PMPro checkout flow.
3. The plugin applies AAC-specific layout, fields, discounts, family-plan rules, and preferences.
4. PMPro processes the order and stores the membership and subscription state.
5. The portal reads that updated state on the member's next profile request.

Salesforce synchronization is maintained separately in [`mharrisalpine/AAC-Hardened-SF-Sync`](https://github.com/mharrisalpine/AAC-Hardened-SF-Sync). The earlier [`mharrisalpine/aac-salesforce-sync`](https://github.com/mharrisalpine/aac-salesforce-sync) repository remains unchanged as a fallback.

## Requirements

- Node.js (the expected version is recorded in `.nvmrc`)
- npm
- WordPress
- Paid Memberships Pro
- A local WordPress environment or access to a staging site for integration testing

Some checkout features depend on separately licensed/configured PMPro add-ons. See `wordpress/aac-member-portal/README.md` for the current list.

## Local development

Install dependencies:

```bash
npm ci
```

Start the Vite development server:

```bash
npm run dev
```

Create a production frontend build:

```bash
npm run build
```

## Build the WordPress plugin

Run:

```bash
npm run package:wordpress
```

This command:

1. Builds the React frontend into `dist/`.
2. Replaces `wordpress/aac-member-portal/app/` with the new build.
3. Creates stable JavaScript, CSS, and media aliases used by the plugin.
4. Produces `wordpress/aac-member-portal.zip` for installation.

The ZIP is ignored by Git; GitHub should contain the source and packaged app assets, not a collection of historical release archives.

## WordPress installation

1. Install and configure Paid Memberships Pro.
2. Run `npm run package:wordpress`.
3. In WordPress, open **Plugins → Add New → Upload Plugin**.
4. Upload `wordpress/aac-member-portal.zip` and activate **AAC Member Portal**.
5. Create a WordPress page containing a Shortcode block with:

```text
[aac_member_portal]
```

6. Configure the portal under **AAC Portal** in WordPress administration.
7. Clear WordPress, CDN, and browser caches after replacing frontend assets.

Deploy to staging and test authentication, profile updates, checkout, renewals, family accounts, benefits, and administrative functions before deploying the same build to production.

## Repository layout

```text
.
├── src/                         React application
├── public/                      Static frontend assets
├── wordpress/aac-member-portal WordPress plugin source and built app
├── docs/                        Architecture and operating documentation
├── tools/                       Frontend generation and plugin packaging
├── package.json                 npm commands and dependencies
└── vite.config.js               Vite production/development configuration
```

## Documentation

- [WordPress plugin guide](wordpress/aac-member-portal/README.md)
- [Codebase reference](docs/codebase-reference.md)
- [Change-management and testing guide](docs/change-management.md)
- [WordPress hosting](docs/wordpress-hosting.md)
- [WordPress endpoints](docs/wordpress-endpoints.md)
- [Family memberships](docs/family-membership.md)
- [Mobile build notes](docs/mobile-build.md)

## Development workflow

Use a branch and pull request for each change:

```bash
git checkout -b feature/short-description
npm ci
npm run build
git add <changed-files>
git commit -m "Describe the change"
git push -u origin feature/short-description
```

Never commit WordPress passwords, API credentials, Salesforce secrets, payment information, member exports, database dumps, or production configuration. Use GitHub Actions secrets or the hosting provider's secret manager for deployment credentials.
