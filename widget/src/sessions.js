import { PersistenceManager } from './persistence.js';
import api from './api.js';

export class SessionManager {
  constructor(agentId) {
    this.agentId = agentId;
    this.persistedSessionId = 'chat-widget-session-id';
    this.persistedConversationId = 'chat-widget-active-conversation-id'; // Existing key used by StorageManager

    this.persistenceManager = new PersistenceManager(agentId);
  }

  /**
   * Initialize session and conversation.
   * Always calls the backend to validate/create session and conversation.
   *
   * @returns {Promise<{sessionId: string, conversationId: string, isNewSession: boolean, isNewConversation: boolean}>}
   */
  async startSession() {
    try {
      const existingSessionId = this.getCurrentPersistedSessionId();
      const existingConversationId = this.getCurrentPersistedConversationId();

      const headers = {
        'X-AGENT-ID': this.agentId
      };

      // Include existing session if we have one (for HMAC validation)
      if (existingSessionId) {
        headers['X-SESSION-ID'] = existingSessionId;
        console.log('Sending existing session ID for validation:', existingSessionId);
      }

      // Include existing conversation if we have one (for validation)
      if (existingConversationId) {
        headers['X-CONVERSATION-ID'] = existingConversationId;
        console.log('Sending existing conversation ID for validation:', existingConversationId);
      }

      const response = await api({
        endpoint: 'sessions/init',
        method: 'POST',
        headers
      });

      if (!response.ok) {
        throw new Error('Failed to initialize session');
      }

      const data = await response.json();

      if (!data.session_id) {
        throw new Error('No session ID returned from init');
      }

      // Update localStorage if session changed
      if (data.session_id !== existingSessionId) {
        console.log(
          data.is_new_session ? '✨ New session created:' : '🔄 Session restored from server:',
          data.session_id
        );
        this.persistenceManager.set(this.persistedSessionId, data.session_id);
      } else {
        console.log('✅ Session validated:', data.session_id);
      }

      // Note: Conversation ID persistence is handled by StorageManager.setActiveConversationId()
      // We just log the status here
      if (data.conversation_id) {
        if (data.conversation_id !== existingConversationId) {
          console.log(
            data.is_new_conversation
              ? '✨ New conversation created:'
              : '🔄 Conversation restored from server:',
            data.conversation_id
          );
        } else {
          console.log('✅ Conversation validated:', data.conversation_id);
        }
      }

      return {
        sessionId: data.session_id,
        conversationId: data.conversation_id,
        isNewSession: data.is_new_session || false,
        isNewConversation: data.is_new_conversation || false
      };
    } catch (error) {
      console.error('Error initializing session:', error);
      return null;
    }
  }

  getCurrentPersistedSessionId() {
    return this.persistenceManager.get(this.persistedSessionId);
  }

  getCurrentPersistedConversationId() {
    return this.persistenceManager.get(this.persistedConversationId);
  }
}
