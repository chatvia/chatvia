/**
 * Handoff Module
 * Handles switching between AI (SSE) and Human Agent (WebSocket/Pusher) modes
 */

/* global __DEV__ */

import Pusher from 'pusher-js';
import { CSS_CLASSES } from '../utils.js';
import { t } from '../i18n.js';
import { authHeaders } from '../auth.js';
import api from '../api.js';
import { getApiUrl, getReverbConfig } from '../endpoints.js';

// Handoff modes
export const HANDOFF_MODE = {
  AI: 'ai',
  PENDING: 'pending',
  HUMAN: 'human'
};

// Handoff status from server
export const HANDOFF_STATUS = {
  PENDING: 'pending', // Waiting for agent
  HUMAN: 'human', // Connected to human agent
  RETURNED: 'returned', // Returned to AI
  AI: 'ai' // AI mode (same as returned)
};

// Heartbeat interval (30 seconds)
const HEARTBEAT_INTERVAL = 30000;

/**
 * Build the session + agent id headers every handoff endpoint shares. Pass
 * `extra` for per-call additions (e.g. X-CONVERSATION-ID). The requests go
 * through api(), which adds the JSON content headers, the protected-agent auth
 * token (keyed off X-AGENT-ID) and a timeout.
 */
function handoffHeaders(widget, extra = {}) {
  return {
    'X-SESSION-ID': widget.sessionId,
    'X-AGENT-ID': widget.agentId,
    ...extra
  };
}

/**
 * Initialize handoff state on widget
 */
export function initHandoff(widget) {
  widget.handoffMode = HANDOFF_MODE.AI;
  widget.pusherClient = null;
  widget.pusherChannel = null;
  widget.conversationId = null;
  widget.adminName = null;
  widget.isTyping = false;
  widget._typingTimeout = null;
  widget._typingInterval = null;
  widget._isTypingSent = false;
  widget._adminTyping = false;
  widget._heartbeatInterval = null;
}

/**
 * Start heartbeat pings to keep last_activity_at updated
 */
export function startHeartbeat(widget) {
  // Don't start if already running
  if (widget._heartbeatInterval) {
    return;
  }

  // Send initial heartbeat
  sendHeartbeat(widget);

  // Start interval
  widget._heartbeatInterval = setInterval(() => {
    sendHeartbeat(widget);
  }, HEARTBEAT_INTERVAL);
}

/**
 * Stop heartbeat pings
 */
export function stopHeartbeat(widget) {
  if (widget._heartbeatInterval) {
    clearInterval(widget._heartbeatInterval);
    widget._heartbeatInterval = null;
  }
}

/**
 * Send a single heartbeat ping
 */
async function sendHeartbeat(widget) {
  const conversationId = widget.getCurrentActiveConversationId?.();
  if (!conversationId || !widget.sessionId) {
    return;
  }

  try {
    await api({
      endpoint: 'heartbeat',
      method: 'POST',
      headers: handoffHeaders(widget),
      body: JSON.stringify({
        conversation_id: conversationId
      })
    });
  } catch (error) {
    // Silent fail - heartbeat is not critical
    console.debug('Heartbeat failed:', error);
  }
}

/**
 * Mark messages as read (only when widget is open and in human mode)
 */
export async function markMessagesAsRead(widget) {
  // Only mark as read if widget is open and in human mode
  if (!widget.isOpen || widget.handoffMode !== HANDOFF_MODE.HUMAN) {
    return;
  }

  const conversationId = widget.getCurrentActiveConversationId?.();
  if (!conversationId || !widget.sessionId) {
    return;
  }

  try {
    await api({
      endpoint: 'mark-read',
      method: 'POST',
      headers: handoffHeaders(widget),
      body: JSON.stringify({
        conversation_id: conversationId
      })
    });
  } catch (error) {
    // Silent fail - not critical
    console.debug('Mark read failed:', error);
  }
}

/**
 * Check handoff status from server
 */
export async function checkHandoffStatus(widget) {
  try {
    const conversationId = widget.getCurrentActiveConversationId();
    if (!conversationId) {
      return null;
    }

    const response = await api({
      endpoint: 'handoff/status',
      method: 'GET',
      headers: handoffHeaders(widget, { 'X-CONVERSATION-ID': conversationId })
    });

    if (!response.ok) {
      console.warn('Failed to check handoff status:', response.status);
      return null;
    }

    const data = await response.json();

    // Normalize status - 'returned' and 'ai' both mean AI mode
    const serverStatus = data.handoff_status === 'returned' ? 'ai' : data.handoff_status;

    // If status returned is different from current, sync it
    if (serverStatus && serverStatus !== widget.handoffMode) {
      await syncHandoffState(widget, { ...data, handoff_status: serverStatus });
    }

    return data;
  } catch (error) {
    console.error('Error checking handoff status:', error);
    return null;
  }
}

/**
 * Sync handoff state from server response
 */
async function syncHandoffState(widget, data) {
  const { handoff_status, admin_name, is_human_controlled } = data;

  console.log('[Handoff] Syncing state:', { handoff_status, admin_name, is_human_controlled });

  if (handoff_status === 'human' && is_human_controlled) {
    console.log('[Handoff] Switching to HUMAN mode');
    widget.handoffMode = HANDOFF_MODE.HUMAN;
    widget.adminName = admin_name;
    widget.conversationId = data.conversation_id || widget.getCurrentActiveConversationId();

    // Connect to WebSocket if not already connected
    if (!widget.pusherClient) {
      await connectPusher(widget);
    }

    updateHandoffUI(widget, 'human');
    console.log('[Handoff] UI updated to human mode');
  } else if (handoff_status === 'pending') {
    console.log('[Handoff] Switching to PENDING mode');
    widget.handoffMode = HANDOFF_MODE.PENDING;
    widget.conversationId = data.conversation_id || widget.getCurrentActiveConversationId();

    // Connect to WebSocket to listen for handoff acceptance
    if (!widget.pusherClient) {
      await connectPusher(widget);
    }

    updateHandoffUI(widget, 'pending');
    console.log('[Handoff] UI updated to pending mode');
  } else {
    console.log('[Handoff] Switching to AI mode');
    // AI mode
    widget.handoffMode = HANDOFF_MODE.AI;
    widget.adminName = null;
    updateHandoffUI(widget, 'ai');
    console.log('[Handoff] UI updated to AI mode');
  }
}

/**
 * Subscribe to WebSocket for the current conversation.
 * This allows the widget to receive admin takeover events.
 * Called when a conversation exists on the server.
 */
export async function subscribeToConversation(widget) {
  const conversationId = widget.getCurrentActiveConversationId();
  if (!conversationId) return;

  widget.conversationId = conversationId;

  // Check current handoff status from server (in case page was reloaded during handoff)
  await checkHandoffStatus(widget);

  // Connect WebSocket if not already connected
  await connectPusher(widget);

  // Subscribe to channel
  if (widget.pusherClient) {
    if (widget.pusherClient.connection.state === 'connected') {
      subscribeToChannel(widget);
    } else {
      // Wait for connection, then subscribe
      const onConnected = () => {
        subscribeToChannel(widget);
        widget.pusherClient.connection.unbind('connected', onConnected);
      };
      widget.pusherClient.connection.bind('connected', onConnected);
    }
  }
}

/**
 * Request human assistance
 * @deprecated Admin initiates handoff via "Take Over" - manual request no longer supported
 */
export async function requestHumanAssistance(_widget) {
  return null;
}

/**
 * Switch to pending mode (waiting for agent)
 */
export async function switchToPendingMode(widget, serverMessage) {
  widget.handoffMode = HANDOFF_MODE.PENDING;

  // Show notification message
  const notificationMessage = serverMessage || t('handoff.request_sent');
  await widget.addMessage(CSS_CLASSES.MESSAGE_SYSTEM, notificationMessage, false);

  // Connect to WebSocket to listen for handoff event
  await connectPusher(widget);

  // Update UI to indicate pending mode
  updateHandoffUI(widget, 'pending');

  // Emit event for external listeners
  emitHandoffEvent(widget, { mode: HANDOFF_MODE.PENDING, status: 'pending' });
}

/**
 * Handle handoff event from SSE or WebSocket
 */
export async function handleHandoffEvent(widget, data) {
  const { status, admin_name, admin_id, message } = data;
  widget.conversationId = data.conversation_id || widget.getCurrentActiveConversationId();

  switch (status) {
    case HANDOFF_STATUS.PENDING:
      if (widget.handoffMode !== HANDOFF_MODE.PENDING) {
        await switchToPendingMode(widget, message);
      }
      break;

    case HANDOFF_STATUS.HUMAN:
      widget.adminName = admin_name;
      await switchToHumanMode(widget, admin_name, admin_id, message);
      break;

    case HANDOFF_STATUS.RETURNED:
    case HANDOFF_STATUS.AI:
      widget.adminName = null;
      await switchToAIMode(widget, message);
      break;
  }

  // Emit event for external listeners
  emitHandoffEvent(widget, { mode: widget.handoffMode, ...data });
}

/**
 * Emit handoff event to external listeners.
 *
 * Uses the widget's closure-private `_emit` hook (wired in by main.js)
 * rather than reaching into `window.chatvia._eventListeners`. The old path
 * leaked the listener registry on the public namespace so any third-party
 * script on the embedding page could snoop on handoff/takeover events.
 */
function emitHandoffEvent(widget, data) {
  widget._emit?.('handoff', data);
}

/**
 * Switch to human agent mode (WebSocket)
 */
export async function switchToHumanMode(widget, adminName, adminId, serverMessage) {
  widget.handoffMode = HANDOFF_MODE.HUMAN;
  widget.adminName = adminName;

  // Show notification message - use server message if provided
  const notificationMessage =
    serverMessage ||
    (adminName
      ? t('handoff.connected_with', { name: adminName })
      : t('handoff.connected_with_agent'));
  await widget.addMessage(CSS_CLASSES.MESSAGE_SYSTEM, notificationMessage, false);

  // Connect Pusher if not already connected (might be connected from pending state)
  if (!widget.pusherClient) {
    await connectPusher(widget);
  }

  // Update UI to indicate human mode
  updateHandoffUI(widget, 'human');

  // Disable menu options during live chat
  if (widget._updateMenuForHandoffState) {
    widget._updateMenuForHandoffState();
  }

  // Re-enable input for human chat
  if (widget.chatInput) widget.chatInput.disabled = false;
  if (widget.sendButton) widget.sendButton.disabled = false;

  // Scroll to bottom to show the notification
  widget.scrollToBottom();

  // Mark any existing messages as read if widget is open
  if (widget.isOpen) {
    markMessagesAsRead(widget);
  }

  // Emit event
  emitHandoffEvent(widget, { mode: HANDOFF_MODE.HUMAN, status: 'human', admin_name: adminName });
}

/**
 * Switch back to AI mode (SSE)
 */
export async function switchToAIMode(widget, serverMessage) {
  widget.handoffMode = HANDOFF_MODE.AI;
  widget.adminName = null;

  // Keep Pusher connected to listen for future takeovers
  // Don't disconnect - we want to always receive admin takeover events

  // Re-initialize SSE for AI mode streaming
  if (widget.setupSSE) {
    widget.setupSSE();
  }

  // Show notification message - use server message if provided
  const notificationMessage = serverMessage || t('handoff.back_with_ai');
  await widget.addMessage(CSS_CLASSES.MESSAGE_SYSTEM, notificationMessage, false);

  // Update UI to indicate AI mode
  updateHandoffUI(widget, 'ai');

  // Re-enable menu options
  if (widget._updateMenuForHandoffState) {
    widget._updateMenuForHandoffState();
  }

  // Re-enable input
  if (widget.chatInput) widget.chatInput.disabled = false;

  // Scroll to bottom to show the notification
  widget.scrollToBottom();
  if (widget.sendButton) widget.sendButton.disabled = false;

  // Emit event
  emitHandoffEvent(widget, { mode: HANDOFF_MODE.AI, status: 'ai' });
}

/**
 * User-initiated end of live chat
 */
export async function endLiveChat(widget) {
  if (widget.handoffMode !== HANDOFF_MODE.HUMAN) return;

  try {
    const conversationId = widget.conversationId || widget.getCurrentActiveConversationId();

    // Notify backend that user ended the chat
    const response = await api({
      endpoint: 'handoff/end',
      method: 'POST',
      headers: handoffHeaders(widget),
      body: JSON.stringify({
        conversation_id: conversationId
      })
    });

    if (!response.ok) {
      console.error('Failed to end live chat:', response.status);
    }

    // Switch back to AI mode
    await switchToAIMode(widget, t('handoff.ended_by_you'));
  } catch (error) {
    console.error('Error ending live chat:', error);
    // Still switch to AI mode even if the request failed
    await switchToAIMode(widget, t('handoff.connection_ended'));
  }
}

/**
 * Connect to Pusher/Reverb WebSocket (connection only, no channel subscription)
 * Call subscribeToConversation() separately to subscribe to a channel
 *
 * Pusher is statically imported (bundled with the widget) rather than loaded
 * from js.pusher.com — keeps the supply chain entirely under our own origin,
 * no CDN-compromise / MITM blast radius for embedding customers.
 */
export async function connectPusher(widget) {
  // Don't reconnect if already connected
  if (widget.pusherClient && widget.pusherClient.connection.state === 'connected') {
    return;
  }

  try {
    const isDev = typeof __DEV__ !== 'undefined' ? __DEV__ : false;

    // Embed-tag overrides (self-hosted installs) win over the build values.
    const { key: appKey, host: wsHost, port: wsPort, scheme, forceTLS } = getReverbConfig();

    console.log('[Handoff] WebSocket config:', {
      appKey: appKey ? '***' : 'MISSING',
      wsHost,
      wsPort,
      scheme,
      isDev
    });

    // No WebSocket config = no live chat capability
    if (!appKey || !wsHost) {
      console.warn('[Handoff] WebSocket config missing - live chat disabled.');
      return;
    }

    const apiUrl = getApiUrl();

    // Create Pusher client if not exists
    if (!widget.pusherClient) {
      console.log('[Handoff] Creating Pusher client...');

      // Enable Pusher logging in dev mode
      if (isDev) {
        Pusher.logToConsole = true;
      }

      widget.pusherClient = new Pusher(appKey, {
        wsHost: wsHost,
        wsPort: wsPort,
        wssPort: wsPort,
        forceTLS: forceTLS,
        enabledTransports: ['ws', 'wss'],
        enableStats: false,
        cluster: 'mt1',
        authEndpoint: `${apiUrl}/broadcasting/auth`,
        auth: {
          headers: {
            'X-SESSION-ID': widget.sessionId,
            'X-AGENT-ID': widget.agentId,
            ...authHeaders(widget.agentId)
          }
        }
      });

      // Handle connection state changes
      widget.pusherClient.connection.bind('state_change', states => {
        console.log('[Handoff] Connection state:', states.previous, '->', states.current);
      });

      widget.pusherClient.connection.bind('connected', () => {
        console.log('[Handoff] WebSocket connected!');
      });

      widget.pusherClient.connection.bind('error', err => {
        console.error('[Handoff] WebSocket error:', err);
      });
    }
  } catch (error) {
    console.error('[Handoff] Failed to connect WebSocket:', error);
  }
}

/**
 * Subscribe to the current conversation's presence channel
 */
function subscribeToChannel(widget) {
  const conversationId = widget.conversationId || widget.getCurrentActiveConversationId();
  if (!conversationId || !widget.pusherClient) {
    console.warn('[Handoff] Cannot subscribe - no conversationId or pusherClient');
    return;
  }

  const channelName = `presence-chat.${conversationId}`;

  // Unsubscribe from previous channel if different
  if (widget.pusherChannel && widget.pusherChannel.name !== channelName) {
    console.log('[Handoff] Unsubscribing from previous channel:', widget.pusherChannel.name);
    widget.pusherClient.unsubscribe(widget.pusherChannel.name);
    widget.pusherChannel = null;
  }

  // Don't resubscribe if already on the same channel
  if (widget.pusherChannel && widget.pusherChannel.name === channelName) {
    console.log('[Handoff] Already subscribed to channel:', channelName);
    return;
  }

  console.log('[Handoff] Subscribing to channel:', channelName);
  widget.pusherChannel = widget.pusherClient.subscribe(channelName);

  // Bind channel events
  widget.pusherChannel.bind('pusher:subscription_succeeded', async members => {
    console.log('[Handoff] Channel subscription succeeded. Members:', members);

    // Immediately check handoff status to sync UI state
    // This handles cases where takeover happened before we subscribed
    await checkHandoffStatus(widget);
  });
  widget.pusherChannel.bind('pusher:subscription_error', error => {
    console.error('[Handoff] Channel subscription error:', error);
  });

  // Listen for messages from admin (Reverb sends WITHOUT leading dot)
  widget.pusherChannel.bind('message', data => {
    console.log('[Handoff] Received message event:', data);
    handleWebSocketMessage(widget, data);
  });

  // Listen for typing indicators (server broadcast)
  widget.pusherChannel.bind('typing', data => {
    console.log('[Handoff] Received typing event:', data);
    handleTypingEvent(widget, data);
  });

  // Also listen for client-side typing (whispers from admin)
  widget.pusherChannel.bind('client-typing', data => {
    console.log('[Handoff] Received client-typing whisper:', data);
    handleTypingEvent(widget, data);
  });

  // Listen for handoff status changes (Reverb sends WITHOUT leading dot)
  widget.pusherChannel.bind('handoff', data => {
    console.log('[Handoff] ⚡ Received handoff event via WebSocket:', data);
    handleHandoffEvent(widget, data);
  });

  console.log('[Handoff] ✅ All event bindings set up for channel:', channelName);
}

/**
 * Disconnect from Pusher completely
 */
export function disconnectPusher(widget) {
  if (widget.pusherChannel) {
    widget.pusherChannel.unbind_all();
    if (widget.pusherClient) {
      widget.pusherClient.unsubscribe(widget.pusherChannel.name);
    }
    widget.pusherChannel = null;
  }

  if (widget.pusherClient) {
    widget.pusherClient.disconnect();
    widget.pusherClient = null;
  }

  // Clear typing state
  if (widget._typingTimeout) {
    clearTimeout(widget._typingTimeout);
    widget._typingTimeout = null;
  }
  if (widget._typingInterval) {
    clearInterval(widget._typingInterval);
    widget._typingInterval = null;
  }
  widget._isTypingSent = false;
  widget._adminTyping = false;
}

/**
 * Handle incoming WebSocket message from admin
 */
async function handleWebSocketMessage(widget, data) {
  const { content, sender_name, sender_id, role, id, timestamp } = data;

  // Only show messages from admin/agent
  const isFromAgent = role === 'agent' || role === 'admin' || role === 'assistant';
  const isOurOwnMessage = sender_id === widget.sessionId || role === 'user';

  if (isOurOwnMessage && !isFromAgent) return;
  if (!content || content.trim() === '') return;

  // Track if we need to open the widget
  const wasWidgetClosed = !widget.isOpen;

  // Open widget for incoming human message
  if (wasWidgetClosed && isFromAgent) {
    widget.toggleChat(true);
  }

  hideAdminTyping(widget);

  // Add message (no feedback for human messages, include timestamp)
  await widget.addMessage(CSS_CLASSES.MESSAGE_AGENT, content, true, {
    messageId: id,
    senderName: sender_name,
    isHuman: true,
    hideActions: true,
    timestamp: timestamp || new Date().toISOString()
  });

  widget.scrollToBottom();
  setTimeout(() => widget.scrollToBottom(), 100);
  setTimeout(() => widget.scrollToBottom(), 300);

  // Mark messages as read - always call in human mode when receiving messages
  // Use a small delay if widget just opened to ensure it's fully open
  if (wasWidgetClosed) {
    setTimeout(() => markMessagesAsRead(widget), 350);
  } else {
    markMessagesAsRead(widget);
  }
}

/**
 * Handle typing indicator event
 */
function handleTypingEvent(widget, data) {
  const { is_typing, typer_type } = data;

  // Only show typing indicator for admin/agent
  if (typer_type === 'user') {
    return;
  }

  if (is_typing) {
    showAdminTyping(widget);
    // Scroll to bottom when typing indicator appears
    widget.scrollToBottom();
  } else {
    hideAdminTyping(widget);
  }
}

/**
 * Show admin typing indicator - "is typing..." for human mode.
 *
 * The typing text contains the admin display name, which is operator-supplied
 * and therefore untrusted. Build the DOM with createElement + textContent so
 * the name can never reach the HTML parser; using innerHTML here would be an
 * XSS sink the moment an admin sets their name to "<img onerror=...>".
 */
function showAdminTyping(widget) {
  if (widget._adminTyping) return;
  widget._adminTyping = true;

  // Remove any existing typing indicator
  hideAdminTypingElement(widget);

  const typingIndicator = document.createElement('div');
  typingIndicator.className = 'chatvia-widget-human-typing';
  // Per-widget id avoids collisions when multiple widgets are embedded on the
  // same page. Read back via the same helper below.
  typingIndicator.dataset.role = 'human-typing-indicator';

  const typingText = widget.adminName
    ? t('handoff.agent_typing', { name: widget.adminName })
    : t('handoff.connecting');

  const textNode = document.createElement('span');
  textNode.className = 'chatvia-widget-typing-text';
  textNode.textContent = typingText;

  const dots = document.createElement('span');
  dots.className = 'chatvia-widget-typing-dots';
  for (let i = 0; i < 3; i++) {
    const dot = document.createElement('span');
    dot.className = 'chatvia-widget-typing-dot';
    dots.appendChild(dot);
  }

  typingIndicator.appendChild(textNode);
  typingIndicator.appendChild(dots);

  if (widget.messagesContainer) {
    widget.messagesContainer.appendChild(typingIndicator);
    widget.scrollToBottom();
  }
}

/**
 * Hide admin typing indicator
 */
function hideAdminTyping(widget) {
  if (!widget._adminTyping) return;
  widget._adminTyping = false;

  hideAdminTypingElement(widget);
}

/**
 * Remove the typing indicator element from DOM. Scoped to the widget's
 * messages container so multiple widgets on the same page don't clobber
 * each other.
 */
function hideAdminTypingElement(widget) {
  if (!widget.messagesContainer) return;
  const existingIndicator = widget.messagesContainer.querySelector(
    '[data-role="human-typing-indicator"]'
  );
  if (existingIndicator) {
    existingIndicator.remove();
  }
}

/**
 * Send typing indicator via WebSocket (client event / whisper)
 * This goes directly to other clients without hitting the server
 */
export function sendTypingIndicator(widget, isTyping) {
  if (widget.handoffMode !== HANDOFF_MODE.HUMAN) {
    return;
  }

  // Clear existing timeout
  if (widget._typingTimeout) {
    clearTimeout(widget._typingTimeout);
    widget._typingTimeout = null;
  }

  // Clear existing interval
  if (widget._typingInterval) {
    clearInterval(widget._typingInterval);
    widget._typingInterval = null;
  }

  if (isTyping) {
    // Send typing immediately
    whisperTyping(widget, true);
    widget._isTypingSent = true;

    // Keep sending typing every 2 seconds while user is active
    widget._typingInterval = setInterval(() => {
      if (widget._isTypingSent) {
        whisperTyping(widget, true);
      }
    }, 2000);

    // Auto-stop after 3 seconds of no keystroke
    widget._typingTimeout = setTimeout(() => {
      widget._isTypingSent = false;
      whisperTyping(widget, false);
      if (widget._typingInterval) {
        clearInterval(widget._typingInterval);
        widget._typingInterval = null;
      }
      widget._typingTimeout = null;
    }, 3000);
  } else {
    // Explicitly stopped typing
    if (widget._isTypingSent) {
      widget._isTypingSent = false;
      whisperTyping(widget, false);
    }
  }
}

/**
 * Send typing via WebSocket client event (whisper)
 */
function whisperTyping(widget, isTyping) {
  if (!widget.pusherChannel) return;

  const success = widget.pusherChannel.trigger('client-typing', {
    is_typing: isTyping,
    typer_type: 'user',
    typer_name: 'Visitor',
    timestamp: new Date().toISOString()
  });

  if (!success) {
    postTypingStatus(widget, isTyping);
  }
}

/**
 * POST typing status to server
 */
async function postTypingStatus(widget, isTyping) {
  try {
    await api({
      endpoint: 'typing',
      method: 'POST',
      headers: handoffHeaders(widget),
      body: JSON.stringify({
        conversation_id: widget.conversationId || widget.getCurrentActiveConversationId(),
        is_typing: isTyping
      })
    });
  } catch {
    // Silent fail for typing indicator
  }
}

/**
 * Send message in human mode (via HTTP POST, not SSE)
 */
export async function sendMessageInHumanMode(widget, text) {
  sendTypingIndicator(widget, false);

  try {
    const response = await api({
      endpoint: 'stream',
      method: 'POST',
      headers: handoffHeaders(widget, {
        'X-CONVERSATION-ID': widget.getCurrentActiveConversationId()
      }),
      body: JSON.stringify({
        agent_id: widget.agentId,
        session_id: widget.sessionId,
        conversation_id: widget.getCurrentActiveConversationId(),
        type: 'message',
        content: text,
        url: window.location.href,
        referrer: document.referrer || ''
      })
    });

    if (!response.ok) {
      throw new Error(`HTTP error! Status: ${response.status}`);
    }

    return true;
  } catch (error) {
    console.error('Failed to send message in human mode:', error);
    return false;
  }
}

/**
 * Update UI to reflect handoff mode
 */
function updateHandoffUI(widget, mode) {
  console.log('[Handoff] updateHandoffUI called with mode:', mode);
  const chatWindow = widget.chatWindow;
  if (!chatWindow) {
    console.warn('[Handoff] No chatWindow found, cannot update UI');
    return;
  }

  // Remove all mode classes
  chatWindow.classList.remove(CSS_CLASSES.HUMAN_MODE, 'chatvia-widget-pending-mode');

  // Get header title element
  const headerTitle = chatWindow.querySelector('.chatvia-widget-header-title');
  const originalTitle = widget.settings?.title || 'Chat';

  // EU AI Act Art. 50 disclosure — keep the persistent header subtitle in sync
  // with who is actually answering: AI in ai/pending, the human in human mode.
  const headerSubtitle = chatWindow.querySelector('.chatvia-widget-header-subtitle');
  if (headerSubtitle) {
    headerSubtitle.textContent =
      mode === 'human' ? t('disclosure.human_agent') : t('disclosure.ai_assistant');
  }

  console.log('[Handoff] Current classes before update:', chatWindow.className);

  switch (mode) {
    case 'pending':
      chatWindow.classList.add('chatvia-widget-pending-mode');
      if (widget.chatInput) {
        widget.chatInput.placeholder = t('handoff.waiting_for_agent');
      }
      if (headerTitle) {
        headerTitle.textContent = originalTitle;
      }
      removeEndChatButton(widget);
      break;

    case 'human':
      chatWindow.classList.add(CSS_CLASSES.HUMAN_MODE);
      console.log('[Handoff] Added human mode class. Classes now:', chatWindow.className);
      if (widget.chatInput) {
        const agentName = widget.adminName || t('handoff.default_agent_name');
        widget.chatInput.placeholder = t('handoff.message_agent', { name: agentName });
      }
      if (headerTitle) {
        headerTitle.textContent = t('handoff.live_title', { title: originalTitle });
      }
      addEndChatButton(widget);
      hideMenuButton(widget);
      break;

    case 'ai':
    default:
      if (widget.chatInput) {
        // Mirror applyInputPlaceholder (config.js), which keys off
        // composer_placeholder — not the stale `input_placeholder`.
        widget.chatInput.placeholder =
          widget.settings?.composer_placeholder || t('widget.type_message');
      }
      if (headerTitle) {
        headerTitle.textContent = originalTitle;
      }
      removeEndChatButton(widget);
      showMenuButton(widget);
      break;
  }

  // Update "Talk to Human" button visibility
  updateTalkToHumanButton(widget, mode);
}

// SVG path for the "end call" icon, kept as data because the rest of the file
// builds icons inline. If this grows, move to ui.js alongside createIcon.
const END_CHAT_ICON_PATH =
  'M10.68 13.31a16 16 0 0 0 3.41 2.6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7 2 2 0 0 1 1.72 2v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.42 19.42 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91';

/**
 * Build the "End Live Chat" SVG icon as DOM nodes (no innerHTML).
 */
function buildEndChatIcon() {
  const SVG_NS = 'http://www.w3.org/2000/svg';
  const svg = document.createElementNS(SVG_NS, 'svg');
  svg.setAttribute('width', '14');
  svg.setAttribute('height', '14');
  svg.setAttribute('viewBox', '0 0 24 24');
  svg.setAttribute('fill', 'none');
  svg.setAttribute('stroke', 'currentColor');
  svg.setAttribute('stroke-width', '2');
  svg.setAttribute('stroke-linecap', 'round');
  svg.setAttribute('stroke-linejoin', 'round');

  const path = document.createElementNS(SVG_NS, 'path');
  path.setAttribute('d', END_CHAT_ICON_PATH);
  svg.appendChild(path);

  const line = document.createElementNS(SVG_NS, 'line');
  line.setAttribute('x1', '23');
  line.setAttribute('y1', '1');
  line.setAttribute('x2', '1');
  line.setAttribute('y2', '23');
  svg.appendChild(line);

  return svg;
}

/**
 * Add the "End Chat" button to the widget header. Scoped to the widget's
 * container so multiple widgets on one page don't collide.
 */
function addEndChatButton(widget) {
  removeEndChatButton(widget);

  const header = widget.chatWindow?.querySelector('.chatvia-widget-header');
  if (!header) return;

  const endChatBtn = document.createElement('button');
  endChatBtn.className = 'chatvia-widget-end-chat-btn';
  endChatBtn.dataset.role = 'end-chat-button';
  endChatBtn.title = t('handoff.end_live_chat_tooltip');

  endChatBtn.appendChild(buildEndChatIcon());
  const label = document.createElement('span');
  label.textContent = t('handoff.end_chat');
  endChatBtn.appendChild(label);

  endChatBtn.addEventListener('click', e => {
    e.preventDefault();
    e.stopPropagation();
    widget._showConfirmationDialog(
      t('handoff.end_live_chat_title'),
      t('handoff.end_live_chat_message'),
      t('handoff.end_chat'),
      () => endLiveChat(widget)
    );
  });

  const closeBtn = header.querySelector('.chatvia-widget-close-btn');
  if (closeBtn) {
    header.insertBefore(endChatBtn, closeBtn);
  } else {
    header.appendChild(endChatBtn);
  }
}

/**
 * Remove the "End Chat" button. Scoped to the widget's header so multiple
 * widgets don't reach into each other's DOM.
 */
function removeEndChatButton(widget) {
  const header = widget.chatWindow?.querySelector('.chatvia-widget-header');
  if (!header) return;
  const existingBtn = header.querySelector('[data-role="end-chat-button"]');
  if (existingBtn) {
    existingBtn.remove();
  }
}

/**
 * Hide the menu button (three dots) during human mode
 */
function hideMenuButton(widget) {
  const menuBtn = widget.chatWindow?.querySelector('.chatvia-widget-menu-btn');
  if (menuBtn) {
    menuBtn.style.display = 'none';
  }
}

/**
 * Show the menu button when returning to AI mode
 */
function showMenuButton(widget) {
  const menuBtn = widget.chatWindow?.querySelector('.chatvia-widget-menu-btn');
  if (menuBtn) {
    menuBtn.style.display = '';
  }
}

/**
 * Update the "Talk to Human" button visibility
 */
function updateTalkToHumanButton(widget, mode) {
  const talkToHumanBtn = widget.chatWindow?.querySelector('.chatvia-widget-talk-to-human');
  if (talkToHumanBtn) {
    // Hide button when not in AI mode
    talkToHumanBtn.style.display = mode === 'ai' ? '' : 'none';
  }
}

/**
 * Check if widget is in human mode
 */
export function isInHumanMode(widget) {
  return widget.handoffMode === HANDOFF_MODE.HUMAN;
}

/**
 * Check if widget is in pending mode
 */
export function isInPendingMode(widget) {
  return widget.handoffMode === HANDOFF_MODE.PENDING;
}

/**
 * Get current handoff mode
 */
export function getHandoffMode(widget) {
  return widget.handoffMode;
}

/**
 * Get admin name (when in human mode)
 */
export function getAdminName(widget) {
  return widget.adminName;
}

/**
 * Create "Talk to Human" button element
 */
export function createTalkToHumanButton(widget, onClick) {
  const button = document.createElement('button');
  button.className = 'chatvia-widget-talk-to-human';
  button.innerHTML = `
    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
      <circle cx="9" cy="7" r="4"></circle>
      <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
      <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
    </svg>
    <span></span>
  `;
  // Untrusted? No — the label is our own i18n string. Set as textContent on the
  // span (not via the template) to keep the SVG markup but avoid interpolating
  // into innerHTML.
  button.querySelector('span').textContent = t('handoff.talk_to_human');
  button.title = t('handoff.request_human');
  button.type = 'button';

  button.addEventListener('click', async e => {
    e.preventDefault();
    if (onClick) {
      await onClick();
    } else {
      await requestHumanAssistance(widget);
    }
  });

  return button;
}

/**
 * Cleanup handoff state (when switching conversations)
 * Note: We keep the Pusher client connected but unsubscribe from the current channel
 */
export function cleanupHandoff(widget) {
  // Unsubscribe from current channel but keep connection alive
  if (widget.pusherChannel && widget.pusherClient) {
    widget.pusherClient.unsubscribe(widget.pusherChannel.name);
    widget.pusherChannel = null;
  }

  // Reset handoff state
  widget.handoffMode = HANDOFF_MODE.AI;
  widget.conversationId = null;
  widget.adminName = null;
  widget._adminTyping = false;

  // Clear typing timeout and interval
  if (widget._typingTimeout) {
    clearTimeout(widget._typingTimeout);
    widget._typingTimeout = null;
  }
  if (widget._typingInterval) {
    clearInterval(widget._typingInterval);
    widget._typingInterval = null;
  }
  widget._isTypingSent = false;
}

/**
 * Initialize handoff state check when conversation loads
 * Call this when a conversation is loaded to sync handoff state
 */
export async function checkAndSyncHandoffState(widget) {
  const status = await checkHandoffStatus(widget);
  return status;
}
