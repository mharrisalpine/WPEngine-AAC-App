import { getAppRuntimeConfig, getMemberApiBase, getPortalPageUrl, setRuntimeRestNonce } from '@/lib/backendConfig';

const AUTH_TOKEN_STORAGE_KEY = 'aac_wp_auth_token';

const buildUrl = (path) => {
  const normalizedPath = path.startsWith('/') ? path : `/${path}`;
  return `${getMemberApiBase()}${normalizedPath}`;
};

export const getAuthToken = () => localStorage.getItem(AUTH_TOKEN_STORAGE_KEY);

export const setAuthToken = (token) => {
  if (token) {
    localStorage.setItem(AUTH_TOKEN_STORAGE_KEY, token);
  } else {
    localStorage.removeItem(AUTH_TOKEN_STORAGE_KEY);
  }
};

export const setRestNonce = (restNonce) => {
  setRuntimeRestNonce(restNonce);
};

let nonceRefreshPromise = null;

const extractRestNonceFromHtml = (html) => {
  const match = String(html || '').match(/"restNonce":"([^"]+)"/);
  return match?.[1] || '';
};

const headersToObject = (headers) => {
  const output = {};
  if (!headers) {
    return output;
  }

  if (typeof headers.forEach === 'function') {
    headers.forEach((value, key) => {
      output[key] = value;
    });
    return output;
  }

  return { ...headers };
};

const createTextResponse = ({ status, statusText, responseText, responseHeaders }) => ({
  ok: status >= 200 && status < 300,
  status,
  statusText,
  headers: {
    get(name) {
      const lowerName = String(name || '').toLowerCase();
      return responseHeaders[lowerName] || '';
    },
  },
  json: async () => JSON.parse(responseText || 'null'),
  text: async () => responseText || '',
});

const xhrRequest = (url, options = {}) => new Promise((resolve, reject) => {
  if (typeof XMLHttpRequest === 'undefined') {
    reject(new Error('This browser does not support the request transport needed to load the member portal.'));
    return;
  }

  const request = new XMLHttpRequest();
  const method = options.method || 'GET';

  request.open(method, url, true);
  request.withCredentials = options.credentials !== 'omit';

  Object.entries(headersToObject(options.headers)).forEach(([key, value]) => {
    if (value !== undefined && value !== null) {
      request.setRequestHeader(key, value);
    }
  });

  request.onreadystatechange = () => {
    if (request.readyState !== 4) {
      return;
    }

    const responseHeaders = {};
    String(request.getAllResponseHeaders() || '')
      .trim()
      .split(/[\r\n]+/)
      .filter(Boolean)
      .forEach((line) => {
        const separatorIndex = line.indexOf(':');
        if (separatorIndex > -1) {
          responseHeaders[line.slice(0, separatorIndex).trim().toLowerCase()] = line.slice(separatorIndex + 1).trim();
        }
      });

    resolve(createTextResponse({
      status: request.status,
      statusText: request.statusText,
      responseText: request.responseText,
      responseHeaders,
    }));
  };

  request.onerror = () => reject(new Error('Network request failed.'));
  request.ontimeout = () => {
    const error = new Error('Request timed out.');
    error.name = 'AbortError';
    reject(error);
  };

  if (options.signal) {
    if (options.signal.aborted) {
      const error = new Error('Request aborted.');
      error.name = 'AbortError';
      reject(error);
      return;
    }
    options.signal.addEventListener('abort', () => {
      request.abort();
      const error = new Error('Request aborted.');
      error.name = 'AbortError';
      reject(error);
    }, { once: true });
  }

  request.send(options.body);
});

const requestTransport = (url, options = {}) => {
  if (typeof fetch === 'function') {
    return fetch(url, options);
  }

  return xhrRequest(url, options);
};

const withTimeout = async (requestPromise, timeoutMs) => {
  if (!timeoutMs || timeoutMs <= 0 || typeof window === 'undefined') {
    return requestPromise;
  }

  let timeoutId;
  const timeoutPromise = new Promise((_, reject) => {
    timeoutId = window.setTimeout(() => {
      const error = new Error('Request timed out.');
      error.name = 'AbortError';
      reject(error);
    }, timeoutMs);
  });

  try {
    return await Promise.race([requestPromise, timeoutPromise]);
  } finally {
    window.clearTimeout(timeoutId);
  }
};

const refreshRestNonce = async () => {
  if (nonceRefreshPromise) {
    return nonceRefreshPromise;
  }

  nonceRefreshPromise = (async () => {
    const portalPageUrl = getPortalPageUrl();
    if (!portalPageUrl) {
      throw new Error('Unable to refresh authentication. Portal URL is not configured.');
    }

    const refreshUrl = new URL(portalPageUrl, window.location.origin);
    refreshUrl.searchParams.set('aac_nonce_refresh', Date.now().toString());

    const response = await requestTransport(refreshUrl.toString(), {
      credentials: 'include',
      cache: 'no-store',
    });

    const html = await response.text();
    const restNonce = extractRestNonceFromHtml(html);
    if (!restNonce) {
      throw new Error('Unable to refresh authentication. Please reload the page.');
    }

    setRestNonce(restNonce);
    return restNonce;
  })();

  try {
    return await nonceRefreshPromise;
  } finally {
    nonceRefreshPromise = null;
  }
};

export async function apiRequest(path, options = {}) {
  const {
    retryOnNonceFailure = true,
    skipAuth = false,
    skipNonce = false,
    timeoutMs = 15000,
    ...fetchOptions
  } = options;
  const runtimeConfig = getAppRuntimeConfig();
  const token = runtimeConfig.isLoggedIn ? null : getAuthToken();
  const headers = new Headers(fetchOptions.headers || {});
  const hasBody = fetchOptions.body !== undefined;
  const isFormData = typeof FormData !== 'undefined' && fetchOptions.body instanceof FormData;

  if (!skipAuth && token && !headers.has('Authorization')) {
    headers.set('Authorization', `Bearer ${token}`);
  }

  if (!skipNonce && runtimeConfig.restNonce && !headers.has('X-WP-Nonce')) {
    headers.set('X-WP-Nonce', runtimeConfig.restNonce);
  }

  if (hasBody && !isFormData && !headers.has('Content-Type')) {
    headers.set('Content-Type', 'application/json');
  }

  const timeoutController = typeof AbortController !== 'undefined' && timeoutMs > 0
    ? new AbortController()
    : null;
  const timeoutId = timeoutController
    ? window.setTimeout(() => timeoutController.abort(), timeoutMs)
    : null;

  let response;
  try {
    const requestPromise = requestTransport(buildUrl(path), {
      credentials: 'include',
      ...fetchOptions,
      headers,
      signal: fetchOptions.signal || timeoutController?.signal,
    });
    response = await withTimeout(requestPromise, timeoutMs);
  } catch (error) {
    if (error?.name === 'AbortError' && !fetchOptions.signal) {
      throw new Error('The request took too long to finish. Refresh your member profile before trying again, because the account may already have been created.');
    }
    throw error;
  } finally {
    if (timeoutId) {
      window.clearTimeout(timeoutId);
    }
  }

  const contentType = response.headers.get('content-type') || '';
  const isJson = contentType.includes('application/json');
  const payload = isJson ? await response.json() : await response.text();

  if (!response.ok) {
    if (
      retryOnNonceFailure &&
      response.status === 403 &&
      isJson &&
      payload?.code === 'rest_cookie_invalid_nonce'
    ) {
      await refreshRestNonce();
      return apiRequest(path, {
        ...fetchOptions,
        retryOnNonceFailure: false,
        skipAuth,
        skipNonce,
      });
    }

    const message =
      (isJson && (payload.message || payload.error)) ||
      response.statusText ||
      'Request failed';
    const error = new Error(message);
    error.status = response.status;
    error.payload = payload;
    throw error;
  }

  return payload;
}
