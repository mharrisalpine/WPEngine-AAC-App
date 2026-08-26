const parseInlineRuntimeConfig = () => {
  if (typeof document === 'undefined') {
    return {};
  }

  const marker = 'window.AAC_MEMBER_PORTAL_CONFIG';
  const scripts = Array.from(document.scripts || []);
  const configScript = scripts.find((script) =>
    String(script.textContent || '').includes(marker)
  );

  if (!configScript) {
    return {};
  }

  const source = String(configScript.textContent || '');
  const markerIndex = source.indexOf(marker);
  const objectStart = source.indexOf('{', markerIndex);
  const objectEnd = source.lastIndexOf('};');

  if (markerIndex === -1 || objectStart === -1 || objectEnd === -1 || objectEnd < objectStart) {
    return {};
  }

  try {
    const parsedConfig = JSON.parse(source.slice(objectStart, objectEnd + 1));
    window.AAC_MEMBER_PORTAL_CONFIG = parsedConfig;
    return parsedConfig;
  } catch (error) {
    console.warn('Unable to parse AAC member portal runtime config.', error);
    return {};
  }
};

const getRuntimeConfig = () => {
  if (typeof window === 'undefined') {
    return {};
  }

  if (window.AAC_MEMBER_PORTAL_CONFIG) {
    return window.AAC_MEMBER_PORTAL_CONFIG;
  }

  return parseInlineRuntimeConfig();
};

const trimTrailingSlash = (value) => String(value || '').replace(/\/$/, '');

export const getAppRuntimeConfig = () => getRuntimeConfig();

export const setRuntimeRestNonce = (restNonce) => {
  if (typeof window === 'undefined') {
    return;
  }

  const nextConfig = {
    ...getRuntimeConfig(),
  };

  if (restNonce) {
    nextConfig.restNonce = restNonce;
  } else {
    delete nextConfig.restNonce;
  }

  window.AAC_MEMBER_PORTAL_CONFIG = nextConfig;
};

export const getMemberApiBase = () => {
  const runtimeBase = getRuntimeConfig().apiBase;
  if (runtimeBase) {
    return trimTrailingSlash(runtimeBase);
  }

  const configuredBase =
    import.meta.env.VITE_MEMBER_API_BASE ||
    import.meta.env.VITE_WORDPRESS_API_BASE;

  if (configuredBase) {
    return trimTrailingSlash(configuredBase);
  }

  return '/wp-json/aac/v1';
};

export const getPortalPageUrl = () => {
  const runtimePortalUrl = getRuntimeConfig().portalPageUrl;
  if (runtimePortalUrl) {
    return trimTrailingSlash(runtimePortalUrl);
  }

  if (typeof window === 'undefined') {
    return '';
  }

  return trimTrailingSlash(`${window.location.origin}${window.location.pathname}`);
};

export const getMainWebsiteBaseUrl = () => {
  const runtimeBaseUrl = getRuntimeConfig().mainWebsiteBaseUrl;
  if (runtimeBaseUrl) {
    return trimTrailingSlash(runtimeBaseUrl);
  }

  if (typeof window === 'undefined') {
    return '';
  }

  return trimTrailingSlash(window.location.origin);
};

export const getWordPressLostPasswordUrl = () => `${getMainWebsiteBaseUrl()}/wp-login.php?action=lostpassword`;

export const getPmproSocialLoginHtml = () => {
  const runtimeMarkup = getRuntimeConfig().pmproSocialLoginHtml;
  return typeof runtimeMarkup === 'string' ? runtimeMarkup : '';
};

export const getCommerceProvider = () =>
  getRuntimeConfig().commerceProvider ||
  import.meta.env.VITE_COMMERCE_PROVIDER ||
  'embedded';

export const isStandaloneBackend = () =>
  getRuntimeConfig().backendMode === 'standalone' ||
  import.meta.env.VITE_BACKEND_MODE === 'standalone';
