/**
 * Server endpoints the widget talks to: the widget API base URL and the Reverb
 * (WebSocket) server used for live-agent handoff.
 *
 * The build bakes in default values from the widget `.env` (__API_URL__ and
 * __REVERB_*__). A self-hosted server can point the same bundle at itself with
 * data attributes on the embed script tag:
 *
 *   data-api-url, data-reverb-host, data-reverb-port, data-reverb-key,
 *   data-reverb-scheme
 *
 * embed.js reads them with readEndpointOverrides() and stores the result on
 * `window.chatvia.endpoints`; main.js hands that to configureEndpoints() before
 * the widget starts (main.js later replaces `window.chatvia` with the public
 * API, so the values must be captured here first). Every value that is not
 * given falls back to the build value.
 */

/* global __API_URL__, __REVERB_APP_KEY__, __REVERB_HOST__, __REVERB_PORT__, __REVERB_SCHEME__ */

// `typeof` guards keep this module usable un-bundled (tests, the Vite demo)
// where the build constants are not defined.
const BUILD = {
  apiUrl: typeof __API_URL__ !== 'undefined' ? __API_URL__ : '',
  reverbKey: typeof __REVERB_APP_KEY__ !== 'undefined' ? __REVERB_APP_KEY__ : '',
  reverbHost: typeof __REVERB_HOST__ !== 'undefined' ? __REVERB_HOST__ : '',
  reverbPort: typeof __REVERB_PORT__ !== 'undefined' ? __REVERB_PORT__ : '',
  reverbScheme: typeof __REVERB_SCHEME__ !== 'undefined' ? __REVERB_SCHEME__ : ''
};

const VALID_SCHEMES = ['http', 'https'];

let runtime = {};

/**
 * Normalize an absolute http(s) base URL and drop any trailing slash, so
 * callers can always build `${base}/${endpoint}`. Returns null when invalid.
 */
function normalizeBaseUrl(value) {
  if (typeof value !== 'string' || value.trim() === '') return null;
  try {
    const url = new URL(value.trim());
    if (url.protocol !== 'https:' && url.protocol !== 'http:') return null;
    return url.href.replace(/\/+$/, '');
  } catch {
    return null;
  }
}

/** A bare host name (no scheme, path or port), e.g. `ws.example.com`. */
function normalizeHost(value) {
  if (typeof value !== 'string') return null;
  const host = value.trim();
  return /^[a-z0-9.-]+$/i.test(host) ? host : null;
}

function normalizePort(value) {
  const port = Number.parseInt(String(value ?? '').trim(), 10);
  return Number.isInteger(port) && port > 0 && port <= 65535 ? port : null;
}

function normalizeScheme(value) {
  if (typeof value !== 'string') return null;
  const scheme = value.trim().toLowerCase();
  return VALID_SCHEMES.includes(scheme) ? scheme : null;
}

function normalizeKey(value) {
  if (typeof value !== 'string') return null;
  const key = value.trim();
  return key === '' ? null : key;
}

/**
 * Validate a raw overrides object and keep only the valid values.
 * @param {object} [raw] - `{ apiUrl, reverb: { host, port, key, scheme } }`
 * @returns {{apiUrl?: string, reverb: {host?: string, port?: number, key?: string, scheme?: string}}}
 */
function sanitizeOverrides(raw) {
  const result = { reverb: {} };
  if (!raw || typeof raw !== 'object') return result;

  const apiUrl = normalizeBaseUrl(raw.apiUrl);
  if (apiUrl) result.apiUrl = apiUrl;

  const reverb = raw.reverb && typeof raw.reverb === 'object' ? raw.reverb : {};
  const host = normalizeHost(reverb.host);
  const port = normalizePort(reverb.port);
  const key = normalizeKey(reverb.key);
  const scheme = normalizeScheme(reverb.scheme);
  if (host) result.reverb.host = host;
  if (port) result.reverb.port = port;
  if (key) result.reverb.key = key;
  if (scheme) result.reverb.scheme = scheme;

  return result;
}

/**
 * Read the endpoint data attributes from the embed script tag. Invalid values
 * are dropped with a console warning so a typo falls back to the build value
 * instead of breaking the widget.
 * @param {HTMLScriptElement|null} scriptTag
 */
export function readEndpointOverrides(scriptTag) {
  if (!scriptTag || typeof scriptTag.getAttribute !== 'function') return { reverb: {} };

  const raw = {
    apiUrl: scriptTag.getAttribute('data-api-url'),
    reverb: {
      host: scriptTag.getAttribute('data-reverb-host'),
      port: scriptTag.getAttribute('data-reverb-port'),
      key: scriptTag.getAttribute('data-reverb-key'),
      scheme: scriptTag.getAttribute('data-reverb-scheme')
    }
  };
  const result = sanitizeOverrides(raw);

  const given = {
    'data-api-url': [raw.apiUrl, result.apiUrl],
    'data-reverb-host': [raw.reverb.host, result.reverb.host],
    'data-reverb-port': [raw.reverb.port, result.reverb.port],
    'data-reverb-key': [raw.reverb.key, result.reverb.key],
    'data-reverb-scheme': [raw.reverb.scheme, result.reverb.scheme]
  };
  for (const [name, [rawValue, value]] of Object.entries(given)) {
    if (rawValue !== null && rawValue !== '' && !value) {
      console.warn(`chatvia: Ignoring invalid ${name}="${rawValue}"; using the built-in value.`);
    }
  }

  return result;
}

/**
 * Store the runtime overrides for this page. Called once by main.js before
 * the widget starts; calling it again replaces the previous values.
 * @param {object} [overrides] - the shape returned by readEndpointOverrides()
 */
export function configureEndpoints(overrides) {
  runtime = sanitizeOverrides(overrides);
}

/** Widget API base URL, without a trailing slash (e.g. `https://example.com/widget`). */
export function getApiUrl() {
  return runtime.apiUrl || normalizeBaseUrl(BUILD.apiUrl) || '';
}

/** Origin of the widget API (e.g. `https://example.com`), or null when unknown. */
export function getApiOrigin() {
  try {
    return new URL(getApiUrl()).origin;
  } catch {
    return null;
  }
}

/**
 * Reverb connection settings for live-agent handoff. `key` or `host` is null
 * when neither the embed tag nor the build provides one — live chat is then
 * unavailable.
 * @returns {{key: string|null, host: string|null, port: number, scheme: string, forceTLS: boolean}}
 */
export function getReverbConfig() {
  const reverb = runtime.reverb || {};
  const scheme = reverb.scheme || normalizeScheme(BUILD.reverbScheme) || 'https';
  const port = reverb.port || normalizePort(BUILD.reverbPort) || (scheme === 'https' ? 443 : 80);

  return {
    key: reverb.key || normalizeKey(BUILD.reverbKey),
    host: reverb.host || normalizeHost(BUILD.reverbHost),
    port,
    scheme,
    forceTLS: scheme === 'https'
  };
}
