/**
 * The global namespace the embed script attaches everything to:
 * `window.chatvia`. Imported by both the embed loader and the widget
 * so the string lives in exactly one place.
 */
export const NAMESPACE = 'chatvia';

/**
 * Log prefix used in console messages so embedding sites can grep for it.
 */
const LOG_PREFIX = `${NAMESPACE}:`;

/* global __DEV__ */
// __DEV__ is replaced by rollup; default to false if running un-bundled.
const DEBUG_BUILD = typeof __DEV__ !== 'undefined' ? __DEV__ : false;

/**
 * Debug logger. No-op in production builds; in dev, prefixed `chatvia:` log.
 * Use this for anything informational ("starting", "conversation loaded", state dumps).
 * Use console.warn / console.error directly for things customers should see.
 */
export function debug(...args) {
  if (!DEBUG_BUILD) return;
  console.log(LOG_PREFIX, ...args);
}

/**
 * Standard warn / error helpers — always log, always prefixed. Use these
 * instead of bare `console.warn`/`console.error` so the prefix stays consistent.
 */
export function warn(...args) {
  console.warn(LOG_PREFIX, ...args);
}

export function error(...args) {
  console.error(LOG_PREFIX, ...args);
}

export const CSS_CLASSES = {
  WIDGET: 'chatvia-widget',
  BUBBLE: 'chatvia-widget-bubble',
  BUBBLE_PULSE: 'chatvia-widget-pulse',
  BUBBLE_ACTIVE: 'chatvia-widget-active',
  BUBBLE_CLOSE_ICON: 'chatvia-widget-bubble-close-icon',
  BUBBLE_NEW_MESSAGE: 'chatvia-widget-new-message',
  WINDOW: 'chatvia-widget-window',
  WINDOW_OPEN: 'chatvia-widget-open',
  WINDOW_DARK: 'chatvia-widget-dark',
  WINDOW_ANIMATING: 'chatvia-widget-animating',
  HEADER: 'chatvia-widget-header',
  HEADER_LOGO: 'chatvia-widget-header-logo',
  HEADER_TITLE: 'chatvia-widget-header-title',
  HEADER_TITLEWRAP: 'chatvia-widget-header-titlewrap',
  HEADER_SUBTITLE: 'chatvia-widget-header-subtitle',
  MENU_BUTTON: 'chatvia-widget-menu-btn',
  DELETE_BUTTON: 'chatvia-widget-delete-btn',
  EXPAND_BUTTON: 'chatvia-widget-expand-btn',
  THEME_TOGGLE: 'chatvia-widget-theme-toggle',
  MENU_DROPDOWN: 'chatvia-widget-menu-dropdown',
  MENU_DROPDOWN_OPEN: 'chatvia-widget-dropdown-open',
  MENU_OPTION: 'chatvia-widget-menu-option',
  CLOSE_BUTTON: 'chatvia-widget-close-btn',
  MESSAGES_CONTAINER: 'chatvia-widget-messages',
  MESSAGE: 'chatvia-widget-message',
  MESSAGE_USER: 'chatvia-widget-user',
  MESSAGE_AGENT: 'chatvia-widget-assistant',
  MESSAGE_SYSTEM: 'chatvia-widget-system',
  MESSAGE_AVATAR: 'chatvia-widget-avatar',
  MESSAGE_BUBBLE: 'chatvia-widget-bubble-content',
  MESSAGE_ACTIONS: 'chatvia-widget-actions',
  MESSAGE_ACTION_BTN: 'chatvia-widget-action-btn',
  MESSAGE_COPY_BTN: 'chatvia-widget-copy-btn',
  MESSAGE_THUMBS_UP: 'chatvia-widget-thumbs-up',
  MESSAGE_THUMBS_DOWN: 'chatvia-widget-thumbs-down',
  MESSAGE_ACTION_ACTIVE: 'chatvia-widget-active',
  LOADING: 'chatvia-widget-loading',
  LOADING_DOTS: 'chatvia-widget-loading-dots',
  LOADING_DOT: 'chatvia-widget-dot',
  FETCH_SPINNER: 'chatvia-widget-fetch-spinner',
  FETCH_SPINNER_CIRCLE: 'chatvia-widget-fetch-spinner-circle',
  // Floating round button that appears when the user has scrolled up away from
  // the latest message — click to smooth-scroll back to the bottom. `_VISIBLE`
  // toggles the show/hide transition.
  SCROLL_TO_BOTTOM: 'chatvia-widget-scroll-bottom',
  SCROLL_TO_BOTTOM_VISIBLE: 'chatvia-widget-scroll-bottom-visible',
  // Empty trailing block that reserves scroll room below a just-sent user
  // message so it can be pinned to the top of the viewport (ChatGPT-style).
  // Shrinks as the assistant reply fills the space beneath it.
  SCROLL_SPACER: 'chatvia-widget-scroll-spacer',
  STARTERS_CONTAINER: 'chatvia-widget-starters',
  STARTER_BUTTON: 'chatvia-widget-starter-btn',
  INPUT_CONTAINER: 'chatvia-widget-input-container',
  INPUT_ROW: 'chatvia-widget-input-row',
  INPUT: 'chatvia-widget-input',
  SEND_BUTTON: 'chatvia-widget-send-btn',
  ATTACH_BUTTON: 'chatvia-widget-attach-btn',
  ATTACHMENTS_STRIP: 'chatvia-widget-attachments-strip',
  ATTACHMENT_CHIP: 'chatvia-widget-attachment-chip',
  ATTACHMENT_CHIP_ICON: 'chatvia-widget-attachment-chip-icon',
  ATTACHMENT_CHIP_META: 'chatvia-widget-attachment-chip-meta',
  ATTACHMENT_CHIP_NAME: 'chatvia-widget-attachment-chip-name',
  ATTACHMENT_CHIP_SIZE: 'chatvia-widget-attachment-chip-size',
  ATTACHMENT_CHIP_REMOVE: 'chatvia-widget-attachment-chip-remove',
  ATTACHMENT_CHIP_UPLOADING: 'chatvia-widget-attachment-chip-uploading',
  BRAND_FOOTER: 'chatvia-widget-footer',
  // Disclaimer strip below the composer (e.g. "AI can make mistakes…").
  // Distinct from BRAND_FOOTER — that's the "Powered by Chatvia" line.
  FOOTER_TEXT: 'chatvia-widget-footer-text',
  // Consent strip above the composer: "By chatting, you agree to our privacy
  // policy." Only shown when the agent has a privacy_policy_url configured.
  PRIVACY_NOTICE: 'chatvia-widget-privacy-notice',
  PRIVACY_NOTICE_TEXT: 'chatvia-widget-privacy-text',
  PRIVACY_NOTICE_LINK: 'chatvia-widget-privacy-link',
  PRIVACY_NOTICE_DISMISS: 'chatvia-widget-privacy-dismiss',
  CONFIRM_DIALOG: 'chatvia-widget-confirm-dialog',
  CONFIRM_CONTENT: 'chatvia-widget-confirm-content',
  CONFIRM_BUTTONS: 'chatvia-widget-confirm-buttons',
  CONFIRM_CANCEL: 'chatvia-widget-confirm-cancel',
  CONFIRM_DELETE: 'chatvia-widget-confirm-delete',
  CONVERSATIONS_CONTAINER: 'chatvia-widget-conversations',
  CONVERSATIONS_LIST: 'chatvia-widget-conversations-list',
  CONVERSATIONS_HEADER: 'chatvia-widget-conversations-header',
  NEW_CHAT_BUTTON: 'chatvia-widget-new-chat-btn',
  NO_CONVERSATIONS_MESSAGE: 'chatvia-widget-no-conversations',
  CONVERSATION_ITEM: 'chatvia-widget-conversation-item',
  CONVERSATION_ITEM_ACTIVE: 'chatvia-widget-conversation-active',
  CONVERSATION_TITLE: 'chatvia-widget-conversation-title',
  CONVERSATION_DATE: 'chatvia-widget-conversation-date',
  STREAMING_MESSAGE: 'chatvia-widget-streaming-msg',
  STREAMING_MESSAGE_CLASS:
    'chatvia-widget-message chatvia-widget-assistant chatvia-widget-streaming',
  MESSAGE_BUBBLES_CONTAINER: 'chatvia-widget-msg-bubbles',
  FLOATING_BUBBLE: 'chatvia-widget-floating-bubble',
  FLOATING_BUBBLE_TEXT: 'chatvia-widget-floating-bubble-text',
  FLOATING_BUBBLE_CLOSE: 'chatvia-widget-floating-bubble-close',
  FLOATING_BUBBLE_SHOW: 'chatvia-widget-bubble-show',
  FULLSCREEN: 'chatvia-widget-fullscreen',
  WINDOW_SMALL: 'chatvia-widget-small',
  WINDOW_MEDIUM: 'chatvia-widget-medium',
  WINDOW_LARGE: 'chatvia-widget-large',
  STREAMING: 'chatvia-widget-streaming',
  TOOL_ACTIVITY: 'chatvia-widget-tool-activity',
  TOOL_CALLING: 'chatvia-widget-tool-calling',
  // Bar mode classes (the floating-bar variant of the launcher)
  BAR_CONTAINER: 'chatvia-widget-bar-container',
  BAR_CONTAINER_EXPANDED: 'chatvia-widget-expanded',
  BAR_CONTAINER_HIDDEN: 'chatvia-widget-hidden',
  // Bar placement classes. `top` and `bottom` center the bar horizontally;
  // `-right` variants anchor to the viewport's right edge instead.
  BAR_POSITION_TOP: 'chatvia-widget-bar-top',
  BAR_POSITION_BOTTOM: 'chatvia-widget-bar-bottom',
  BAR_POSITION_TOP_RIGHT: 'chatvia-widget-bar-top-right',
  BAR_POSITION_BOTTOM_RIGHT: 'chatvia-widget-bar-bottom-right',
  BAR_INPUT_WRAPPER: 'chatvia-widget-bar-input-wrapper',
  BAR_INPUT: 'chatvia-widget-bar-input',
  BAR_SHORTCUT_HINT: 'chatvia-widget-shortcut-hint',
  BAR_BUTTON: 'chatvia-widget-bar-btn',
  BAR_HISTORY_BUTTON: 'chatvia-widget-bar-history-btn',
  // Conversation content classes
  CONVERSATION_CONTENT: 'chatvia-widget-conversation-content',
  CONVERSATION_SUBTITLE: 'chatvia-widget-conversation-subtitle',
  CONVERSATION_ARROW_ICON: 'chatvia-widget-conversation-arrow',
  // Brand
  BRAND_LOGO: 'chatvia-widget-brand-logo',
  // Position and mode classes
  FLAT_MODE: 'chatvia-widget-flat-mode',
  WINDOW_FLAT: 'chatvia-widget-flat',
  POSITION_BOTTOM_RIGHT: 'chatvia-widget-bottom-right',
  POSITION_BOTTOM_LEFT: 'chatvia-widget-bottom-left',
  // Handoff mode
  HUMAN_MODE: 'chatvia-widget-human-mode',
  MESSAGE_HUMAN_AGENT: 'chatvia-widget-human-agent',
  MESSAGE_TIME: 'chatvia-widget-message-time'
};
export const STREAMING_DONE_MARKER = '[DONE]';
export const STREAMING_FINALIZE_TIMEOUT = 5000; // ms

export const DEFAULT_LOGO_SVG =
  'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAyNCAyNCIgZmlsbD0id2hpdGUiPjxwYXRoIGQ9Ik0yMCA0SDRjLTEuMSAwLTIgLjktMiAydjEwYzAgMS4xLjkgMiAyIDJoMTJsNCA0VjZjMC0xLjEtLjktMi0yLTJ6Ii8+PC9zdmc+';

// Allowlist for operator-configured image URLs (logo_url, avatar_url, bubble
// logo, etc.). We accept:
//   - https:// — first-class case, no mixed content / passive beaconing.
//   - data:image/{png,jpeg,gif,webp,svg+xml};base64 — inline assets including
//     small SVG logos. SVG via <img src> can't execute scripts or load remote
//     refs (browsers block both for image-loaded SVG), so this is contained.
// We reject http:// (passive content beacons + mixed-content warnings on the
// embedding HTTPS page), javascript:, file:, blob:, anything else.
//
// Callers should pair this with referrerpolicy="no-referrer" on the <img>
// element so the embedding host's URL doesn't leak to the image's origin.
const SAFE_IMAGE_URL_RE = /^(https:\/\/|data:image\/(png|jpe?g|gif|webp|svg\+xml);base64,)/i;

/**
 * Return `url` when it passes the image URL allowlist, otherwise null.
 * Callers should fall back to a hardcoded default (e.g. DEFAULT_LOGO_SVG)
 * on null so a misconfigured / hostile setting doesn't break the render.
 *
 * @param {unknown} url
 * @returns {string|null}
 */
export function safeImageUrl(url) {
  if (typeof url !== 'string' || url === '') return null;
  return SAFE_IMAGE_URL_RE.test(url) ? url : null;
}

/**
 * Human-readable file size. Single source of truth for attachment chips —
 * previously duplicated as `_formatBytes` (widget.js) and `_formatBytesShort`
 * (state/messages.js).
 * @param {number} bytes
 * @returns {string} e.g. "512 B", "3 KB", "1.4 MB", or "" for falsy input
 */
export function formatBytes(bytes) {
  if (!bytes) return '';
  if (bytes < 1024) return bytes + ' B';
  if (bytes < 1024 * 1024) return Math.round(bytes / 1024) + ' KB';
  return (bytes / 1024 / 1024).toFixed(1) + ' MB';
}
