/**
 * Conversation Management Module
 * Handles conversation CRUD operations, history, and navigation
 */

import { CSS_CLASSES } from '../utils.js';
import { createElement, createIcon } from '../ui.js';
import { cleanupHandoff, subscribeToConversation } from './handoff.js';
import { mapRoleToMessageType } from './messages.js';
import { t } from '../i18n.js';

/**
 * Initialize conversations from storage
 */
export async function initConversations(widget) {
  console.log('Initializing conversations...');
  try {
    const { conversations, activeConversationId } = await widget.storageManager.loadConversations();
    console.log(
      'Loaded conversations from API:',
      conversations,
      'Active ID:',
      activeConversationId
    );

    widget.conversations = conversations || [];

    // Use conversation ID from session init if available, otherwise use stored activeConversationId
    if (widget.initialConversationId) {
      console.log('Using conversation ID from session init:', widget.initialConversationId);
      widget.activeConversationId = widget.initialConversationId;
      widget.storageManager.setActiveConversationId(widget.initialConversationId);
    } else {
      widget.activeConversationId = activeConversationId || null;
    }

    // Check if the stored active conversation ID actually exists in the conversations list
    if (widget.activeConversationId && widget.conversations.length > 0) {
      const activeConversationExists = widget.conversations.find(
        c => c.id === widget.activeConversationId
      );
      if (!activeConversationExists) {
        console.log('Active conversation no longer exists on server, will create new one');
        widget.activeConversationId = null;
      }
    }

    if (widget.conversations.length === 0 || !widget.activeConversationId) {
      console.log('No valid conversation found, creating a new one...');
      await createNewConversation(widget);
    }
    console.log(`Active conversation ID: ${widget.activeConversationId}`);
  } catch (error) {
    console.error('Error initializing conversations:', error);
    await widget.disabledChat(t('errors.conversations_load_failed'));
    throw error;
  }
}

/**
 * Create a new conversation
 */
export async function createNewConversation(widget) {
  console.log('Requesting new conversation from API...');
  try {
    // Clean up any existing handoff state from previous conversation
    cleanupHandoff(widget);

    const newConversation = await widget.storageManager.createNewConversation();
    if (newConversation) {
      widget.conversations.unshift(newConversation);
      widget.activeConversationId = newConversation.id;
      widget.messageHistory = [];
      console.log('New conversation created successfully:', newConversation);

      // Re-setup SSE with the new conversation ID
      widget.setupSSE();

      // Subscribe to WebSocket for handoff events
      await subscribeToConversation(widget);

      await widget.displayHistoryMessages();
      widget._updateConversationActionButtons();
      widget.closeMenuDropdown();
      widget.scrollToBottom();

      return newConversation;
    } else {
      console.error('API did not return a new conversation object.');
      await widget.disabledChat(t('errors.new_chat_failed'));
      return null;
    }
  } catch (error) {
    console.error('Failed to create new conversation:', error);
    await widget.disabledChat(t('errors.new_chat_error'));
    return null;
  }
}

/**
 * Get the active conversation
 */
export function getActiveConversation(widget) {
  const storedActiveConversationId = widget.storageManager
    ? widget.storageManager.getActiveConversationId()
    : widget.activeConversationId;
  return widget.conversations.find(c => c.id === storedActiveConversationId);
}

/**
 * Get current active conversation ID
 */
export function getCurrentActiveConversationId(widget) {
  return widget.storageManager
    ? widget.storageManager.getActiveConversationId()
    : widget.activeConversationId;
}

/**
 * Load the active conversation's messages
 */
export async function loadActiveConversation(widget) {
  widget.clearMessages();
  if (widget.inputContainer) widget.inputContainer.style.display = 'flex';
  widget.showFetchSpinner();

  try {
    const activeConversation = getActiveConversation(widget);

    if (!activeConversation) {
      console.warn('loadActiveConversation called with no active conversation. Re-initializing.');
      try {
        await initConversations(widget);
        const potentiallyNewActiveConversation = getActiveConversation(widget);
        if (!potentiallyNewActiveConversation) {
          console.error('Failed to establish an active conversation even after re-init.');
          await widget.disabledChat(t('errors.load_chat_failed'));
          return;
        }
        const messages = await widget.storageManager.getMessages(
          potentiallyNewActiveConversation.id
        );
        widget.messageHistory = messages || [];
        potentiallyNewActiveConversation.messages = widget.messageHistory;
        console.log(
          `Loaded ${widget.messageHistory.length} messages for conversation ${potentiallyNewActiveConversation.id}`
        );
        await widget.displayHistoryMessages();
        widget._updateConversationActionButtons();
        widget.scrollToBottom();
        return;
      } catch (error) {
        console.error('Error during re-initialization in loadActiveConversation:', error);
        await widget.disabledChat(t('errors.load_chat_failed'));
        return;
      }
    }

    console.log(`Loading messages for active conversation: ${activeConversation.id}`);

    try {
      const messages = await widget.storageManager.getMessages(activeConversation.id);
      widget.messageHistory = messages || [];

      activeConversation.messages = widget.messageHistory;
      console.log(
        `Loaded ${widget.messageHistory.length} messages for conversation ${activeConversation.id}`
      );
      await widget.displayHistoryMessages();

      // Check if there are any user messages in the conversation
      const hasUserMessages = widget.messageHistory.some(message => {
        const messageType = message.role ? mapRoleToMessageType(message.role) : message.type;
        return messageType === CSS_CLASSES.MESSAGE_USER;
      });

      if (hasUserMessages || activeConversation.hasUserInteraction) {
        widget.hideStarters();
      } else if (
        !widget.messageHistory.length ||
        (widget.messageHistory.length === 1 &&
          (widget.messageHistory[0].role
            ? mapRoleToMessageType(widget.messageHistory[0].role) === CSS_CLASSES.MESSAGE_AGENT
            : widget.messageHistory[0].type === CSS_CLASSES.MESSAGE_AGENT))
      ) {
        widget.showStarters();
      } else {
        widget.hideStarters();
      }

      widget._updateConversationActionButtons();

      // Clean up previous conversation's handoff state and reset to AI mode
      cleanupHandoff(widget);

      // Re-setup SSE with the new conversation ID (default mode)
      if (widget.setupSSE) {
        widget.setupSSE();
      }

      // Always subscribe to WebSocket for this conversation
      // This ensures we receive handoff events even for conversations without messages yet
      await subscribeToConversation(widget);
    } catch (error) {
      console.error(`Error loading messages for conversation ${activeConversation.id}:`, error);
      await widget.addMessage(CSS_CLASSES.MESSAGE_SYSTEM, t('errors.messages_load_failed'), false);
    }
    widget.scrollToBottom();
  } finally {
    widget.hideFetchSpinner();
  }
}

/**
 * Start a new conversation
 */
export async function startNewConversation(widget) {
  const activeConversation = getActiveConversation(widget);

  // If current conversation is already empty, just stay on it
  if (
    activeConversation &&
    Array.isArray(activeConversation.messages) &&
    activeConversation.messages.length === 0
  ) {
    widget.closeMenuDropdown();
    widget.chatInput?.focus();
    return;
  }

  console.log('Starting a new conversation...');
  widget.closeMenuDropdown();
  widget.clearMessages();
  widget.showFetchSpinner();

  try {
    const newConversation = await createNewConversation(widget);

    if (newConversation) {
      if (widget.inputContainer) widget.inputContainer.style.display = 'flex';
      widget.showStarters();
      setTimeout(() => widget.chatInput?.focus(), 300);
    } else {
      throw new Error('Failed to create new conversation');
    }
  } catch (error) {
    console.error('Error starting new conversation:', error);
    await widget.addMessage(CSS_CLASSES.MESSAGE_SYSTEM, t('errors.start_chat_failed'), false);
  } finally {
    widget.hideFetchSpinner();
  }
}

/**
 * Load chat history view
 */
export async function loadChatHistory(widget) {
  widget.closeMenuDropdown();
  widget.clearMessages();
  widget.showFetchSpinner();
  if (widget.inputContainer) widget.inputContainer.style.display = 'none';

  try {
    const { conversations } = await widget.storageManager.loadConversations();
    widget.conversations = conversations;
    widget.activeConversationId = widget.storageManager.getActiveConversationId();
    widget.hideStarters();

    const conversationsContainer = createConversationsContainer(widget, conversations);
    widget.messagesContainer?.appendChild(conversationsContainer);
  } catch (error) {
    console.error('Error loading chat history:', error);
    await widget.addMessage(CSS_CLASSES.MESSAGE_SYSTEM, t('errors.history_load_failed'), false);
    if (widget.inputContainer) widget.inputContainer.style.display = 'flex';
  } finally {
    widget.hideFetchSpinner();
  }
}

/**
 * Delete current conversation
 */
export async function deleteCurrentConversation(widget, conversationId) {
  if (!conversationId) {
    console.error('Cannot delete conversation without an ID.');
    return;
  }
  console.log(`Attempting to delete conversation: ${conversationId}`);
  widget.showFetchSpinner();
  try {
    const success = await widget.storageManager.deleteConversation(conversationId);
    if (success) {
      await showDeleteSuccess(widget, conversationId);
    } else {
      console.error('Failed to delete conversation via API.');
    }
  } catch (error) {
    console.error('Error deleting conversation:', error);
  } finally {
    widget.hideFetchSpinner();
  }
}

/**
 * Delete all conversations
 */
export async function deleteAllConversations(widget) {
  console.log('Attempting to delete all conversations via API...');
  widget.showFetchSpinner();
  try {
    const success = await widget.storageManager.deleteAllConversations();

    if (success) {
      widget.conversations = [];
      widget.activeConversationId = null;
      widget.messageHistory = [];
      console.log('Starting a new conversation after deleting all...');
      await startNewConversation(widget);
    } else {
      console.error('Failed to delete all conversations via API.');
    }
  } catch (error) {
    console.error('Error deleting all conversations:', error);
  } finally {
    widget.hideFetchSpinner();
  }
}

/**
 * Show success after deleting a conversation
 */
export async function showDeleteSuccess(widget, deletedConversationId) {
  widget.conversations = widget.conversations.filter(c => c.id !== deletedConversationId);

  if (widget.conversations.length > 0) {
    const sortedConversations = [...widget.conversations].sort(
      (a, b) => (b.timestamp || 0) - (a.timestamp || 0)
    );
    const nextActiveConversationId = sortedConversations[0].id;
    console.log('Setting next active conversation to:', nextActiveConversationId);
    widget.storageManager.setActiveConversationId(nextActiveConversationId);
    widget.activeConversationId = nextActiveConversationId;

    widget.setupSSE();
    await loadActiveConversation(widget);
  } else {
    console.log('No conversations left after deletion. Creating a new one.');
    await startNewConversation(widget);
  }
}

/**
 * Confirm delete conversation dialog
 */
export function confirmDeleteConversation(widget) {
  if (widget.isStreaming) {
    console.log('Preventing delete conversation while streaming.');
    return;
  }

  const conversationIdToDelete = widget.storageManager.getActiveConversationId();
  const conversationToDelete = widget.conversations.find(c => c.id === conversationIdToDelete);
  if (!conversationToDelete) {
    console.log('No active chat to delete.');
    return;
  }
  widget.closeMenuDropdown();
  widget._showConfirmationDialog(
    t('confirm.delete_chat_title'),
    t('confirm.delete_chat_message'),
    t('confirm.delete_button'),
    () => deleteCurrentConversation(widget, conversationIdToDelete)
  );
}

/**
 * Confirm clear all conversations dialog
 */
export function confirmClearAllConversations(widget) {
  widget.closeMenuDropdown();
  widget._showConfirmationDialog(
    t('confirm.delete_all_title'),
    t('confirm.delete_all_message'),
    t('confirm.delete_all_button'),
    () => deleteAllConversations(widget)
  );
}

/**
 * Create conversations container for history view
 */
function createConversationsContainer(widget, conversations) {
  const container = createElement('div', {
    className: `${CSS_CLASSES.CONVERSATIONS_CONTAINER} ${CSS_CLASSES.CONVERSATIONS_LIST}`
  });

  if (!conversations || conversations.length === 0) {
    const noConversationsMsg = createElement('p', {
      textContent: t('conversations.no_history'),
      className: CSS_CLASSES.NO_CONVERSATIONS_MESSAGE
    });
    container.appendChild(noConversationsMsg);
  } else {
    const sortedConversations = [...conversations].sort(
      (a, b) => (b.timestamp || 0) - (a.timestamp || 0)
    );
    sortedConversations.forEach(conversation => {
      const conversationItem = createConversationItem(widget, conversation);
      container.appendChild(conversationItem);
    });
  }
  return container;
}

/**
 * Create a single conversation item
 */
function createConversationItem(widget, conversation) {
  const item = createElement('div', { className: CSS_CLASSES.CONVERSATION_ITEM });
  if (conversation.id === widget.activeConversationId) {
    item.classList.add(CSS_CLASSES.CONVERSATION_ITEM_ACTIVE);
  }

  const contentContainer = createElement('div', { className: CSS_CLASSES.CONVERSATION_CONTENT });

  const titleEl = createElement('div', { className: CSS_CLASSES.CONVERSATION_TITLE });
  titleEl.textContent =
    conversation.title ||
    getFirstUserMessage(widget, conversation) ||
    t('conversations.new_conversation');
  contentContainer.appendChild(titleEl);

  const subtitleEl = createElement('div', { className: CSS_CLASSES.CONVERSATION_SUBTITLE });
  // Fall back to the agent id so the list never shows another product's brand
  // when settings.title hasn't loaded yet.
  subtitleEl.textContent = widget.settings.title || widget.agentId;
  contentContainer.appendChild(subtitleEl);

  if (conversation.timestamp) {
    const timestampEl = createElement('span', {
      className: CSS_CLASSES.CONVERSATION_DATE,
      textContent: ` • ${formatTimestamp(conversation.timestamp)}`
    });
    subtitleEl.appendChild(timestampEl);
  }

  item.appendChild(contentContainer);

  const arrowIcon = createElement('div', { className: CSS_CLASSES.CONVERSATION_ARROW_ICON });
  arrowIcon.appendChild(createIcon('arrow-right'));
  item.appendChild(arrowIcon);

  item.addEventListener('click', async () => {
    widget.storageManager.setActiveConversationId(conversation.id);
    widget.activeConversationId = conversation.id;
    widget.setupSSE();
    await loadActiveConversation(widget);
  });

  return item;
}

/**
 * Get first user message from conversation for title
 */
function getFirstUserMessage(widget, conversation) {
  if (!conversation.messages || !Array.isArray(conversation.messages)) return null;

  const firstUserMessage = conversation.messages.find(msg => {
    const messageType = msg.role ? mapRoleToMessageType(msg.role) : msg.type;
    return messageType === CSS_CLASSES.MESSAGE_USER;
  });

  return firstUserMessage
    ? firstUserMessage.content.substring(0, 50) +
        (firstUserMessage.content.length > 50 ? '...' : '')
    : null;
}

/**
 * Format timestamp for display
 */
export function formatTimestamp(timestamp) {
  if (!timestamp) return t('conversations.unknown_date');

  const date = new Date(timestamp * 1000);
  const now = new Date();
  const isToday = date.toDateString() === now.toDateString();

  const yesterday = new Date(now);
  yesterday.setDate(yesterday.getDate() - 1);
  const isYesterday = date.toDateString() === yesterday.toDateString();

  if (isToday) {
    const time = date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    return t('conversations.today_at', { time });
  } else if (isYesterday) {
    const time = date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    return t('conversations.yesterday_at', { time });
  } else {
    return date.toLocaleDateString([], {
      month: 'short',
      day: 'numeric',
      year: date.getFullYear() !== now.getFullYear() ? 'numeric' : undefined
    });
  }
}

/**
 * Update conversation action buttons state
 */
export function updateConversationActionButtons(widget) {
  const activeConversation = getActiveConversation(widget);
  let hasUserMessages = false;
  if (activeConversation && Array.isArray(activeConversation.messages)) {
    hasUserMessages = activeConversation.messages.some(m => {
      const messageType = m.role ? mapRoleToMessageType(m.role) : m.type;
      return messageType === CSS_CLASSES.MESSAGE_USER;
    });
  }

  const deleteDisabled = !hasUserMessages;
  const newChatDisabled = !hasUserMessages;

  const onlyEmptyConversation =
    widget.conversations.length === 1 &&
    (!activeConversation ||
      !activeConversation.messages ||
      activeConversation.messages.length === 0);

  const setState = (el, disabled) => {
    if (!el) return;
    el.style.pointerEvents = disabled ? 'none' : 'auto';
    el.style.opacity = disabled ? '0.4' : '1';
    el.setAttribute('aria-disabled', disabled);
  };

  setState(widget.deleteConversationButton, deleteDisabled);
  setState(widget.newChatOption, newChatDisabled);
  setState(widget.historyOption, onlyEmptyConversation);
  setState(widget.clearCurrentOption, onlyEmptyConversation);
  setState(widget.clearAllOption, onlyEmptyConversation);
}
