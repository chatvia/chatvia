/**
 * Message Handling Module
 * Handles message display, streaming, and markdown parsing
 */

import {
  CSS_CLASSES,
  STREAMING_FINALIZE_TIMEOUT,
  debug,
  safeImageUrl,
  formatBytes
} from '../utils.js';
import { createElement, createIcon } from '../ui.js';
import { createInlineForm } from '../forms.js';
import { isInHumanMode, sendMessageInHumanMode, subscribeToConversation } from './handoff.js';
import { pickLocalized, t } from '../i18n.js';
// Canonical markdown impl lives in markdown.js. Import for local use, and
// re-export so existing call sites that go through messages.js keep working.
import { parseMarkdown, escapeHtml, htmlToText } from '../markdown.js';
import { getApiOrigin } from '../endpoints.js';
export { parseMarkdown, escapeHtml };

/**
 * Add a message to the chat
 */
export async function addMessage(widget, type, text, addToHistory = true, options = {}) {
  if (!widget.messagesContainer) return null;

  const isHumanAgentMessage = options.isHuman === true;

  // Build class list - add human agent class if from human agent
  let messageClass = `${CSS_CLASSES.MESSAGE} ${type}`;
  if (isHumanAgentMessage) {
    messageClass += ` ${CSS_CLASSES.MESSAGE_HUMAN_AGENT}`;
  }

  const message = createElement('div', { className: messageClass });
  const contentWrapper = createElement('div', { className: 'chatvia-widget-content-wrapper' });

  if (type === CSS_CLASSES.MESSAGE_AGENT) {
    const avatar = createAvatar(widget, isHumanAgentMessage);
    if (avatar) contentWrapper.appendChild(avatar);
  }

  // Inline form artifacts (assistant turns). A form-only turn carries no text,
  // so skip the empty bubble and render just the form below.
  const formArtifacts = Array.isArray(options.formArtifacts) ? options.formArtifacts : [];
  const skipEmptyAgentBubble =
    type === CSS_CLASSES.MESSAGE_AGENT && !text && formArtifacts.length > 0;

  // For attachment-only user turns, skip the empty bubble entirely so the
  // chips don't sit next to a hollow bubble.
  const attachmentList = Array.isArray(options.attachments) ? options.attachments : [];
  if (
    !skipEmptyAgentBubble &&
    (text || type !== CSS_CLASSES.MESSAGE_USER || attachmentList.length === 0)
  ) {
    const bubble = createElement('div', { className: CSS_CLASSES.MESSAGE_BUBBLE });
    await setMessageBubbleContent(bubble, type, text);
    contentWrapper.appendChild(bubble);
  }

  // Forms render as real DOM under the bubble (DOMPurify strips form markup).
  for (const fa of formArtifacts) {
    contentWrapper.appendChild(createInlineForm(widget, fa, options.messageId));
  }

  // Render attachment chips BELOW the bubble for user messages — keeps the
  // chips visually attached to the message that introduced them (history
  // reload pulls `message.attachments` from the server; the just-sent turn
  // gets them via options.attachments).
  if (type === CSS_CLASSES.MESSAGE_USER && attachmentList.length > 0) {
    const strip = createElement('div', { className: CSS_CLASSES.ATTACHMENTS_STRIP });
    for (const att of attachmentList) {
      strip.appendChild(_renderAttachmentChipLink(att));
    }
    contentWrapper.appendChild(strip);
  }

  message.appendChild(contentWrapper);

  // Add action buttons for AI assistant messages only (not human agent messages)
  // Timestamp goes inside actions for AI messages, standalone otherwise
  const hasActions =
    type === CSS_CLASSES.MESSAGE_AGENT && text && !options.hideActions && !isHumanAgentMessage;

  if (hasActions) {
    const actionsContainer = createMessageActions(
      widget,
      text,
      options.messageId,
      options.feedback,
      options.timestamp
    );
    message.appendChild(actionsContainer);
  } else if (options.timestamp || options.isHuman) {
    // Standalone timestamp for user messages and human agent messages
    const timeElement = createElement('div', {
      className: CSS_CLASSES.MESSAGE_TIME,
      title: options.timestamp ? formatFullDate(options.timestamp) : ''
    });

    // Add read status for user messages in human mode
    if (type === CSS_CLASSES.MESSAGE_USER && widget.handoffMode === 'human') {
      const readStatus = createReadStatusIndicator(options.readAt);
      timeElement.appendChild(readStatus);
    }

    if (options.timestamp) {
      const timeText = document.createTextNode(formatMessageTime(options.timestamp));
      timeElement.appendChild(timeText);
    }

    message.appendChild(timeElement);
  }

  if (addToHistory) {
    addMessageToHistory(widget, type, text, options);
  }

  widget.messagesContainer.appendChild(message);
  widget.scrollToBottom();
  return message;
}

/**
 * Normalize a timestamp (Unix seconds, Unix ms, or ISO string) into a valid
 * Date, or null when it's missing/unparseable/implausible (before year 2000).
 * Single source of truth for the date parsing both formatters previously
 * duplicated.
 */
function parseTimestamp(timestamp) {
  if (!timestamp) return null;

  // Unix timestamp in seconds — a number below the year-3000 second count is
  // treated as seconds and scaled to ms; larger numbers are already ms.
  const date =
    typeof timestamp === 'number' && timestamp < 32503680000
      ? new Date(timestamp * 1000)
      : new Date(timestamp);

  if (isNaN(date.getTime()) || date.getFullYear() < 2000) return null;
  return date;
}

/**
 * Format timestamp for display
 */
function formatMessageTime(timestamp) {
  const date = parseTimestamp(timestamp);
  if (!date) return '';

  const now = new Date();
  const diffMs = now - date;

  // If negative or very small, it's "just now"
  if (diffMs < 0) return t('conversations.just_now');

  const diffMins = Math.floor(diffMs / 60000);
  const diffHours = Math.floor(diffMs / 3600000);
  const diffDays = Math.floor(diffMs / 86400000);

  if (diffMins < 1) return t('conversations.just_now');
  if (diffMins === 1) return t('conversations.minute_ago');
  if (diffMins < 60) return t('conversations.minutes_ago', { count: diffMins });
  if (diffHours === 1) return t('conversations.hour_ago');
  if (diffHours < 24) return t('conversations.hours_ago', { count: diffHours });
  if (diffDays === 1) return t('conversations.yesterday');
  if (diffDays < 30) return t('conversations.days_ago', { count: diffDays });

  const diffMonths = Math.floor(diffDays / 30);
  if (diffMonths < 12) {
    return diffMonths === 1
      ? t('conversations.month_ago')
      : t('conversations.months_ago', { count: diffMonths });
  }

  const diffYears = Math.floor(diffDays / 365);
  return diffYears === 1
    ? t('conversations.year_ago')
    : t('conversations.years_ago', { count: diffYears });
}

/**
 * Format full date for tooltip
 */
function formatFullDate(timestamp) {
  const date = parseTimestamp(timestamp);
  if (!date) return '';

  return date.toLocaleString(undefined, {
    weekday: 'short',
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  });
}

/**
 * Create read status indicator (checkmarks for sent/read)
 */
function createReadStatusIndicator(readAt) {
  const container = createElement('span', {
    className: `chatvia-widget-read-status ${readAt ? 'read' : ''}`
  });

  // Double checkmark SVG
  const checkSvg = `
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
      <path d="M18 6L7 17l-5-5"/>
      <path d="M22 6L11 17"/>
    </svg>
  `;

  container.innerHTML = checkSvg;
  container.title = readAt ? t('messages.read') : t('messages.sent');

  return container;
}

/**
 * Create avatar element
 */
// Human agent icon SVG
const HUMAN_AGENT_SVG = `
  <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
    <circle cx="12" cy="7" r="4"></circle>
  </svg>
`;

export function createAvatar(widget, isHumanAgent = false) {
  // Human agents always show the person icon — the message needs a visual
  // marker that distinguishes a live operator from the AI.
  if (isHumanAgent) {
    const avatar = createElement('div', { className: CSS_CLASSES.MESSAGE_AVATAR });
    avatar.innerHTML = HUMAN_AGENT_SVG;
    return avatar;
  }

  // AI assistant only renders an avatar when the operator has configured
  // `settings.avatar_url`. No fallback icon — if the operator wants an icon,
  // they set one in settings. Returning null tells callers to skip the slot.
  const avatarUrl = safeImageUrl(widget.settings.avatar_url);
  if (!avatarUrl) return null;

  const avatar = createElement('div', { className: CSS_CLASSES.MESSAGE_AVATAR });
  const avatarImg = createElement('img', {
    src: avatarUrl,
    alt: 'Assistant',
    referrerpolicy: 'no-referrer'
  });
  avatar.appendChild(avatarImg);
  return avatar;
}

/**
 * Create message action buttons
 */
export function createMessageActions(widget, messageText, messageId, feedback, timestamp) {
  const actionsContainer = createElement('div', {
    className: CSS_CLASSES.MESSAGE_ACTIONS
  });

  // Copy button - always show (left side)
  const copyButton = createElement('button', {
    className: 'chatvia-widget-action-btn chatvia-widget-copy-btn',
    'aria-label': t('messages.copy_message'),
    title: t('messages.copy')
  });
  copyButton.appendChild(createIcon('copy'));
  copyButton.addEventListener('click', () => handleCopyMessage(messageText, copyButton));
  actionsContainer.appendChild(copyButton);

  // Only show feedback buttons if there's a message ID
  if (messageId) {
    // Thumbs up button
    const thumbsUpButton = createElement('button', {
      className: `chatvia-widget-action-btn chatvia-widget-thumbs-up${feedback === 'positive' ? ' chatvia-widget-active' : ''}`,
      'aria-label': t('messages.good_response'),
      title: t('messages.good_response'),
      'data-message-id': messageId
    });
    thumbsUpButton.appendChild(createIcon('thumbs-up'));

    // Thumbs down button
    const thumbsDownButton = createElement('button', {
      className: `chatvia-widget-action-btn chatvia-widget-thumbs-down${feedback === 'negative' ? ' chatvia-widget-active' : ''}`,
      'aria-label': t('messages.bad_response'),
      title: t('messages.bad_response'),
      'data-message-id': messageId
    });
    thumbsDownButton.appendChild(createIcon('thumbs-down'));

    // Add event listeners
    thumbsUpButton.addEventListener('click', () =>
      handleFeedback(widget, messageId, true, thumbsUpButton, thumbsDownButton)
    );
    thumbsDownButton.addEventListener('click', () =>
      handleFeedback(widget, messageId, false, thumbsDownButton, thumbsUpButton)
    );

    actionsContainer.appendChild(thumbsUpButton);
    actionsContainer.appendChild(thumbsDownButton);
  }

  // Add timestamp after feedback buttons (with divider)
  if (timestamp) {
    const timeElement = createElement('div', {
      className: CSS_CLASSES.MESSAGE_TIME,
      title: formatFullDate(timestamp) // Full date on hover
    });
    timeElement.textContent = formatMessageTime(timestamp);
    actionsContainer.appendChild(timeElement);
  }

  return actionsContainer;
}

/**
 * Handle copy message action
 */
export async function handleCopyMessage(text, button) {
  try {
    // Strip any markup to plain text WITHOUT routing untrusted assistant
    // output through element.innerHTML (a DOM-XSS sink — see htmlToText).
    const plainText = htmlToText(text) || text;

    await navigator.clipboard.writeText(plainText);

    const originalIcon = button.innerHTML;
    button.innerHTML = '';
    button.appendChild(createIcon('check'));
    button.classList.add('chatvia-widget-copied');

    setTimeout(() => {
      button.innerHTML = originalIcon;
      button.classList.remove('chatvia-widget-copied');
    }, 2000);
  } catch (error) {
    console.error('Failed to copy message:', error);
  }
}

/**
 * Handle feedback action
 */
export async function handleFeedback(widget, messageId, isPositive, clickedButton, otherButton) {
  if (!messageId) return;

  const isAlreadyActive = clickedButton.classList.contains('chatvia-widget-active');

  try {
    const api = (await import('../api.js')).default;

    const response = await api({
      endpoint: 'feedback',
      method: 'POST',
      headers: {
        'X-AGENT-ID': widget.agentId,
        'X-SESSION-ID': widget.sessionId,
        'X-CONVERSATION-ID': widget.getCurrentActiveConversationId(),
        'X-MESSAGE-ID': messageId
      },
      body: JSON.stringify({ feedback: isPositive })
    });

    if (response.ok) {
      if (isAlreadyActive) {
        clickedButton.classList.remove('chatvia-widget-active');
      } else {
        clickedButton.classList.add('chatvia-widget-active');
        otherButton.classList.remove('chatvia-widget-active');
      }

      // Mirror the choice onto the in-memory message so a later re-render
      // (history reload, conversation switch) re-derives the same active
      // state. The canonical persisted form is the string the API returns
      // ('positive' / 'negative'), never a boolean — createMessageActions
      // matches on those strings.
      const message = widget.messageHistory?.find(m => m.id === messageId);
      if (message) {
        message.feedback = isAlreadyActive ? null : isPositive ? 'positive' : 'negative';
      }
    } else {
      console.error('Failed to submit feedback:', response.status);
    }
  } catch (error) {
    console.error('Error submitting feedback:', error);
  }
}

/**
 * Set message bubble content
 */
/**
 * Render a clickable file chip for a persisted attachment. Clicking opens
 * the public URL in a new tab so the visitor can re-download what they sent.
 * Icon comes from `<API origin>/images/icons/files/<ext>.svg` — drop a
 * new SVG in there and the chip picks it up automatically.
 */
function _renderAttachmentChipLink(att) {
  const link = createElement('a', {
    className: CSS_CLASSES.ATTACHMENT_CHIP,
    href: att.url,
    target: '_blank',
    rel: 'noopener noreferrer'
  });

  const iconEl = createElement('img', {
    src: _iconUrlFor(att),
    alt: att.mime_type || '',
    className: CSS_CLASSES.ATTACHMENT_CHIP_ICON
  });
  link.appendChild(iconEl);

  const meta = createElement('div', { className: CSS_CLASSES.ATTACHMENT_CHIP_META });

  const nameEl = createElement('span', {
    className: CSS_CLASSES.ATTACHMENT_CHIP_NAME,
    textContent: att.filename || 'attachment'
  });
  meta.appendChild(nameEl);

  if (att.size_bytes) {
    const sizeEl = createElement('span', {
      className: CSS_CLASSES.ATTACHMENT_CHIP_SIZE,
      textContent: formatBytes(att.size_bytes)
    });
    meta.appendChild(sizeEl);
  }

  link.appendChild(meta);
  return link;
}

const _KNOWN_FILE_EXTENSIONS = new Set(['pdf']);

function _iconUrlFor(att) {
  const ext = (att.filename || '').split('.').pop()?.toLowerCase();
  const origin = getApiOrigin() || '';
  if (ext && _KNOWN_FILE_EXTENSIONS.has(ext)) {
    return origin + '/images/icons/files/' + ext + '.svg';
  }
  if (att.mime_type === 'application/pdf') return origin + '/images/icons/files/pdf.svg';
  return origin + '/images/icons/files/file.svg';
}

function setMessageBubbleContent(bubble, type, text) {
  if (type === CSS_CLASSES.MESSAGE_AGENT) {
    bubble.innerHTML = parseMarkdown(text);
  } else {
    bubble.textContent = text;
  }
}

/**
 * Add message to history
 */
function addMessageToHistory(widget, type, text, options = {}) {
  const nowIso = new Date().toISOString();
  // Store the SAME shape the server returns ({ role, content, id, created_at })
  // alongside the legacy { type, text } fields. Without `role`/`content`,
  // readers like getUserMessageHistory (Arrow-Up recall) and the history
  // re-render silently skipped messages added during the current session.
  const messageData = {
    type,
    text,
    role: mapMessageTypeToRole(type),
    content: text,
    id: options.messageId,
    feedback: options.feedback,
    timestamp: nowIso,
    created_at: options.timestamp || nowIso,
    // Mirror the server shape so an in-session re-render (conversation switch)
    // can re-mount inline form artifacts without a round trip.
    metadata: options.metadata,
    options
  };
  widget.messageHistory.push(messageData);
  const activeConversation = widget.getActiveConversation();

  if (activeConversation) {
    if (!Array.isArray(activeConversation.messages)) activeConversation.messages = [];
    activeConversation.messages.push(messageData);

    if (type === CSS_CLASSES.MESSAGE_USER) {
      activeConversation.hasUserInteraction = true;
      widget._updateConversationActionButtons();
    }
  }
}

/**
 * Send user message
 */
export async function sendUserMessage(widget, customText) {
  const text = customText || widget.chatInput.value.trim();
  // Allow attachment-only messages — the user can attach a PDF and hit send
  // without typing anything; the LLM still gets the URL through the notice
  // appended server-side.
  const pendingAttachments = Array.isArray(widget.attachments) ? [...widget.attachments] : [];
  if (!text && pendingAttachments.length === 0) return;

  const userMessageEl = await addMessage(widget, CSS_CLASSES.MESSAGE_USER, text, true, {
    attachments: pendingAttachments
  });

  if (!customText) {
    widget.chatInput.value = '';
    widget.autoResizeInput();
  }

  widget.hideStarters();

  const activeConversation = widget.getActiveConversation();
  if (activeConversation) {
    activeConversation.hasUserInteraction = true;
    widget._updateConversationActionButtons();
  }

  // Check if we're in human mode (handoff active)
  if (isInHumanMode(widget)) {
    // In human mode, send via HTTP POST (WebSocket receives response)
    debug('Sending message in human mode');
    const success = await sendMessageInHumanMode(widget, text);

    if (!success) {
      const el = await addMessage(
        widget,
        CSS_CLASSES.MESSAGE_SYSTEM,
        t('errors.message_send_failed'),
        false,
        {
          retryText: text
        }
      );
      el?.classList.add('error');
    }

    widget.scrollToBottom();
    return;
  }

  // AI mode - use SSE
  // Pin the just-sent question to the top of the viewport (ChatGPT-style) so the
  // incoming reply streams in beneath it. Held for the turn; the streaming path
  // keeps it in place via followStream().
  widget.pinMessageToTop(userMessageEl);

  // Clear any previous loading timeout
  if (widget._loadingTimeout) {
    clearTimeout(widget._loadingTimeout);
  }

  // Store timeout on widget so it can be cleared when SSE message arrives
  widget._loadingTimeout = setTimeout(() => {
    if (!document.getElementById(CSS_CLASSES.STREAMING_MESSAGE)) {
      widget.showLoading();
    }
  }, 150);

  if (!widget.sseClient) {
    console.error('SSE client not initialized.');
    clearTimeout(widget._loadingTimeout);
    widget.hideLoading();
    const el = await addMessage(
      widget,
      CSS_CLASSES.MESSAGE_SYSTEM,
      t('errors.connection_unavailable'),
      false,
      {
        retryText: text
      }
    );
    el?.classList.add('error');
    return;
  }

  // Snapshot attachments at send time; the strip is cleared right after so
  // the user can attach again for the next turn.
  const attachments = Array.isArray(widget.attachments) ? [...widget.attachments] : [];

  const messagePayload = {
    session_id: widget.sessionId,
    conversation_id: widget.getCurrentActiveConversationId(),
    type: 'message',
    content: text,
    url: window.location.href,
    referrer: document.referrer || '',
    attachments
  };

  if (typeof widget._clearAttachments === 'function') {
    widget._clearAttachments();
  }

  // Don't log full payload in production — it contains user content.
  debug('Sending SSE message', { conversation_id: messagePayload.conversation_id });

  // Reset per-turn stream flags used by the silent-close error guard.
  widget._streamHandled = false;
  widget._streamErrorShown = false;

  const success = await widget.sseClient.send(messagePayload);

  if (!success) {
    console.error('Failed to send message via SSE.');
    clearTimeout(widget._loadingTimeout);
    widget.hideLoading();
    const el = await addMessage(
      widget,
      CSS_CLASSES.MESSAGE_SYSTEM,
      t('errors.message_send_failed'),
      false,
      {
        retryText: text
      }
    );
    el?.classList.add('error');
    widget.chatInput.disabled = false;
    widget.sendButton.disabled = false;
  } else {
    widget.chatInput.disabled = true;
    widget.sendButton.disabled = true;

    // Subscribe to WebSocket now that we have a conversation on the server
    // This enables receiving admin takeover events
    if (!widget.pusherChannel) {
      subscribeToConversation(widget);
    }
  }

  // Keep the pinned question in place (or fall back to bottom if the send failed
  // and an error message cleared the pin).
  widget.followStream();
}

/**
 * Display history messages
 */
export async function displayHistoryMessages(widget) {
  widget.clearMessages();

  // Greeting localized to the widget's active language (falls back to the base).
  const welcome = pickLocalized(
    widget.settings?.text_translations?.welcome_message,
    widget.settings?.welcome_message
  );

  if (widget.messageHistory && widget.messageHistory.length > 0) {
    for (const message of widget.messageHistory) {
      const messageType = message.role ? mapRoleToMessageType(message.role) : message.type;
      const isWelcomeMessage = welcome && message.content === welcome;
      const isHumanAgent = message.role === 'agent';
      await addMessage(widget, messageType, message.content, false, {
        messageId: message.id,
        feedback: message.feedback,
        hideActions: isWelcomeMessage,
        isHuman: isHumanAgent,
        timestamp: message.created_at || message.timestamp,
        readAt: message.read_at,
        attachments: message.attachments || message.metadata?.attachments || [],
        formArtifacts: (message.metadata?.artifacts || [])
          .filter(a => a && a.kind === 'form' && a.data)
          .map(a => a.data)
      });
    }
  } else if (welcome) {
    const activeConversation = widget.getActiveConversation();
    if (activeConversation && !activeConversation.welcomeShown) {
      activeConversation.welcomeShown = true;
      // Tracked on the widget so destroy() can cancel it; the original code
      // leaked this 500ms timer if the widget was torn down during the gap.
      widget._welcomeMessageTimeout = setTimeout(async () => {
        widget._welcomeMessageTimeout = null;
        await addMessage(widget, CSS_CLASSES.MESSAGE_AGENT, welcome, false, {
          hideActions: true
        });
      }, 500);
    }
  }
}

/**
 * Start streaming message
 */
export function startStreamingMessage(widget) {
  if (document.getElementById(CSS_CLASSES.STREAMING_MESSAGE) || !widget.messagesContainer) {
    return;
  }

  const streamingMsgElement = createElement('div', {
    className: `${CSS_CLASSES.MESSAGE} ${CSS_CLASSES.MESSAGE_AGENT}`,
    id: CSS_CLASSES.STREAMING_MESSAGE
  });

  // Tool activity lives OUTSIDE the bubble (the bubble's innerHTML is
  // overwritten on every delta). Holds the transient "calling tool" shimmer.
  const toolActivity = createElement('div', { className: CSS_CLASSES.TOOL_ACTIVITY });
  streamingMsgElement.appendChild(toolActivity);
  widget._activeToolCalls = new Set();
  // Form artifacts surfaced by tools this turn — rendered onto the persisted
  // message at finalize (the tool-activity node is transient).
  widget._streamFormArtifacts = [];

  const contentWrapper = createElement('div', { className: 'chatvia-widget-content-wrapper' });
  // Hidden until the first text delta arrives, so a tool-call shimmer shows on
  // its own with no empty avatar/bubble beneath it.
  contentWrapper.style.display = 'none';
  const avatar = createAvatar(widget);
  if (avatar) {
    avatar.classList.add(CSS_CLASSES.STREAMING); // drives the avatar typing pulse
    contentWrapper.appendChild(avatar);
  }
  const messageBubble = createElement('div', { className: CSS_CLASSES.MESSAGE_BUBBLE });
  contentWrapper.appendChild(messageBubble);

  streamingMsgElement.appendChild(contentWrapper);
  widget.messagesContainer.appendChild(streamingMsgElement);
  widget.followStream();
}

/**
 * Schedule a streaming re-render, coalesced to one paint per animation frame.
 * SSE deltas can arrive far faster than the browser paints; rendering each one
 * re-parsed + DOMPurify-sanitized the entire accumulated message, making a turn
 * O(N²) in its own length. Instead we render the latest text once per frame.
 */
export function updateStreamingMessage(widget) {
  if (widget._streamRenderScheduled) return;
  widget._streamRenderScheduled = true;
  widget._streamRenderRAF = requestAnimationFrame(() => {
    widget._streamRenderScheduled = false;
    widget._streamRenderRAF = null;
    renderStreamingMessageNow(widget);
  });
}

/**
 * Render the current accumulated streamed text synchronously. Always reads the
 * live widget.currentStreamedMessage, so one frame reflects every delta that
 * landed since the previous paint.
 */
function renderStreamingMessageNow(widget) {
  const streamingMsgElement = document.getElementById(CSS_CLASSES.STREAMING_MESSAGE);
  if (!streamingMsgElement) return;

  const messageBubble = streamingMsgElement.querySelector(`.${CSS_CLASSES.MESSAGE_BUBBLE}`);
  if (messageBubble) {
    // Reveal the avatar/bubble row now that real text is streaming in
    // (it stays hidden during a tool-call shimmer, see startStreamingMessage).
    if (widget.currentStreamedMessage) {
      const wrapper = messageBubble.closest('.chatvia-widget-content-wrapper');
      if (wrapper) wrapper.style.display = '';
    }
    updateMessageBubbleContent(messageBubble, widget.currentStreamedMessage);
  }

  widget.followStream();
}

/**
 * Update message bubble content
 */
export function updateMessageBubbleContent(messageBubble, content) {
  try {
    messageBubble.innerHTML = parseMarkdown(content);
  } catch (error) {
    console.error('Error processing markdown:', error);
    messageBubble.textContent = content;
  }
}

/**
 * Finalize streaming message
 */
export async function finalizeStreamingMessage(widget) {
  if (!widget.isStreaming) return;

  // Cancel any pending coalesced render; we render the final text synchronously
  // below, so a late frame must not overwrite or re-append actions.
  if (widget._streamRenderRAF) {
    cancelAnimationFrame(widget._streamRenderRAF);
    widget._streamRenderRAF = null;
  }
  widget._streamRenderScheduled = false;

  debug('Finalizing streaming message');

  clearSSETimeouts(widget);
  widget.hideLoading();

  const finalMessage = widget.currentStreamedMessage;
  const formArtifacts = Array.isArray(widget._streamFormArtifacts)
    ? widget._streamFormArtifacts
    : [];
  const hasForms = formArtifacts.length > 0;
  const messageId = widget.currentStreamedMessageId;

  const streamingMsgElement = document.getElementById(CSS_CLASSES.STREAMING_MESSAGE);
  // Persist the turn if it produced text OR a form (a form-only turn has no text).
  if (streamingMsgElement && (finalMessage !== '' || hasForms)) {
    const wrapper = streamingMsgElement.querySelector('.chatvia-widget-content-wrapper');
    const messageBubble = streamingMsgElement.querySelector(`.${CSS_CLASSES.MESSAGE_BUBBLE}`);
    const finalizedAt = new Date().toISOString();

    if (finalMessage !== '') {
      if (messageBubble) {
        // Ensure the row is visible (e.g. a single-shot final message that never
        // went through updateStreamingMessage's reveal).
        if (wrapper) wrapper.style.display = '';
        await updateMessageBubbleContent(messageBubble, finalMessage);
      }
      const actionsContainer = createMessageActions(
        widget,
        finalMessage,
        messageId,
        undefined,
        finalizedAt
      );
      streamingMsgElement.appendChild(actionsContainer);
    } else {
      // Form-only turn: drop the empty bubble but keep the row for the form.
      if (messageBubble) messageBubble.remove();
      if (wrapper) wrapper.style.display = '';
    }

    if (hasForms && wrapper) {
      for (const fa of formArtifacts) {
        wrapper.appendChild(createInlineForm(widget, fa, messageId));
      }
    }

    streamingMsgElement
      .querySelector(`.${CSS_CLASSES.MESSAGE_AVATAR}`)
      ?.classList.remove(CSS_CLASSES.STREAMING);
    streamingMsgElement.removeAttribute('id');

    addMessageToHistory(widget, CSS_CLASSES.MESSAGE_AGENT, finalMessage, {
      messageId,
      timestamp: finalizedAt,
      metadata: hasForms
        ? { artifacts: formArtifacts.map(data => ({ kind: 'form', data })) }
        : undefined
    });
  } else if (streamingMsgElement) {
    streamingMsgElement.remove();
  }

  widget._streamFormArtifacts = [];

  widget.isStreaming = false;
  widget.currentStreamedMessage = '';
  widget.currentStreamedMessageId = null;

  if (widget.chatInput) {
    widget.chatInput.disabled = false;
    // preventScroll: re-focusing the composer must not scroll the messages
    // container, which would fight the pinned question.
    widget.chatInput.focus({ preventScroll: true });
  }
  if (widget.sendButton) {
    widget.sendButton.disabled = false;
  }
  widget.autoResizeInput();
  // Re-size the reserved spacer and re-assert the pin synchronously now that the
  // actions row (and any forms) have been appended — keeps the question pinned
  // instead of letting the new content nudge the view down at finish.
  widget.holdPin();
}

/**
 * Pick the transient activity phrase for a tool call. The backend resolves a
 * per-connector `activity_label` ("Preparing your form…", "Searching the web…")
 * in config/connectors.php; we render that verbatim. When a connector has no
 * configured phrase (external presets, webhook functions) we fall back to a
 * label-based line, and finally to a generic string — so the indicator never
 * mislabels a first-party capability as an "external tool".
 */
function toolActivityLabel(tool) {
  if (typeof tool.activity_label === 'string' && tool.activity_label !== '') {
    return tool.activity_label;
  }
  if (typeof tool.label === 'string' && tool.label !== '') {
    return t('tool.connecting', { label: tool.label });
  }
  return t('tool.calling');
}

/**
 * Show a transient activity shimmer while a function/MCP call runs, then hide
 * it once every active call has finished so the answer can stream in below.
 * Tracked by tool_call_id so parallel or sequential calls keep the indicator
 * visible until the last one ends — no per-tool icons. The phrase updates to
 * the most recent call so a find→render sequence reads naturally.
 */
export function handleToolEvent(widget, tool) {
  const streamingMsgElement = document.getElementById(CSS_CLASSES.STREAMING_MESSAGE);
  if (!streamingMsgElement || !tool || !tool.tool_call_id) return;

  const container = streamingMsgElement.querySelector(`.${CSS_CLASSES.TOOL_ACTIVITY}`);
  if (!container) return;

  if (!widget._activeToolCalls) widget._activeToolCalls = new Set();

  if (tool.phase === 'start') {
    // Builtin capabilities (forms, charts, leads) are internal plumbing — never
    // show a "Calling …" chip for them. Their `end`-phase artifacts (e.g. an
    // inline form) still render; the call is still logged server-side.
    if (tool.internal) return;
    widget._activeToolCalls.add(tool.tool_call_id);
    let node = container.firstChild;
    if (!node) {
      node = createElement('div', { className: CSS_CLASSES.TOOL_CALLING });
      container.appendChild(node);
    }
    node.textContent = toolActivityLabel(tool);
    widget.followStream();
    return;
  }

  if (tool.phase === 'end') {
    // The `end` phase carries any structured artifacts the tool produced.
    // Stash form artifacts; finalize renders them onto the persisted message.
    if (Array.isArray(tool.artifacts)) {
      if (!Array.isArray(widget._streamFormArtifacts)) widget._streamFormArtifacts = [];
      for (const artifact of tool.artifacts) {
        if (artifact && artifact.kind === 'form' && artifact.data) {
          widget._streamFormArtifacts.push(artifact.data);
        }
      }
    }
    widget._activeToolCalls.delete(tool.tool_call_id);
    if (widget._activeToolCalls.size === 0) {
      container.innerHTML = '';
    }
  }
}

/**
 * Surface a stream error as a system message. Used for backend `error`
 * events, transport failures, and silent empty closes (so the user always
 * sees something instead of a stuck/empty turn).
 */
export async function renderStreamError(widget, message) {
  widget.hideLoading();

  // Drop an empty streaming bubble so we don't leave a blank assistant turn.
  const streamingMsgElement = document.getElementById(CSS_CLASSES.STREAMING_MESSAGE);
  if (streamingMsgElement && !widget.currentStreamedMessage) {
    streamingMsgElement.remove();
  }

  const text = message || t('errors.response_failed');
  const el = await addMessage(widget, CSS_CLASSES.MESSAGE_SYSTEM, text, false);
  // `.error` swaps the default (green handoff-style) system bubble for the
  // red error variant + warning-triangle icon defined in widget.css.
  el?.classList.add('error');

  if (widget.chatInput) widget.chatInput.disabled = false;
  if (widget.sendButton) widget.sendButton.disabled = false;
  widget.scrollToBottom();
}

/**
 * Clear SSE timeouts
 */
export function clearSSETimeouts(widget) {
  if (widget._sseMessageLoadingTimeout) {
    clearTimeout(widget._sseMessageLoadingTimeout);
    widget._sseMessageLoadingTimeout = null;
  }
  if (widget._finalizeTimeout) {
    clearTimeout(widget._finalizeTimeout);
    widget._finalizeTimeout = null;
  }
}

/**
 * Reset finalize timeout
 */
export function resetFinalizeTimeout(widget) {
  if (widget._finalizeTimeout) clearTimeout(widget._finalizeTimeout);
  widget._finalizeTimeout = setTimeout(
    () => finalizeStreamingMessage(widget),
    STREAMING_FINALIZE_TIMEOUT
  );
}

/**
 * Map role to message type
 */
export function mapRoleToMessageType(role) {
  switch (role) {
    case 'user':
      return CSS_CLASSES.MESSAGE_USER;
    case 'assistant':
      return CSS_CLASSES.MESSAGE_AGENT;
    case 'system':
      return CSS_CLASSES.MESSAGE_SYSTEM;
    default:
      return CSS_CLASSES.MESSAGE_AGENT;
  }
}

/**
 * Inverse of {@link mapRoleToMessageType}: map a widget message-type CSS class
 * back to the canonical server role. Lets locally-appended history entries
 * carry the same `{ role, content }` shape the server returns, so every reader
 * (history recall, conversation titles, re-render) sees one shape.
 */
export function mapMessageTypeToRole(type) {
  switch (type) {
    case CSS_CLASSES.MESSAGE_USER:
      return 'user';
    case CSS_CLASSES.MESSAGE_SYSTEM:
      return 'system';
    case CSS_CLASSES.MESSAGE_AGENT:
    default:
      return 'assistant';
  }
}
