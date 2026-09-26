/**
 * Event Handlers Module
 * Sets up all event listeners for the widget
 */

import { CSS_CLASSES } from '../utils.js';
import { matchesTrigger } from '../keyboardTrigger.js';
import { updateScrollToBottomButton } from './controls.js';

/**
 * Setup all event listeners
 */
export function setupEventListeners(widget) {
  // One controller for all global (document/window) listeners so destroy() can
  // remove them in a single abort() — element-scoped listeners die with the DOM.
  if (!widget._eventController) widget._eventController = new AbortController();

  if (widget.mode === 'bar') {
    setupBarListeners(widget);
  } else {
    setupBubbleListeners(widget);
  }
  setupWindowControlListeners(widget);
  setupInputListeners(widget);
  setupHeaderButtonListeners(widget);
  setupGlobalListeners(widget);
  setupScrollListener(widget);
  widget.autoResizeInput();
}

/**
 * Track scroll position on the messages container so the floating
 * scroll-to-bottom button can appear when the visitor reads earlier messages.
 * Listener is on the container itself (element-scoped), so it dies with the
 * DOM on destroy() — no need to register it on the global AbortController.
 *
 * `passive: true` because we never `preventDefault`; the browser can optimize
 * scroll handling and we get the same callback rate without blocking.
 */
function setupScrollListener(widget) {
  if (!widget.messagesContainer) return;
  let raf = 0;
  const onScroll = () => {
    if (raf) return; // coalesce rapid scroll events to one frame
    raf = requestAnimationFrame(() => {
      raf = 0;
      updateScrollToBottomButton(widget);
    });
  };
  widget.messagesContainer.addEventListener('scroll', onScroll, { passive: true });

  // Release a top-pin (ChatGPT-style) the moment the visitor scrolls by their
  // own hand. We key off wheel/touch — genuine user gestures — rather than
  // scrollTop deltas, because the pin itself moves scrollTop programmatically
  // and a delta heuristic can't tell the two apart (that confusion is what made
  // the question "drop" when a turn finalized).
  const releaseOnUserScroll = () => {
    if (widget._pinActive) widget._pinReleased = true;
  };
  widget.messagesContainer.addEventListener('wheel', releaseOnUserScroll, { passive: true });
  widget.messagesContainer.addEventListener('touchmove', releaseOnUserScroll, { passive: true });

  // Initial sync — if the chat opens with content already overflowing and the
  // saved scroll position isn't at the bottom, show the button immediately.
  updateScrollToBottomButton(widget);
}

/**
 * Setup bubble click listeners
 */
export function setupBubbleListeners(widget) {
  widget.chatBubble?.addEventListener('click', () => widget.toggleChat());
  widget.chatBubble?.addEventListener('keydown', e => {
    if (e.key === 'Enter' || e.key === ' ') {
      e.preventDefault();
      widget.toggleChat();
    }
  });
}

/**
 * Wire up interactions for the floating bar launcher (bar mode).
 * The bar expands on focus/click, contracts on blur (when the panel is
 * closed), and submits on Enter or button click.
 */
export function setupBarListeners(widget) {
  // Expand on focus
  widget.barInput?.addEventListener('focus', () => {
    widget.bar?.classList.add('chatvia-widget-expanded');
  });

  // Contract on blur (only while the chat panel is closed)
  widget.barInput?.addEventListener('blur', () => {
    if (!widget.isOpen) {
      widget.bar?.classList.remove('chatvia-widget-expanded');
    }
  });

  // Clicking anywhere on the bar focuses the input
  widget.bar?.addEventListener('click', () => {
    widget.bar?.classList.add('chatvia-widget-expanded');
    widget.barInput?.focus();
  });

  // Enter submits
  widget.barInput?.addEventListener('keydown', async e => {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      await handleBarSubmit(widget);
    }
  });

  // Submit button click
  widget.barButton?.addEventListener('click', async () => {
    await handleBarSubmit(widget);
  });

  // History dot — open the slider panel without sending anything, so the
  // user can see (and continue) the conversation they already started.
  // preventDefault on mousedown keeps the input focused: otherwise the input
  // blurs, the blur handler collapses the bar from 42→36px mid-press, and
  // mouseup lands outside the shrunken dot so click never fires.
  widget.barHistoryButton?.addEventListener('mousedown', e => {
    e.preventDefault();
  });
  widget.barHistoryButton?.addEventListener('click', e => {
    e.stopPropagation();
    if (!widget.isOpen) {
      widget.toggleChat(true);
    }
  });
}

/**
 * Process a submission from the bar: open the chat panel and send the query
 * as the first message.
 */
async function handleBarSubmit(widget) {
  const query = widget.barInput?.value?.trim();
  if (!query) return;

  widget.barInput.value = '';

  if (!widget.isOpen) {
    widget.toggleChat(true);
  }

  await widget.sendUserMessage(query);
}

/**
 * Setup window control listeners
 */
export function setupWindowControlListeners(widget) {
  widget.closeButton?.addEventListener('click', () => widget.toggleChat(false));
  widget.closeButton?.addEventListener('keydown', e => {
    if (e.key === 'Enter' || e.key === ' ') {
      e.preventDefault();
      widget.toggleChat(false);
    }
  });
}

/**
 * Setup input listeners
 */
export function setupInputListeners(widget) {
  widget.sendButton?.addEventListener('click', () => widget.sendUserMessage());
  widget.sendButton?.addEventListener('keydown', e => {
    if (e.key === 'Enter' || e.key === ' ') {
      e.preventDefault();
      widget.sendUserMessage();
    }
  });
  widget.chatInput?.addEventListener('keydown', async e => {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      await widget.sendUserMessage();
    }
  });
  widget.chatInput?.addEventListener('input', () => {
    widget.autoResizeInput();

    // Send typing indicator in human mode
    if (widget.isInHumanMode && widget.isInHumanMode()) {
      const hasText = widget.chatInput.value.trim().length > 0;
      widget.sendTypingIndicator(hasText);
    }
  });
}

/**
 * Setup header button listeners
 */
export function setupHeaderButtonListeners(widget) {
  widget.expandButton?.addEventListener('click', () => widget.toggleExpand());
  widget.expandButton?.addEventListener('keydown', e => {
    if (e.key === 'Enter' || e.key === ' ') {
      e.preventDefault();
      widget.toggleExpand();
    }
  });
  widget.deleteConversationButton?.addEventListener('click', () =>
    widget.confirmDeleteConversation()
  );
  widget.deleteConversationButton?.addEventListener('keydown', e => {
    if (e.key === 'Enter' || e.key === ' ') {
      e.preventDefault();
      widget.confirmDeleteConversation();
    }
  });
  widget.themeToggleButton?.addEventListener('click', () => widget.toggleTheme());
  widget.themeToggleButton?.addEventListener('keydown', e => {
    if (e.key === 'Enter' || e.key === ' ') {
      e.preventDefault();
      widget.toggleTheme();
    }
  });
  widget.menuButton?.addEventListener('click', e => {
    e.stopPropagation();
    widget.toggleMenuDropdown();
  });
  widget.menuButton?.addEventListener('keydown', e => {
    if (e.key === 'Enter' || e.key === ' ') {
      e.preventDefault();
      e.stopPropagation();
      widget.toggleMenuDropdown();
    }
  });
}

/**
 * Setup global listeners
 */
export function setupGlobalListeners(widget) {
  const closeMenuOnOutside = e => {
    if (
      widget.menuDropdown?.classList.contains(CSS_CLASSES.MENU_DROPDOWN_OPEN) &&
      !widget.menuDropdown.contains(e.target) &&
      !widget.menuButton?.contains(e.target)
    ) {
      widget.closeMenuDropdown();
    }
  };

  const signal = widget._eventController?.signal;
  document.addEventListener('mousedown', closeMenuOnOutside, { signal });
  document.addEventListener('touchstart', closeMenuOnOutside, { passive: true, signal });

  setupKeyboardShortcuts(widget);
}

/**
 * Setup keyboard shortcuts
 */
export function setupKeyboardShortcuts(widget) {
  widget._historyIndex = -1;
  widget._userInputHistory = [];

  document.addEventListener(
    'keydown',
    e => {
      // Configured launcher shortcut. Falls through to the platform default
      // (cmd+k / ctrl+k) when the backend hasn't sent a `keyboard_trigger`
      // (legacy widgets or a transient API gap).
      const trigger = widget.settings?.keyboard_trigger;

      if (matchesTrigger(e, trigger)) {
        // For the bare `/` / `?` triggers, don't hijack the key while the user
        // is typing into the chat input or the bar's own input — that would
        // make it impossible to actually type those characters into a message.
        // The modifier triggers (cmd+…) are global because the modifier itself
        // is the disambiguator.
        const isBareKeyTrigger = trigger === '/' || trigger === 'shift+/';
        if (isBareKeyTrigger) {
          const active = document.activeElement;
          const inWidgetInput = active === widget.chatInput || active === widget.barInput;
          // Also bail when typing into any other text-entry field on the host
          // page; the launcher should never steal slashes from the embedder.
          const isHostTextField =
            active &&
            (active.tagName === 'INPUT' ||
              active.tagName === 'TEXTAREA' ||
              active.isContentEditable);
          if (inWidgetInput || isHostTextField) return;
        }

        e.preventDefault();

        if (widget.mode === 'bar') {
          if (widget.isOpen) {
            widget.toggleChat(false);
          } else if (widget.bar?.classList.contains('chatvia-widget-expanded')) {
            widget.bar.classList.remove('chatvia-widget-expanded');
            widget.barInput?.blur();
          } else {
            widget.bar?.classList.add('chatvia-widget-expanded');
            widget.barInput?.focus();
          }
        } else {
          widget.toggleChat();
          if (widget.isOpen) {
            setTimeout(() => widget.chatInput?.focus(), 100);
          }
        }
        return;
      }

      // Escape to close panel
      if (e.key === 'Escape') {
        if (widget.menuDropdownIsOpened()) {
          widget.closeMenuDropdown();
          return;
        }
        if (widget.isOpen) {
          e.preventDefault();
          widget.toggleChat(false);
          return;
        }
        if (widget.mode === 'bar' && widget.bar?.classList.contains('chatvia-widget-expanded')) {
          widget.bar.classList.remove('chatvia-widget-expanded');
          widget.barInput?.blur();
          return;
        }
      }

      // Arrow up/down to navigate user message history
      const activeElement = document.activeElement;
      const isInputFocused =
        activeElement === widget.chatInput || activeElement === widget.barInput;

      if (isInputFocused && (e.key === 'ArrowUp' || e.key === 'ArrowDown')) {
        const userMessages = getUserMessageHistory(widget);
        if (userMessages.length === 0) return;

        e.preventDefault();

        if (e.key === 'ArrowUp') {
          if (widget._historyIndex < userMessages.length - 1) {
            if (widget._historyIndex === -1) {
              widget._savedInput = activeElement.value;
            }
            widget._historyIndex++;
            activeElement.value = userMessages[userMessages.length - 1 - widget._historyIndex];
          }
        } else if (e.key === 'ArrowDown') {
          if (widget._historyIndex > 0) {
            widget._historyIndex--;
            activeElement.value = userMessages[userMessages.length - 1 - widget._historyIndex];
          } else if (widget._historyIndex === 0) {
            widget._historyIndex = -1;
            activeElement.value = widget._savedInput || '';
          }
        }

        activeElement.selectionStart = activeElement.selectionEnd = activeElement.value.length;
      }
    },
    { signal: widget._eventController?.signal }
  );

  // Reset history index when user types
  const resetHistoryIndex = () => {
    widget._historyIndex = -1;
    widget._savedInput = '';
  };

  widget.chatInput?.addEventListener('input', resetHistoryIndex);
  widget.barInput?.addEventListener('input', resetHistoryIndex);

  // Window resize listener to recalculate dynamic widget height
  let resizeTimeout;
  window.addEventListener(
    'resize',
    () => {
      // Debounce resize events
      clearTimeout(resizeTimeout);
      resizeTimeout = setTimeout(() => {
        if (widget.applyWindowSize && !widget.isFlat) {
          widget.applyWindowSize();
        }
      }, 150);
    },
    { signal: widget._eventController?.signal }
  );
}

/**
 * Get user message history
 */
function getUserMessageHistory(widget) {
  const activeConversation = widget.getActiveConversation();
  if (!activeConversation?.messages) return [];

  return activeConversation.messages.filter(m => m.role === 'user').map(m => m.content);
}
