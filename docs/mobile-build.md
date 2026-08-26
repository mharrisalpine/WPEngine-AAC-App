# Mobile Build Notes

This app is now prepared for an iPhone and Android wrapper using Capacitor.

## What changed

- Native builds can use hash routing by setting `VITE_APP_RUNTIME=mobile`.
- Safe-area padding is applied to the fixed header and bottom navigation for iPhone devices with notches and home indicators.
- The WordPress API base can be pointed at a live site with `VITE_WORDPRESS_API_BASE`.
- External links and checkout now go through a single mobile-aware navigation helper.

## Recommended environment

For native mobile builds:

```bash
VITE_APP_RUNTIME=mobile
VITE_WORDPRESS_API_BASE=https://your-wordpress-site.com/wp-json/aac/v1
VITE_MOBILE_APP_URL=https://your-mobile-hosted-app.example.com
```

## Recommended install steps

Install Capacitor packages:

```bash
npm install @capacitor/cli @capacitor/core
npm install @capacitor/android @capacitor/ios --save-dev
```

## Suggested workflow

Build the web app:

```bash
npm run build
```

Initialize native platforms if they do not exist yet:

```bash
npx cap add android
npx cap add ios
```

Sync the built app into native projects:

```bash
npx cap sync
```

Open the native projects:

```bash
npx cap open android
npx cap open ios
```

## Important backend note

Mobile builds should point to a real HTTPS WordPress site. Relative API paths such as `/wp-json/aac/v1` only work when the frontend is served from the same origin as WordPress.

## Checkout return URLs

The checkout flow now builds success and cancel URLs through a helper that supports mobile wrappers.

- On web builds it uses `window.location.origin`.
- On mobile builds it uses `VITE_MOBILE_APP_URL` first, then `VITE_PUBLIC_APP_URL`, then falls back to the local wrapper origin.

For a production mobile app, set `VITE_MOBILE_APP_URL` to a real hosted URL or a universal-link/deep-link target that your checkout provider can redirect back to.

## Common follow-up work

- Add Capacitor plugins for browser, device, keyboard, and splash screen if needed.
- Replace the starter mobile browser helper with the official Capacitor Browser plugin package when native dependencies are installed.
- Consider deep links or universal links so checkout can return directly into the app instead of a hosted fallback page.
