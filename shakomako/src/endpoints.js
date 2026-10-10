/**
 * Server endpoints the widget talks to: the widget API base URL and the Reverb
 * (WebSocket) server used for live-agent handoff.
 *
 * Each value is taken from the first place that has one:
 *
 *   1. The embed script tag: data-api-url, data-reverb-host, data-reverb-port,
 *      data-reverb-key, data-reverb-scheme.
 *   2. The build: __API_URL__ and __REVERB_*__, from the widget `.env`.
 *   3. The server: the API URL is `/widget` on the server embed.js was loaded
 *      from (a Chatvia server serves the built widget itself), and the Reverb
 *      settings arrive with the agent settings (`settings.reverb`).
 *   4. For Reverb only: the API's own host, port and scheme (a server can
 *      forward /app to Reverb, as the Docker image does).
 *
 * So a build with no `.env` at all works wherever a Chatvia server serves it.
 *
 * embed.js reads the tag with readEndpointOverrides() and stores the result on
 * `window.chatvia.endpoints`; main.js hands that to configureEndpoints() before
 * the widget starts (main.js later replaces `window.chatvia` with the public
 * API, so the values must be captured here first). The widget passes the
 * server's Reverb settings to configureServerReverb() once they have loaded.
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

/** Path of the widget API on a Chatvia server. */
const API_PATH = 'widget';

// Embed tag overrides, plus the API URL worked out from embed.js's address.
let runtime = {};

// Reverb settings the widget API sent with the agent settings.
let server = {};

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
 * @param {object} [raw] - `{ apiUrl, scriptApiUrl, reverb: { host, port, key, scheme } }`
 * @returns {{apiUrl?: string, scriptApiUrl?: string, reverb: {host?: string, port?: number, key?: string, scheme?: string}}}
 */
function sanitizeOverrides(raw) {
  const result = { reverb: {} };
  if (!raw || typeof raw !== 'object') return result;

  const apiUrl = normalizeBaseUrl(raw.apiUrl);
  if (apiUrl) result.apiUrl = apiUrl;

  const scriptApiUrl = normalizeBaseUrl(raw.scriptApiUrl);
  if (scriptApiUrl) result.scriptApiUrl = scriptApiUrl;

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
 * The widget API on the server that served embed.js (`{origin}/widget`), or
 * null when the script address is missing or not http(s).
 * @param {string|null|undefined} src - the embed script's absolute URL
 */
export function apiUrlFromScriptSrc(src) {
  if (typeof src !== 'string' || src === '') return null;
  try {
    const url = new URL(src);
    if (url.protocol !== 'https:' && url.protocol !== 'http:') return null;
    return `${url.origin}/${API_PATH}`;
  } catch {
    return null;
  }
}

/**
 * Read the endpoint data attributes from the embed script tag, and the API URL
 * its own address points to. Invalid attributes are dropped with a console
 * warning so a typo falls back to the next source instead of breaking the
 * widget.
 * @param {HTMLScriptElement|null} scriptTag
 */
export function readEndpointOverrides(scriptTag) {
  if (!scriptTag || typeof scriptTag.getAttribute !== 'function') return { reverb: {} };

  const raw = {
    apiUrl: scriptTag.getAttribute('data-api-url'),
    scriptApiUrl: apiUrlFromScriptSrc(scriptTag.src),
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

/**
 * Store the Reverb settings the widget API sent with the agent settings. They
 * apply only where neither the embed tag nor the build gives a value.
 * @param {object} [reverb] - `{ key, host, port, scheme }`; null fields are skipped
 */
export function configureServerReverb(reverb) {
  server = sanitizeOverrides({ reverb }).reverb;
}

/** Widget API base URL, without a trailing slash (e.g. `https://example.com/widget`). */
export function getApiUrl() {
  return runtime.apiUrl || normalizeBaseUrl(BUILD.apiUrl) || runtime.scriptApiUrl || '';
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
 * Reverb connection settings for live-agent handoff. Each field comes from the
 * embed tag, else the build, else the server. With no host from any of them,
 * the API's own host, port and scheme are used. `key` is null when no source
 * gives one, and `host` is null when the API URL is unknown too — live chat is
 * then unavailable.
 * @returns {{key: string|null, host: string|null, port: number, scheme: string, forceTLS: boolean}}
 */
export function getReverbConfig() {
  const tag = runtime.reverb || {};
  const build = sanitizeOverrides({
    reverb: {
      key: BUILD.reverbKey,
      host: BUILD.reverbHost,
      port: BUILD.reverbPort,
      scheme: BUILD.reverbScheme
    }
  }).reverb;
  const pick = field => tag[field] ?? build[field] ?? server[field] ?? null;

  let host = pick('host');
  let scheme = pick('scheme');
  let port = pick('port');

  if (!host) {
    const api = apiLocation();
    if (api) {
      host = normalizeHost(api.hostname);
      scheme = scheme || normalizeScheme(api.protocol.replace(':', ''));
      port = port || normalizePort(api.port);
    }
  }

  scheme = scheme || 'https';

  return {
    key: pick('key'),
    host,
    port: port || (scheme === 'https' ? 443 : 80),
    scheme,
    forceTLS: scheme === 'https'
  };
}

/** The parsed widget API URL, or null when it is unknown. */
function apiLocation() {
  try {
    return new URL(getApiUrl());
  } catch {
    return null;
  }
}
