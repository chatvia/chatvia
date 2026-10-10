import { readEndpointOverrides } from './endpoints.js';

window.chatvia =
  window.chatvia ||
  function () {
    (window.chatvia.q = window.chatvia.q || []).push(arguments);
  };

(function () {
  const namespace = 'chatvia';

  // Prevent multiple initializations (check within the IIFE)
  if (window[namespace].initialized) {
    console.warn('chatvia already initialized.');
    return;
  }

  window[namespace].initialized = true;

  // Safari-compatible script tag detection
  // document.currentScript doesn't work in Safari with async scripts
  let embedScriptTag = document.currentScript;

  // Fallback for Safari and other browsers where currentScript is null
  if (!embedScriptTag) {
    // Try to find by ID first (recommended approach)
    embedScriptTag = document.getElementById('chatbot-embed-script');

    // If not found by ID, search for script with data-agent-id attribute
    if (!embedScriptTag) {
      const scripts = document.querySelectorAll('script[data-agent-id]');
      embedScriptTag = scripts[scripts.length - 1]; // Get the last one (most recently added)
    }
  }

  if (!embedScriptTag) {
    console.error(
      'chatvia: Could not find embed script tag. Make sure the script has id="chatbot-embed-script" or data-agent-id attribute.'
    );
    window[namespace].error = 'Could not find embed script tag.';
    return;
  }

  // Read attributes with data- prefix (HTML5 compliant custom data attributes)
  const agentId = embedScriptTag.getAttribute('data-agent-id');
  const mountId = embedScriptTag.getAttribute('data-mount-id');
  const language = embedScriptTag.getAttribute('data-language') || 'auto';

  // Render mode: 'bubble' (default chat bubble) or 'bar' (floating launcher bar).
  // data-mode is canonical. data-type is a deprecated alias kept for older
  // embeds, with this legacy mapping:
  //   data-type="widget" → bubble
  //   data-type="search" → bar   (renamed; "search" was misleading — the bar
  //                               is a generic ask/launcher, not a search box)
  const VALID_MODES = ['bubble', 'bar'];
  const rawMode = (embedScriptTag.getAttribute('data-mode') || '').toLowerCase().trim();
  const rawLegacyType = (embedScriptTag.getAttribute('data-type') || '').toLowerCase().trim();
  let mode = 'bubble';
  if (rawMode) {
    if (VALID_MODES.includes(rawMode)) {
      mode = rawMode;
    } else {
      console.warn(
        `chatvia: Invalid data-mode "${rawMode}". Expected ${VALID_MODES.join(' or ')}.`
      );
    }
  } else if (rawLegacyType) {
    console.warn('chatvia: data-type is deprecated; use data-mode="bubble" or "bar" instead.');
    if (rawLegacyType === 'search') {
      mode = 'bar';
    } else if (rawLegacyType === 'widget') {
      mode = 'bubble';
    } else {
      console.warn(`chatvia: Invalid data-type "${rawLegacyType}". Expected widget or search.`);
    }
  }

  // Signed bootstrap token for protected agents (is_public = false). The
  // embedding site signs a short-lived JWT per visitor; the widget exchanges it
  // once for a session token. Absent for public agents.
  const auth = embedScriptTag.getAttribute('data-auth');

  // Theme preference: 'auto' (follow OS), 'light', or 'dark'.
  // Absent/invalid leaves it unset so the widget falls back to its backend setting.
  const rawTheme = (embedScriptTag.getAttribute('data-theme') || '').toLowerCase().trim();
  let theme = null;
  if (rawTheme) {
    if (['auto', 'light', 'dark'].includes(rawTheme)) {
      theme = rawTheme;
    } else {
      console.warn(`chatvia: Invalid data-theme "${rawTheme}". Expected auto, light, or dark.`);
    }
  }

  window[namespace].agentId = agentId;
  window[namespace].mode = mode;
  window[namespace].language = language;
  window[namespace].theme = theme;
  if (mountId) window[namespace].mountId = mountId;
  if (auth) window[namespace].auth = auth;

  // Server endpoints: the optional data-api-url / data-reverb-* attributes,
  // and the API on the server this script was loaded from. The widget bundle
  // decides between them and its build values (see endpoints.js).
  window[namespace].endpoints = readEndpointOverrides(embedScriptTag);

  /* global __SCRIPT_URL__ */
  // Script host: the build value when set, otherwise the folder this embed
  // script was loaded from (a self-hosted copy serves the bundle next to it).
  const buildScriptHost = typeof __SCRIPT_URL__ !== 'undefined' ? __SCRIPT_URL__ : '';
  const scriptHost = buildScriptHost || detectScriptHostFromSrc();

  // Function to detect script host from embed script's src attribute (fallback)
  function detectScriptHostFromSrc() {
    if (!embedScriptTag.src) return null;
    try {
      const scriptUrl = new URL(embedScriptTag.src);
      return `${scriptUrl.protocol}//${scriptUrl.host}${scriptUrl.pathname.replace(/\/embed\.js$/, '')}`;
    } catch (error) {
      console.warn('chatvia: Could not parse script src URL:', error);
      return null;
    }
  }

  if (!agentId) {
    console.error('chatvia: Missing data-agent-id attribute on embed script tag.');
    window[namespace].error = 'Missing data-agent-id attribute.';
    return;
  }

  console.log(`chatvia: Initializing for agent ${agentId} from ${scriptHost}`);

  // Add init command to the queue
  window[namespace].q = window[namespace].q || [];
  window[namespace].q.push(['init']);

  async function loadBundle(primaryHost = scriptHost) {
    try {
      const versionUrl = `${primaryHost}/version.json?t=${Date.now()}`;
      console.log('chatvia: Fetching version from:', versionUrl);

      // Fetch current version from version.json with cache busting
      const versionResponse = await fetch(versionUrl);
      console.log(
        'chatvia: Version response status:',
        versionResponse.status,
        versionResponse.statusText
      );

      if (!versionResponse.ok) {
        throw new Error(
          `Failed to fetch version.json: ${versionResponse.status} ${versionResponse.statusText}`
        );
      }

      const versionData = await versionResponse.json();
      console.log('chatvia: Version data received:', versionData);

      const version = versionData.version;
      console.log('chatvia: Using version:', version);

      // One bundle serves every mode; the widget reads window.chatvia.mode to
      // decide between the chat bubble and the floating bar.
      const bundleFile = 'widget.min.js';
      const scriptId = 'chatvia-embed-script-' + version.replace(/\./g, '-');
      const fullScriptUrl = `${primaryHost}/${version}/${bundleFile}`;
      console.log(`chatvia: Loading ${mode} from:`, fullScriptUrl);

      if (document.getElementById(scriptId)) {
        console.log('chatvia: Bundle script tag already exists for version:', version);
        return;
      }

      const script = document.createElement('script');

      script.id = scriptId;
      script.type = 'text/javascript';
      script.defer = true;
      script.src = fullScriptUrl;
      script.onerror = () => {
        console.error(`chatvia: Failed to load bundle from ${fullScriptUrl}`);
        window[namespace].error = `Failed to load bundle from ${fullScriptUrl}`;
      };
      script.onload = () =>
        console.log(`chatvia: Bundle loaded successfully from ${fullScriptUrl}`);

      console.log('chatvia: Inserting script tag with ID:', scriptId);
      const firstScript = document.getElementsByTagName('script')[0];

      if (firstScript && firstScript.parentNode) {
        firstScript.parentNode.insertBefore(script, firstScript);
      } else {
        (document.head || document.body).appendChild(script);
      }
    } catch (error) {
      console.error('chatvia: Error loading version or bundle:', error);
      console.error('chatvia: Script host was:', primaryHost);

      // Try fallback host if primary failed and we haven't tried fallback yet
      if (primaryHost === scriptHost) {
        const fallbackHost = detectScriptHostFromSrc();
        if (fallbackHost && fallbackHost !== scriptHost) {
          console.warn('chatvia: Trying fallback host:', fallbackHost);
          return loadBundle(fallbackHost);
        }
      }

      // If we get here, both primary and fallback failed
      window[namespace].error = `Error loading bundle: ${error.message}`;
    }
  }
  if (document.readyState === 'loading') {
    // Wrap the call: passing loadBundle directly would hand it the Event
    // object as `primaryHost` and fetch "[object Event]/version.json".
    document.addEventListener('DOMContentLoaded', () => loadBundle(), { once: true });
    console.log('chatvia: DOMContentLoaded listener added.');
  } else {
    console.log('chatvia: DOM already loaded or script deferred, loading bundle immediately.');
    loadBundle();
  }
})();
