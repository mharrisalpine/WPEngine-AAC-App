const isNativeShell = () =>
  import.meta.env.VITE_APP_RUNTIME === 'mobile' ||
  Boolean(window?.Capacitor);

const getBrowserPlugin = () => window?.Capacitor?.Plugins?.Browser;

const normalizePath = (path) => {
  if (!path) {
    return '/';
  }

  return path.startsWith('/') ? path : `/${path}`;
};

const trimTrailingSlash = (value) => value.replace(/\/$/, '');

export async function openExternalUrl(url) {
  if (!url) {
    return;
  }

  const browserPlugin = getBrowserPlugin();
  if (isNativeShell() && browserPlugin?.open) {
    await browserPlugin.open({ url });
    return;
  }

  window.open(url, '_blank', 'noopener,noreferrer');
}

export function openPhoneNumber(phoneNumber) {
  if (!phoneNumber) {
    return;
  }

  window.location.href = `tel:${phoneNumber}`;
}

export function openEmailAddress(email) {
  if (!email) {
    return;
  }

  window.location.href = `mailto:${email}`;
}

export function getAppReturnUrl(path = '/') {
  const normalizedPath = normalizePath(path);

  if (isNativeShell()) {
    const configuredBase =
      import.meta.env.VITE_MOBILE_APP_URL || import.meta.env.VITE_PUBLIC_APP_URL;

    if (configuredBase) {
      const base = trimTrailingSlash(configuredBase);
      if (base.includes('#')) {
        return `${base}${normalizedPath}`;
      }
      return `${base}/#${normalizedPath}`;
    }

    return `${window.location.origin}/#${normalizedPath}`;
  }

  return `${window.location.origin}${normalizedPath}`;
}

export async function openCheckoutUrl(url) {
  await openExternalUrl(url);
}
