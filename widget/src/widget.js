/**
 * ChatWidget - Main Widget Class
 * Orchestrates all widget functionality using state modules
 */

import '../css/widget.css';

import StorageManager from './storage.js';
import { AgentManager } from './agent.js';
import { AuthManager } from './auth.js';
import { getSettings } from './settings.js';
import { SessionManager } from './sessions.js';
import { CSS_CLASSES, DEFAULT_LOGO_SVG, debug, safeImageUrl, formatBytes } from './utils.js';
import { createElement, createIcon } from './ui.js';
import { initI18n, t, getDirection, pickLocalized } from './i18n.js';
import { triggerLabel } from './keyboardTrigger.js';

// Import state modules
import * as conversations from './state/conversations.js';
import * as messages from './state/messages.js';
import * as sse from './state/sse.js';
import * as events from './state/events.js';
import * as controls from './state/controls.js';
import * as config from './state/config.js';
import * as handoff from './state/handoff.js';
import { startHeartbeat } from './state/handoff.js';

/** Render modes. `bubble` = chat-bubble launcher, `bar` = floating bar launcher. */
export const VALID_MODES = ['bubble', 'bar'];
const DEFAULT_MODE = 'bubble';

/**
 * Bar-mode placement values. Mirrors the chatvia validator at
 * config/model-settings.php:189. `top` and `bottom` center the bar horizontally;
 * the `-right` variants anchor to the viewport's right edge. Default is `top`,
 * which matches the chatvia default in the same config file.
 */
export const VALID_BAR_PLACEMENTS = ['top', 'top-right', 'bottom', 'bottom-right'];
const DEFAULT_BAR_PLACEMENT = 'top';

export class ChatWidget {
  /**
   * @param {string} agentId — public agent id from `data-agent-id`.
   * @param {object|string} [options] — widget options. Passing a string is a
   *   legacy form that treats the value as a `mountId`.
   * @param {'bubble'|'bar'} [options.mode]
   * @param {'top'|'bottom'} [options.position] — `bar` mode only: which edge
   *   of the viewport the bar floats against.
   * @param {string} [options.language]
   * @param {'auto'|'light'|'dark'} [options.theme]
   * @param {string} [options.auth] — bootstrap JWT for protected agents.
   * @param {string} [options.mountId] — id of a host element for flat mode.
   */
  constructor(agentId, options = {}) {
    this.agentId = agentId;

    // Handle null/undefined options, or legacy mountId parameter (string)
    if (!options) {
      this.mountId = null;
      this.options = {};
    } else if (typeof options === 'string') {
      this.mountId = options;
      this.options = {};
    } else {
      this.mountId = options.mountId || null;
      this.options = options;
    }

    // Internal event emitter wired in by main.js (closure-private listener
    // registry). Defaults to a no-op so direct constructions of ChatWidget
    // (tests, alternate entry points) don't crash when something tries to
    // emit. The widget never reads listeners directly — it can only fire.
    this._emit = typeof this.options.emit === 'function' ? this.options.emit : () => {};

    // Mode: 'bubble' (default chat bubble) or 'bar' (floating launcher bar).
    // embed.js validates the input attribute; this is a safety net for direct
    // callers that bypass the loader (e.g. the index.html / bar.html demos).
    this.mode = ChatWidget._validateMode(this.options.mode);
    // Same default as the dashboard's Command Bar setting (bar.placement).
    this.barPosition = this.options.position || DEFAULT_BAR_PLACEMENT;
    this.language = this.options.language || 'auto';

    // Theme preference: 'auto' | 'light' | 'dark' | null.
    // A visitor's saved toggle choice wins over the embed's data-theme.
    // Resolved against backend settings in config.applyTheme().
    this.themePreference = config.readStoredTheme(this.agentId) || this.options.theme || null;

    this.sessionId = null;
    this.isFlat = !!this.mountId;

    this.settings = {};
    this.isMobileApp = this._detectMobile();

    this.agentManager = new AgentManager(agentId);
    this.storageManager = null;
    this.sessionManager = null;

    // Auth: protected agents (is_public = false) ship a signed bootstrap token
    // via the embed's data-auth. We exchange it once for a session token and
    // lock the chat if it's missing or rejected.
    this.bootstrapToken = this.options.auth || null;
    this.authManager = new AuthManager(agentId, this.bootstrapToken);
    this.isLocked = false;
    // i18n key for the notice shown when the chat is locked (verification
    // rejected, or protected-agent authorization failed). Null when unlocked.
    this.disabledReasonKey = null;

    // State
    this.conversations = [];
    this.activeConversationId = null;
    this.messageHistory = [];
    this.isOpen = false;
    this.sseClient = null;
    this.sseReconnectAttempts = 0;
    this.currentStreamedMessage = '';
    this.currentStreamedMessageId = null;
    this.isStreaming = false;
    this.isWidthExpanded = false;
    this.startersVisible = true;

    // Internal timers/state. All setTimeout ids stored on `this` so destroy()
    // can clear them; never schedule a bare setTimeout from instance methods.
    this._previousWindowSize = null;
    this._finalizeTimeout = null;
    this._loadingSafetyTimeout = null;
    this._wsMessageLoadingTimeout = null;
    this._nextBubbleTimeout = null;
    this._showOnLoadTimeout = null;
    this._initialResizeTimeout = null;

    // Initialize handoff state
    handoff.initHandoff(this);
  }

  /**
   * Active conversation id — a single source of truth backed by the
   * StorageManager (localStorage), which the backend treats as authoritative
   * (the conversations endpoint returns no active id; see storage.js). Before
   * the StorageManager exists (construction), fall back to a private field.
   *
   * Previously this was a plain instance field tracked in parallel with
   * `storageManager`'s persisted value AND handoff's `conversationId`, so the
   * three could silently drift. Routing every read/write of
   * `widget.activeConversationId` through the store keeps the in-memory view
   * and the persisted value identical by construction — existing call sites
   * (`widget.activeConversationId = id`) keep working unchanged.
   */
  get activeConversationId() {
    return this.storageManager
      ? this.storageManager.getActiveConversationId()
      : (this._activeConversationId ?? null);
  }

  set activeConversationId(value) {
    if (this.storageManager) {
      this.storageManager.setActiveConversationId(value);
    } else {
      this._activeConversationId = value;
    }
  }

  _detectMobile() {
    const mobileRegex = /Mobile|Android.*Mobile|iPhone|iPod|BlackBerry|IEMobile|Opera Mini/i;
    if (mobileRegex.test(navigator.userAgent)) {
      const tabletRegex = /iPad|Android(?!.*Mobile)|Tablet|Tab/i;
      return !tabletRegex.test(navigator.userAgent);
    }
    return window.innerWidth <= 768;
  }

  /**
   * Clear every setTimeout id stored on the instance under the listed keys,
   * then null the field. Safe to call with a missing field.
   */
  _clearTrackedTimers(keys) {
    for (const key of keys) {
      if (this[key]) {
        clearTimeout(this[key]);
        this[key] = null;
      }
    }
  }

  /**
   * Same as `_clearTrackedTimers` for cancelAnimationFrame ids.
   */
  _cancelTrackedRafs(keys) {
    for (const key of keys) {
      if (this[key]) {
        cancelAnimationFrame(this[key]);
        this[key] = null;
      }
    }
  }

  /**
   * Validate the requested mode and fall back to `bubble` on anything unexpected.
   * Warns once on invalid input so misconfigured embeds fail loud, not silent.
   */
  static _validateMode(rawMode) {
    if (!rawMode) return DEFAULT_MODE;
    if (VALID_MODES.includes(rawMode)) return rawMode;
    console.warn(
      `chatvia: Invalid mode "${rawMode}". Expected ${VALID_MODES.join(' or ')}. Falling back to "${DEFAULT_MODE}".`
    );
    return DEFAULT_MODE;
  }

  /**
   * Validate a bar placement string. Aligned with the chatvia backend
   * validator at config/model-settings.php:189 — keep the two lists in lockstep.
   */
  static _validateBarPlacement(raw) {
    if (VALID_BAR_PLACEMENTS.includes(raw)) return raw;
    if (raw) {
      console.warn(
        `chatvia: Invalid bar placement "${raw}". Expected ${VALID_BAR_PLACEMENTS.join(' | ')}. Falling back to "${DEFAULT_BAR_PLACEMENT}".`
      );
    }
    return DEFAULT_BAR_PLACEMENT;
  }

  /**
   * Map a placement value to its CSS class. The class drives the CSS rules at
   * the bottom of widget.css that position the bar in the right viewport corner.
   */
  static _barPlacementClass(placement) {
    switch (placement) {
      case 'top':
        return CSS_CLASSES.BAR_POSITION_TOP;
      case 'top-right':
        return CSS_CLASSES.BAR_POSITION_TOP_RIGHT;
      case 'bottom':
        return CSS_CLASSES.BAR_POSITION_BOTTOM;
      case 'bottom-right':
        return CSS_CLASSES.BAR_POSITION_BOTTOM_RIGHT;
      default:
        return CSS_CLASSES.BAR_POSITION_TOP;
    }
  }

  // ========================================
  // Initialization
  // ========================================

  async init() {
    return await this._initialize();
  }

  async _initialize() {
    try {
      // The widget is prepared entirely in the background (i18n, client
      // verification, session, settings, conversations) and only mounts once
      // everything is ready — see _createWidgetElements below. We deliberately
      // show no boot indicator in the corner during this window so visitors
      // never see a spinner for a widget that may not even meet its display
      // requirements.

      // Initialize i18n system first
      await initI18n({ language: this.language });

      // Verify the agent. A genuine rejection ('unverified') no longer aborts
      // init — we mount a locked shell below so the visitor sees a branded,
      // disabled window with a verification notice instead of a blank corner.
      // 'error' (server unreachable) is distinct from a rejection: we don't
      // lock on it; the getSettings call right below will surface a real
      // connectivity failure the same way it always has.
      const verification = await this.agentManager.verifyClient();

      this.settings = await getSettings(this.agentId);
      // The API sends the semantic window size ('small' / 'medium' / 'large');
      // the window-size logic works in CSS-class terms, so normalize once here.
      this.settings.window_size = config.normalizeWindowSize(this.settings.window_size);
      debug('Initial settings', this.settings);

      // Per-surface kill switch (dashboard → Deploy → enable/disable). Each mode
      // reads its own flag: bubble → widget_enabled, bar → bar_enabled. When the
      // owner turns this surface off we mount nothing at all, so it simply never
      // appears on the host page. Defaults to enabled when the flag is absent so
      // an older backend keeps working unchanged.
      const surfaceEnabled =
        this.mode === 'bar' ? this.settings.bar_enabled : this.settings.widget_enabled;
      if (surfaceEnabled === false) {
        debug(`chatvia: the ${this.mode} surface is disabled for this agent — not mounting.`);
        return;
      }

      if (this.isMobileApp) {
        this.settings.show_on_load = false;
        this.settings.is_fullscreen = true;
      }

      // Decide the lock state. A server rejection takes precedence over the
      // protected-agent auth check. Either way we still mount the shell but lock
      // it, so the visitor sees the branded window with a notice rather than a
      // broken/blank corner.
      if (verification === 'unverified') {
        this.isLocked = true;
        this.disabledReasonKey = 'errors.verification_failed';
      } else if (this.settings.protected === true && !(await this._authorizeProtectedWidget())) {
        this.isLocked = true;
        this.disabledReasonKey = 'errors.auth_required';
      }

      this.sessionManager = new SessionManager(this.agentId);
      if (!this.isLocked) {
        await this._initializeSession();
      }

      this.storageManager = new StorageManager(this.agentId, this.sessionId);
      if (!this.isLocked) {
        await this.initConversations();
      }

      await this._createWidgetElements();

      await this.applyConfiguration();

      this._setupEventListeners();

      // Locked: skip session-bound wiring (SSE, conversations, heartbeat) and leave
      // the chat in its disabled sign-in state.
      if (this.isLocked) {
        await this.disabledChat(t(this.disabledReasonKey || 'errors.auth_required'));
        console.warn(
          `chatvia: widget locked — ${this.disabledReasonKey || 'errors.auth_required'}.`
        );
        return;
      }

      this.setupSSE();
      await this.loadActiveConversation();

      // Start heartbeat to track online status
      startHeartbeat(this);

      if (this.settings.show_on_load) {
        this._showOnLoadTimeout = setTimeout(() => {
          this._showOnLoadTimeout = null;
          this.toggleChat(true);
        }, this.settings.show_on_load_delay);
      }

      this._initializeMessageBubbles();

      // Initial textarea resize. Tracked so destroy() can clear it if the
      // widget is torn down during the 300ms boot window.
      this._initialResizeTimeout = setTimeout(() => {
        this._initialResizeTimeout = null;
        this.autoResizeInput();
      }, 300);
    } catch (error) {
      console.error('Error during async initialization:', error);
      await this.disabledChat(t('errors.initialization_failed'));
      throw error;
    }
  }

  /**
   * Exchange the embed's signed bootstrap token for a widget session token.
   * @returns {Promise<boolean>} true when a session token was obtained
   */
  async _authorizeProtectedWidget() {
    if (!this.authManager.hasBootstrapToken()) {
      console.warn('chatvia: agent is protected but the embed is missing a data-auth token.');
      return false;
    }
    return await this.authManager.exchange();
  }

  async _initializeSession() {
    debug('Initializing session and conversation...');
    const initData = await this.sessionManager.startSession();

    if (!initData || !initData.sessionId) {
      console.error('chatvia: Failed to initialize session. Disabling chat.');
      await this.disabledChat(t('errors.session_failed'));
      throw new Error('Session initialization failed');
    }

    this.sessionId = initData.sessionId;
    this.initialConversationId = initData.conversationId;

    debug('Session initialized', {
      sessionId: initData.sessionId,
      conversationId: initData.conversationId,
      isNewSession: initData.isNewSession,
      isNewConversation: initData.isNewConversation
    });
  }

  // ========================================
  // Conversation Management (delegated)
  // ========================================

  async initConversations() {
    return conversations.initConversations(this);
  }

  async createNewConversation() {
    return conversations.createNewConversation(this);
  }

  getActiveConversation() {
    return conversations.getActiveConversation(this);
  }

  getCurrentActiveConversationId() {
    return conversations.getCurrentActiveConversationId(this);
  }

  async loadActiveConversation() {
    return conversations.loadActiveConversation(this);
  }

  async startNewConversation() {
    return conversations.startNewConversation(this);
  }

  async loadChatHistory() {
    return conversations.loadChatHistory(this);
  }

  confirmDeleteConversation() {
    return conversations.confirmDeleteConversation(this);
  }

  confirmClearAllConversations() {
    return conversations.confirmClearAllConversations(this);
  }

  _updateConversationActionButtons() {
    return conversations.updateConversationActionButtons(this);
  }

  // ========================================
  // Message Handling (delegated)
  // ========================================

  async addMessage(type, text, addToHistory = true, options = {}) {
    return messages.addMessage(this, type, text, addToHistory, options);
  }

  async sendUserMessage(customText) {
    return messages.sendUserMessage(this, customText);
  }

  async displayHistoryMessages() {
    return messages.displayHistoryMessages(this);
  }

  async parseMarkdown(text) {
    return messages.parseMarkdown(text);
  }

  startStreamingMessage() {
    return messages.startStreamingMessage(this);
  }

  async updateStreamingMessage() {
    return messages.updateStreamingMessage(this);
  }

  async finalizeStreamingMessage() {
    return messages.finalizeStreamingMessage(this);
  }

  async refreshStreamingMessages() {
    if (this.isStreaming) {
      await this.updateStreamingMessage();
    }
  }

  // ========================================
  // SSE Handling (delegated)
  // ========================================

  setupSSE() {
    return sse.setupSSE(this);
  }

  removeConnectionErrorMessages() {
    return sse.removeConnectionErrorMessages(this);
  }

  // ========================================
  // Event Listeners (delegated)
  // ========================================

  _setupEventListeners() {
    return events.setupEventListeners(this);
  }

  // ========================================
  // UI Controls (delegated)
  // ========================================

  toggleChat(open) {
    return controls.toggleChat(this, open);
  }

  toggleExpand() {
    return controls.toggleExpand(this);
  }

  toggleTheme() {
    return controls.toggleTheme(this);
  }

  toggleMenuDropdown() {
    return controls.toggleMenuDropdown(this);
  }

  menuDropdownIsOpened() {
    return controls.menuDropdownIsOpened(this);
  }

  closeMenuDropdown() {
    return controls.closeMenuDropdown(this);
  }

  scrollToBottom(smooth = false) {
    return controls.scrollToBottom(this, smooth);
  }

  pinMessageToTop(anchorEl) {
    return controls.pinMessageToTop(this, anchorEl);
  }

  followStream() {
    return controls.followStream(this);
  }

  holdPin() {
    return controls.holdPin(this);
  }

  clearMessages() {
    return controls.clearMessages(this);
  }

  showLoading() {
    return controls.showLoading(this);
  }

  hideLoading() {
    return controls.hideLoading(this);
  }

  showFetchSpinner() {
    return controls.showFetchSpinner(this);
  }

  hideFetchSpinner() {
    return controls.hideFetchSpinner(this);
  }

  autoResizeInput() {
    return controls.autoResizeInput(this);
  }

  hideStarters() {
    return controls.hideStarters(this);
  }

  showStarters() {
    return controls.showStarters(this);
  }

  async disabledChat(message) {
    return controls.disabledChat(this, message);
  }

  // ========================================
  // Configuration (delegated)
  // ========================================

  async applyConfiguration() {
    return config.applyConfiguration(this);
  }

  applyWindowSize() {
    return config.applyWindowSize(this);
  }

  _updateStartersContainer() {
    return config.updateStartersContainer(this);
  }

  // ========================================
  // UI Element Creation
  // ========================================

  async _createWidgetElements() {
    return new Promise(resolve => {
      this._loadExternalLibrariesIfNeeded();

      const isFlat = this.isFlat;
      const isBarMode = this.mode === 'bar';

      this.container = createElement('div', { className: CSS_CLASSES.WIDGET });

      if (isBarMode) {
        this.container.classList.add('chatvia-widget-bar-mode');
      }

      if (!isFlat) {
        if (isBarMode) {
          this.bar = this._createBar();
          this.container.appendChild(this.bar);
        } else {
          this.chatBubble = this._createChatBubble();
          this.container.appendChild(this.chatBubble);

          this.messageBubblesContainer = this._createMessageBubblesContainer();
          this.container.appendChild(this.messageBubblesContainer);
        }
      }

      this.chatWindow = this._createChatWindow();

      if (isBarMode) {
        // The chat window itself opens with a slider animation above the bar;
        // the mode it lives in is `bar`, but the window-open style is `slider`.
        this.chatWindow.classList.add('chatvia-widget-slider-mode');
      }

      this.container.appendChild(this.chatWindow);

      if (isFlat) {
        this.chatWindow.classList.add(CSS_CLASSES.WINDOW_OPEN, CSS_CLASSES.WINDOW_FLAT);
        this.isOpen = true;
        if (this.expandButton) this.expandButton.style.display = 'none';
        if (this.closeButton) this.closeButton.style.display = 'none';
      } else if (this.isMobileApp && !isBarMode) {
        this.chatWindow.style.display = 'none';
        this.isOpen = false;
        if (this.expandButton) this.expandButton.style.display = 'none';
      }

      requestAnimationFrame(() => {
        const mountEl = this.mountId ? document.getElementById(this.mountId) : null;
        (mountEl || document.body).appendChild(this.container);
        resolve();
      });
    });
  }

  _createChatBubble() {
    const bubble = createElement('div', {
      className: `${CSS_CLASSES.BUBBLE} ${CSS_CLASSES.BUBBLE_PULSE}`,
      role: 'button',
      'aria-label': t('widget.open_chat'),
      tabIndex: 0
    });
    const logoUrl = safeImageUrl(this.settings.logo_url) || DEFAULT_LOGO_SVG;
    this.bubbleLogo = createElement('img', {
      alt: 'Chat',
      src: logoUrl,
      width: '30',
      height: '30',
      // referrerpolicy avoids leaking the embedding host's URL to the logo origin.
      referrerpolicy: 'no-referrer',
      style: 'width: 30px; height: 30px; opacity: 1;'
    });
    bubble.appendChild(this.bubbleLogo);

    // Shown only while the chat is open: the bubble flips to a dark circle with
    // a down-chevron (Chatbase-style). The logo/icon swap is driven purely by
    // the `.chatvia-widget-active` class toggled in toggleChat().
    this.bubbleCloseIcon = createElement('span', {
      className: CSS_CLASSES.BUBBLE_CLOSE_ICON,
      'aria-hidden': 'true'
    });
    this.bubbleCloseIcon.appendChild(createIcon('chevron-down'));
    bubble.appendChild(this.bubbleCloseIcon);

    return bubble;
  }

  /**
   * Build the floating bar launcher for `bar` mode. Returns the container
   * element; instance refs (`barInput`, `barButton`) are stashed so the
   * controls/events modules can wire interactions to them later.
   */
  _createBar() {
    // Placement resolution chain:
    //   1. programmatic override from the embedder (e.g. bar.html demo)
    //   2. dashboard-configured bar.placement (served via settings API)
    //   3. constructor default ('top', matching the dashboard default)
    // Constrained to the 4 values the chatvia validator accepts; anything else
    // falls back to 'top' (matching the chatvia default).
    const resolved =
      this.options.position ||
      this.settings?.bar_placement ||
      this.barPosition ||
      DEFAULT_BAR_PLACEMENT;
    this.barPosition = ChatWidget._validateBarPlacement(resolved);
    const positionClass = ChatWidget._barPlacementClass(this.barPosition);
    const barContainer = createElement('div', {
      className: `${CSS_CLASSES.BAR_CONTAINER} ${positionClass}`
    });

    const inputWrapper = createElement('div', {
      className: CSS_CLASSES.BAR_INPUT_WRAPPER
    });

    // `composer_placeholder` is the input placeholder; `welcome_message` is a
    // separate full-sentence greeting rendered as the first assistant message
    // (see state/messages.js). Don't conflate them — surfacing a full greeting
    // inside the tiny launcher input was the old bug.
    const placeholder =
      this.settings.composer_placeholder || this.options.placeholder || t('widget.ask_question');

    this.barInput = createElement('input', {
      className: CSS_CLASSES.BAR_INPUT,
      type: 'text',
      placeholder: placeholder
    });

    // Label reflects the trigger the dashboard configured (cmd+k / cmd+j / etc).
    // triggerLabel() picks the platform glyph (⌘ on mac, Ctrl+ elsewhere) and
    // falls back to the default when the setting is missing.
    const shortcutHint = createElement('span', {
      className: CSS_CLASSES.BAR_SHORTCUT_HINT,
      textContent: triggerLabel(this.settings.keyboard_trigger),
      title: t('widget.open_chat')
    });

    this.barHistoryButton = createElement('button', {
      className: CSS_CLASSES.BAR_HISTORY_BUTTON,
      'aria-label': t('widget.view_history'),
      title: t('widget.view_history'),
      type: 'button'
    });
    this.barHistoryButton.appendChild(createIcon('history'));

    this.barButton = createElement('button', {
      className: CSS_CLASSES.BAR_BUTTON,
      'aria-label': t('widget.send_message')
    });
    this.barButton.appendChild(createIcon('arrow-right'));

    inputWrapper.appendChild(this.barInput);
    inputWrapper.appendChild(shortcutHint);
    inputWrapper.appendChild(this.barHistoryButton);
    inputWrapper.appendChild(this.barButton);
    barContainer.appendChild(inputWrapper);

    return barContainer;
  }

  _createMessageBubblesContainer() {
    return createElement('div', {
      className: CSS_CLASSES.MESSAGE_BUBBLES_CONTAINER
    });
  }

  _initializeMessageBubbles() {
    if (
      this.isFlat ||
      this.isOpen ||
      !this.settings.bubbles ||
      !Array.isArray(this.settings.bubbles) ||
      this.settings.bubbles.length === 0
    ) {
      return;
    }

    this.currentBubbleIndex = 0;
    this._nextBubbleTimeout = setTimeout(() => this._showNextBubble(), 1000);
  }

  _showNextBubble() {
    // Never reveal a teaser bubble once the chat is open — a pending timer can
    // fire on the same tick the chat opens (e.g. show_on_load auto-open racing
    // the bubble schedule), after stopMessageBubbles() has already run.
    if (
      this.isOpen ||
      !this.settings.bubbles ||
      this.settings.bubbles.length === 0 ||
      !this.messageBubblesContainer
    ) {
      return;
    }

    if (this.currentBubbleIndex >= this.settings.bubbles.length) {
      return;
    }

    const rawBubble = this.settings.bubbles[this.currentBubbleIndex];
    // Localize the bubble to the widget's active language via its parallel
    // translation map; fall back to the resolved string (or a legacy object).
    const bubbleText =
      typeof rawBubble === 'string'
        ? pickLocalized(this.settings.bubble_translations?.[this.currentBubbleIndex], rawBubble)
        : rawBubble?.text;

    const bubble = createElement('div', {
      className: CSS_CLASSES.FLOATING_BUBBLE
    });

    bubble.appendChild(
      createElement('span', {
        className: CSS_CLASSES.FLOATING_BUBBLE_TEXT,
        textContent: bubbleText
      })
    );

    // Close (X) control to dismiss the teaser without opening the chat.
    const closeBtn = createElement('button', {
      type: 'button',
      className: CSS_CLASSES.FLOATING_BUBBLE_CLOSE,
      'aria-label': t('widget.dismiss')
    });
    closeBtn.appendChild(createIcon('close'));
    closeBtn.addEventListener('click', e => {
      // Don't let the click fall through to the bubble's open-chat handler.
      e.stopPropagation();
      this._dismissMessageBubbles();
    });
    bubble.appendChild(closeBtn);

    bubble.addEventListener('click', () => {
      // Open the chat widget when bubble is clicked
      this.toggleChat(true);

      // Fade out the clicked bubble
      bubble.classList.remove(CSS_CLASSES.FLOATING_BUBBLE_SHOW);
      setTimeout(() => {
        if (bubble.parentNode) {
          bubble.parentNode.removeChild(bubble);
        }
      }, 300);
    });

    this.messageBubblesContainer.appendChild(bubble);

    setTimeout(() => {
      bubble.classList.add(CSS_CLASSES.FLOATING_BUBBLE_SHOW);
    }, 100);

    this.currentBubbleIndex++;

    if (this.currentBubbleIndex < this.settings.bubbles.length) {
      this._nextBubbleTimeout = setTimeout(() => this._showNextBubble(), 2000);
    }
  }

  _dismissMessageBubbles() {
    // Stop any pending bubble from appearing after the user dismisses the teaser.
    clearTimeout(this._nextBubbleTimeout);
    this.currentBubbleIndex = this.settings.bubbles ? this.settings.bubbles.length : 0;

    if (!this.messageBubblesContainer) {
      return;
    }

    // Fade out every visible bubble, then remove them once the transition ends.
    const bubbles = this.messageBubblesContainer.querySelectorAll(
      `.${CSS_CLASSES.FLOATING_BUBBLE}`
    );
    bubbles.forEach(bubble => {
      bubble.classList.remove(CSS_CLASSES.FLOATING_BUBBLE_SHOW);
      setTimeout(() => {
        if (bubble.parentNode) {
          bubble.parentNode.removeChild(bubble);
        }
      }, 300);
    });
  }

  _createChatWindow() {
    const windowEl = createElement('div', {
      className: CSS_CLASSES.WINDOW,
      role: 'dialog',
      'aria-modal': 'true',
      'aria-labelledby': 'chatvia-widget-header-title'
    });

    this.chatHeader = this._createHeader();
    this.messagesContainer = this._createMessagesContainer();
    this.loadingIndicator = this._createLoadingIndicator();
    this.startersContainer = this._createStartersContainer();
    this.inputContainer = this._createInputContainer();
    // Disclaimer line ("AI can make mistakes…") — sits between the input and
    // the "Powered by" brand footer. Created up-front so applyConfiguration
    // can swap its text in without an extra DOM insertion.
    this.footerText = this._createFooterText();
    this.brandFooter = this._createBrandFooter();
    // Consent strip ("By chatting, you agree to our privacy policy") — sits
    // directly above the composer. Hidden until applyPrivacyNotice() fills it,
    // which only happens when the agent has a privacy_policy_url configured.
    this.privacyNotice = this._createPrivacyNotice();
    // Floating chevron-down button shown when the user has scrolled up away
    // from the latest message. Absolutely positioned over the chat window so
    // it floats above the input regardless of which message they're reading.
    this.scrollToBottomBtn = this._createScrollToBottomButton();

    windowEl.appendChild(this.chatHeader);
    windowEl.appendChild(this.messagesContainer);
    windowEl.appendChild(this.startersContainer);
    // Optional privacy-consent strip, then the composer. The optional disclaimer
    // strip and the "Powered by" brand footer sit below the composer, with the
    // brand line anchored at the very bottom of the window.
    windowEl.appendChild(this.privacyNotice);
    windowEl.appendChild(this.inputContainer);
    windowEl.appendChild(this.footerText);
    windowEl.appendChild(this.brandFooter);
    windowEl.appendChild(this.scrollToBottomBtn);

    return windowEl;
  }

  _createPrivacyNotice() {
    // Hidden by default; config.applyPrivacyNotice() fills the text span with
    // the consent sentence + privacy-policy link and reveals it when the agent
    // has configured `privacy_policy_url`. The dismiss button is created once
    // here (with a stable handler) so re-applying never rebinds it.
    const container = createElement('div', {
      className: CSS_CLASSES.PRIVACY_NOTICE,
      style: 'display:none;'
    });

    this.privacyNoticeText = createElement('span', {
      className: CSS_CLASSES.PRIVACY_NOTICE_TEXT
    });

    const dismiss = createElement(
      'button',
      {
        type: 'button',
        className: CSS_CLASSES.PRIVACY_NOTICE_DISMISS,
        'aria-label': t('widget.dismiss'),
        title: t('widget.dismiss')
      },
      {
        click: () => {
          // Remember the dismissal per agent so the strip doesn't nag on every
          // open; the visitor has already seen the consent line.
          config.writePrivacyDismissed(this.agentId);
          container.style.display = 'none';
        }
      }
    );
    dismiss.appendChild(createIcon('x'));

    container.appendChild(this.privacyNoticeText);
    container.appendChild(dismiss);

    return container;
  }

  _createScrollToBottomButton() {
    const btn = createElement(
      'div',
      {
        className: CSS_CLASSES.SCROLL_TO_BOTTOM,
        role: 'button',
        'aria-label': t('widget.scroll_to_bottom'),
        title: t('widget.scroll_to_bottom'),
        tabIndex: 0
      },
      {
        click: () => this.scrollToBottom(true),
        keydown: e => {
          if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            this.scrollToBottom(true);
          }
        }
      }
    );
    btn.appendChild(createIcon('chevron-down'));
    return btn;
  }

  _createFooterText() {
    // Hidden by default; applyFooterText() reveals it when the agent has
    // configured `chat_footer_text` in the dashboard.
    return createElement('div', {
      className: CSS_CLASSES.FOOTER_TEXT,
      style: 'display:none;'
    });
  }

  _createHeader() {
    const header = createElement('div', { className: CSS_CLASSES.HEADER });

    this.headerLogo = createElement('img', {
      className: CSS_CLASSES.HEADER_LOGO,
      alt: 'Chat logo'
    });
    this.headerTitle = createElement('div', {
      className: CSS_CLASSES.HEADER_TITLE,
      id: 'chatvia-widget-header-title'
    });
    // EU AI Act Art. 50 — persistent, always-visible disclosure that the visitor
    // is talking to an AI. updateHandoffUI() flips this to the human label when a
    // live agent takes over, and back to AI when the conversation returns to the bot.
    this.headerSubtitle = createElement('div', {
      className: CSS_CLASSES.HEADER_SUBTITLE
    });
    this.headerSubtitle.textContent = t('disclosure.ai_assistant');
    this.headerTitleWrap = createElement('div', {
      className: CSS_CLASSES.HEADER_TITLEWRAP
    });
    this.headerTitleWrap.appendChild(this.headerTitle);
    this.headerTitleWrap.appendChild(this.headerSubtitle);
    this.menuButton = this._createHeaderButton('menu', t('widget.menu'), CSS_CLASSES.MENU_BUTTON, {
      'aria-haspopup': 'true',
      'aria-expanded': 'false'
    });
    this.deleteConversationButton = this._createHeaderButton(
      'delete',
      t('widget.clear_current_chat'),
      CSS_CLASSES.DELETE_BUTTON
    );
    this.deleteConversationButton.style.display = 'none';
    this.themeToggleButton = this._createHeaderButton(
      'moon',
      t('menu.theme_toggle'),
      CSS_CLASSES.THEME_TOGGLE
    );
    this.expandButton = this._createHeaderButton(
      'expand',
      t('widget.expand_chat'),
      CSS_CLASSES.EXPAND_BUTTON
    );
    this.closeButton = this._createHeaderButton(
      'close',
      t('widget.close_chat'),
      CSS_CLASSES.CLOSE_BUTTON
    );
    this.menuDropdown = this._createMenuDropdown();

    header.appendChild(this.headerLogo);
    header.appendChild(this.headerTitleWrap);
    header.appendChild(this.deleteConversationButton);
    header.appendChild(this.themeToggleButton);
    header.appendChild(this.expandButton);
    header.appendChild(this.menuButton);
    header.appendChild(this.menuDropdown);
    header.appendChild(this.closeButton);

    return header;
  }

  _createHeaderButton(iconName, label, className, additionalAttributes = {}) {
    const button = createElement('div', {
      className,
      role: 'button',
      'aria-label': label,
      title: label,
      tabIndex: 0,
      ...additionalAttributes
    });
    button.appendChild(createIcon(iconName));
    return button;
  }

  _createMenuDropdown() {
    const dropdown = createElement('div', {
      className: CSS_CLASSES.MENU_DROPDOWN,
      role: 'menu'
    });

    // Wrap actions to check if in human mode
    const guardedAction = action => {
      return () => {
        if (this.isInHumanMode()) {
          // Don't allow navigation during live chat
          return;
        }
        action();
      };
    };

    this.newChatOption = this._createMenuOption(
      t('menu.new_chat'),
      'new-chat',
      guardedAction(() => this.startNewConversation())
    );
    this.historyOption = this._createMenuOption(
      t('menu.chat_history'),
      'history',
      guardedAction(() => this.loadChatHistory())
    );
    this.clearCurrentOption = this._createMenuOption(
      t('menu.clear_current'),
      'delete',
      guardedAction(() => this.confirmDeleteConversation())
    );
    this.clearAllOption = this._createMenuOption(
      t('menu.clear_all'),
      'clear-all',
      guardedAction(() => this.confirmClearAllConversations())
    );

    dropdown.appendChild(this.newChatOption);
    dropdown.appendChild(this.historyOption);
    dropdown.appendChild(
      createElement('hr', {
        style: 'border:none;border-top:1px solid var(--chat-border);margin:4px 0;'
      })
    );
    dropdown.appendChild(this.clearCurrentOption);
    dropdown.appendChild(this.clearAllOption);
    return dropdown;
  }

  // Update menu options based on handoff state
  _updateMenuForHandoffState() {
    const inHumanMode = this.isInHumanMode();
    const options = [
      this.newChatOption,
      this.historyOption,
      this.clearCurrentOption,
      this.clearAllOption
    ];

    options.forEach(option => {
      if (!option) return;

      if (inHumanMode) {
        option.classList.add('chatvia-widget-menu-disabled');
      } else {
        option.classList.remove('chatvia-widget-menu-disabled');
      }
    });
  }

  _createMenuOption(text, iconName, action) {
    const option = createElement(
      'div',
      {
        className: CSS_CLASSES.MENU_OPTION,
        role: 'menuitem',
        tabIndex: 0
      },
      {
        click: action,
        keydown: e => {
          if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            action();
          }
        }
      }
    );
    option.appendChild(createIcon(iconName));
    option.appendChild(createElement('span', { textContent: text }));
    return option;
  }

  _createMessagesContainer() {
    return createElement('div', {
      className: CSS_CLASSES.MESSAGES_CONTAINER,
      role: 'log',
      'aria-live': 'polite'
    });
  }

  _createLoadingIndicator() {
    const indicator = createElement('div', {
      className: CSS_CLASSES.LOADING,
      'aria-label': t('widget.loading')
    });
    const dotsContainer = createElement('div', { className: CSS_CLASSES.LOADING_DOTS });
    for (let i = 0; i < 3; i++) {
      dotsContainer.appendChild(createElement('div', { className: CSS_CLASSES.LOADING_DOT }));
    }
    indicator.appendChild(dotsContainer);
    indicator.style.display = 'none';
    return indicator;
  }

  /**
   * Centered fetch spinner shown over the chat window while we're loading
   * messages, conversations, or history from the server. Separate from the typing
   * dots — those signal "agent is responding"; this signals "fetching data".
   * Mounted on demand into `chatWindow`, which is the positioned ancestor
   * (fixed in launcher modes, relative in flat mode) so the spinner stays
   * centered regardless of message scroll position.
   */
  _createFetchSpinner() {
    const overlay = createElement('div', {
      className: CSS_CLASSES.FETCH_SPINNER,
      role: 'status',
      'aria-label': t('widget.loading')
    });
    overlay.appendChild(createElement('div', { className: CSS_CLASSES.FETCH_SPINNER_CIRCLE }));
    return overlay;
  }

  _createStartersContainer() {
    return createElement('div', {
      className: CSS_CLASSES.STARTERS_CONTAINER
    });
  }

  _createInputContainer() {
    const container = createElement('div', { className: CSS_CLASSES.INPUT_CONTAINER });

    // Attachment chips appear ABOVE the textarea — populated as files upload.
    this.attachments = [];
    this.attachmentsStrip = createElement('div', { className: CSS_CLASSES.ATTACHMENTS_STRIP });
    this.attachmentsStrip.style.display = 'none';
    container.appendChild(this.attachmentsStrip);

    // See _createBar above — placeholder ≠ welcome_message. The latter is a
    // greeting rendered as the first assistant message, not a placeholder hint.
    const placeholder =
      this.settings?.composer_placeholder || this.options?.placeholder || t('widget.type_message');

    this.chatInput = createElement('textarea', {
      className: CSS_CLASSES.INPUT,
      placeholder: placeholder,
      rows: 1,
      'aria-label': placeholder,
      autocomplete: 'off',
      autocorrect: 'on',
      spellcheck: 'true'
    });

    // The paperclip/attach button only exists when the agent owner allows
    // uploads (dashboard "Allow file uploads" → settings.allow_file_uploads).
    // Backend defaults this to true, so treat a missing flag as enabled and
    // only hide when it's explicitly false. When off we skip the button AND
    // the hidden file input entirely — there's no other entry point to the
    // picker, so uploads are fully disabled, not just visually hidden.
    const allowUploads = this.settings?.allow_file_uploads !== false;
    if (allowUploads) {
      // Hidden native file picker — triggered by the paperclip button.
      this.fileInput = createElement('input', {
        type: 'file',
        accept: 'application/pdf'
      });
      this.fileInput.style.display = 'none';
      this.fileInput.addEventListener('change', e => this._handleFileSelected(e));

      this.attachButton = createElement('div', {
        className: CSS_CLASSES.ATTACH_BUTTON,
        role: 'button',
        'aria-label': t('widget.attach_pdf'),
        title: t('widget.attach_pdf'),
        tabIndex: 0
      });
      this.attachButton.appendChild(createIcon('paperclip'));
      // Gate the picker on the per-chat upload ceiling. Settings carry
      // `max_files_per_chat` (config: agent.files.upload.max_files); _handleFileSelected
      // re-checks as a defence-in-depth, but stopping the picker from opening
      // gives the user the chip-style "limit reached" message instead of a
      // confusing native dialog that goes nowhere on submit.
      const tryOpenFilePicker = async () => {
        const max = this.settings?.max_files_per_chat ?? 2;
        if (this.attachments.length >= max) {
          const msg =
            t('widget.upload_limit_reached', { count: max }) ||
            `You can attach up to ${max} files.`;
          const el = await this.addMessage(CSS_CLASSES.MESSAGE_SYSTEM, msg, false);
          el?.classList.add('error');
          return;
        }
        this.fileInput.click();
      };
      this.attachButton.addEventListener('click', tryOpenFilePicker);
      this.attachButton.addEventListener('keydown', e => {
        if (e.key === 'Enter' || e.key === ' ') {
          e.preventDefault();
          tryOpenFilePicker();
        }
      });
    }

    this.sendButton = createElement('div', {
      className: CSS_CLASSES.SEND_BUTTON,
      role: 'button',
      'aria-label': t('widget.send_message'),
      tabIndex: 0
    });
    this.sendButton.appendChild(createIcon('send'));

    // Paperclip + textarea + send laid out as one flex row (align-items:
    // flex-end). Keeping them as flex siblings — rather than absolutely
    // positioning the buttons over the textarea — means they stay vertically
    // aligned no matter what height autoResizeInput() gives the textarea, so
    // the composer looks correct on first paint instead of only after typing.
    const inputRow = createElement('div', { className: CSS_CLASSES.INPUT_ROW });
    if (this.attachButton) inputRow.appendChild(this.attachButton);
    inputRow.appendChild(this.chatInput);
    if (this.fileInput) inputRow.appendChild(this.fileInput);
    inputRow.appendChild(this.sendButton);
    container.appendChild(inputRow);
    return container;
  }

  async _handleFileSelected(event) {
    const file = event.target.files && event.target.files[0];
    event.target.value = '';
    if (!file) return;

    // Re-check the per-chat upload ceiling — the click handler already
    // gates this, but a race (multi-select OS dialog, concurrent picker)
    // could slip past. Server enforces the same value via
    // MaxConversationAttachments so a forged request still 422s.
    const maxFiles = this.settings?.max_files_per_chat ?? 2;
    if (this.attachments.length >= maxFiles) {
      const msg =
        t('widget.upload_limit_reached', { count: maxFiles }) ||
        `You can attach up to ${maxFiles} files.`;
      const el = await this.addMessage(CSS_CLASSES.MESSAGE_SYSTEM, msg, false);
      el?.classList.add('error');
      return;
    }

    // Resolve the agentId from a fallback chain — defensive because production
    // reports have shown `this.agentId` going undefined on the upload path
    // while sibling props (sessionId) survive on the same instance. AgentManager
    // and AuthManager each capture agentId in their own constructors, so as
    // long as one is intact the upload still reaches the right tenant. If all
    // three are gone we surface a clear failure instead of POSTing the string
    // "undefined" and getting a confusing 403 back.
    const agentId =
      this.agentId ||
      this.agentManager?.agentId ||
      this.authManager?.agentId ||
      (typeof window !== 'undefined' && window.chatvia && window.chatvia.agentId) ||
      null;

    if (!agentId) {
      console.error('chatvia: cannot upload — no agent id resolvable on the widget instance', {
        'this.agentId': this.agentId,
        'agentManager.agentId': this.agentManager?.agentId,
        'authManager.agentId': this.authManager?.agentId,
        'window.chatvia.agentId': typeof window !== 'undefined' ? window.chatvia?.agentId : null
      });
      const el = await this.addMessage(
        CSS_CLASSES.MESSAGE_SYSTEM,
        t('widget.upload_failed'),
        false
      );
      el?.classList.add('error');
      return;
    }

    // Optimistic chip — flips from "uploading" to "ready" once the URL is back.
    const tempId = 'tmp-' + Date.now();
    const chip = this._addAttachmentChip({
      id: tempId,
      filename: file.name,
      size_bytes: file.size,
      mime_type: file.type,
      url: '',
      uploading: true
    });

    try {
      const { uploadFile } = await import('./api.js');
      const response = await uploadFile({
        endpoint: 'conversations/attachments',
        file,
        headers: {
          'X-AGENT-ID': agentId,
          'X-SESSION-ID': this.sessionId,
          'X-CONVERSATION-ID': this.getCurrentActiveConversationId()
        }
      });

      if (!response.ok) {
        const errorBody = await response.json().catch(() => ({}));
        const msg =
          (errorBody?.errors?.file && errorBody.errors.file[0]) ||
          errorBody?.message ||
          t('widget.upload_failed');
        this._removeAttachment(tempId);
        chip.remove();
        const el = await this.addMessage(CSS_CLASSES.MESSAGE_SYSTEM, msg, false);
        el?.classList.add('error');
        return;
      }

      const body = await response.json();
      const att = body.data;
      // Swap the temp entry for the server's authoritative attachment record.
      this.attachments = this.attachments.filter(a => a.id !== tempId);
      this.attachments.push(att);
      this._updateAttachmentChip(chip, att);
    } catch (err) {
      console.error('Attachment upload failed:', err);
      this._removeAttachment(tempId);
      chip.remove();
      const el = await this.addMessage(
        CSS_CLASSES.MESSAGE_SYSTEM,
        t('widget.upload_failed'),
        false
      );
      el?.classList.add('error');
    } finally {
      if (this.attachments.length === 0) {
        this.attachmentsStrip.style.display = 'none';
      }
    }
  }

  _addAttachmentChip({ id, filename, size_bytes, mime_type, url, uploading = false }) {
    this.attachments.push({ id, filename, size_bytes, mime_type, url });
    this.attachmentsStrip.style.display = 'flex';

    const chip = createElement('div', { className: CSS_CLASSES.ATTACHMENT_CHIP });
    if (uploading) chip.classList.add(CSS_CLASSES.ATTACHMENT_CHIP_UPLOADING);
    chip.dataset.attachmentId = id;

    chip.appendChild(createIcon(uploading ? 'spinner' : 'file-text'));

    const nameEl = createElement('span', {
      className: CSS_CLASSES.ATTACHMENT_CHIP_NAME,
      textContent: filename
    });
    chip.appendChild(nameEl);

    const sizeEl = createElement('span', {
      className: CSS_CLASSES.ATTACHMENT_CHIP_SIZE,
      textContent: formatBytes(size_bytes)
    });
    chip.appendChild(sizeEl);

    const removeBtn = createElement(
      'button',
      {
        className: CSS_CLASSES.ATTACHMENT_CHIP_REMOVE,
        'aria-label': t('widget.remove_attachment'),
        type: 'button'
      },
      {
        click: () => {
          this._removeAttachment(id);
          chip.remove();
          if (this.attachments.length === 0) {
            this.attachmentsStrip.style.display = 'none';
          }
        }
      }
    );
    removeBtn.appendChild(createIcon('x'));
    chip.appendChild(removeBtn);

    this.attachmentsStrip.appendChild(chip);
    return chip;
  }

  _updateAttachmentChip(chip, att) {
    chip.dataset.attachmentId = att.id;
    chip.classList.remove(CSS_CLASSES.ATTACHMENT_CHIP_UPLOADING);
    // Swap spinner icon for file-text icon.
    const oldIcon = chip.querySelector('svg');
    if (oldIcon) oldIcon.replaceWith(createIcon('file-text'));
  }

  _removeAttachment(id) {
    this.attachments = this.attachments.filter(a => a.id !== id);
  }

  _clearAttachments() {
    this.attachments = [];
    if (this.attachmentsStrip) {
      this.attachmentsStrip.innerHTML = '';
      this.attachmentsStrip.style.display = 'none';
    }
  }

  _createBrandFooter() {
    return createElement('div', { className: CSS_CLASSES.BRAND_FOOTER });
  }

  async _loadExternalLibrariesIfNeeded() {
    // Libraries are now bundled statically, just refresh streaming messages
    await this.refreshStreamingMessages();
  }

  // ========================================
  // Dialogs
  // ========================================

  _showConfirmationDialog(title, message, confirmText, onConfirm) {
    // Dialog lives at document.body, so dir is not inherited from the widget
    // container — stamp it explicitly to flip text/button alignment in RTL.
    const dialog = createElement('div', {
      className: CSS_CLASSES.CONFIRM_DIALOG,
      dir: getDirection()
    });

    const content = createElement('div', { className: CSS_CLASSES.CONFIRM_CONTENT });

    const titleEl = createElement('h3');
    titleEl.textContent = title;

    const messageEl = createElement('p');
    messageEl.textContent = message;

    const buttonsContainer = createElement('div', { className: CSS_CLASSES.CONFIRM_BUTTONS });

    const cancelButton = createElement('button', {
      className: CSS_CLASSES.CONFIRM_CANCEL,
      textContent: t('confirm.cancel')
    });

    const deleteButton = createElement('button', {
      className: CSS_CLASSES.CONFIRM_DELETE,
      textContent: confirmText
    });

    buttonsContainer.appendChild(cancelButton);
    buttonsContainer.appendChild(deleteButton);

    content.appendChild(titleEl);
    content.appendChild(messageEl);
    content.appendChild(buttonsContainer);
    dialog.appendChild(content);

    document.body.appendChild(dialog);

    cancelButton.addEventListener('click', () => dialog.remove());
    deleteButton.addEventListener('click', () => {
      onConfirm();
      dialog.remove();
    });
  }

  // ========================================
  // Handoff (delegated)
  // ========================================

  isInHumanMode() {
    return handoff.isInHumanMode(this);
  }

  isInPendingMode() {
    return handoff.isInPendingMode(this);
  }

  getHandoffMode() {
    return handoff.getHandoffMode(this);
  }

  getAdminName() {
    return handoff.getAdminName(this);
  }

  async requestHumanAssistance() {
    return handoff.requestHumanAssistance(this);
  }

  async checkHandoffStatus() {
    return handoff.checkHandoffStatus(this);
  }

  sendTypingIndicator(isTyping) {
    return handoff.sendTypingIndicator(this, isTyping);
  }

  createTalkToHumanButton(onClick) {
    return handoff.createTalkToHumanButton(this, onClick);
  }

  // ========================================
  // Cleanup
  // ========================================

  destroy() {
    this._cancelTrackedRafs(['_scrollRAF', '_resizeRAF', '_streamRenderRAF']);
    this._streamRenderScheduled = false;

    this._clearTrackedTimers([
      '_finalizeTimeout',
      '_loadingSafetyTimeout',
      '_wsMessageLoadingTimeout',
      '_loadingTimeout',
      '_nextBubbleTimeout',
      '_showOnLoadTimeout',
      '_initialResizeTimeout',
      '_welcomeMessageTimeout'
    ]);

    // Remove all global document/window listeners in one shot.
    if (this._eventController) {
      this._eventController.abort();
      this._eventController = null;
    }

    // Detach the prefers-color-scheme listener used by 'auto' theme.
    config.teardownAutoThemeListener(this);

    if (this.sseClient) {
      this.sseClient.close();
      this.sseClient = null;
    }

    // Hard teardown of handoff/Pusher: stop the heartbeat interval and fully
    // disconnect the socket (cleanupHandoff only unsubscribes, keeping the
    // client alive — wrong for a destroy).
    handoff.stopHeartbeat(this);
    handoff.disconnectPusher(this);

    controls.unlockBodyScroll(this);

    if (this.fetchSpinner) {
      this.fetchSpinner.remove();
      this.fetchSpinner = null;
    }

    if (this.container && this.container.parentNode) {
      this.container.parentNode.removeChild(this.container);
    }

    debug('Widget destroyed and resources cleaned up');
  }
}
