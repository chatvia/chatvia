import { ChatWidget } from './widget.js';
import { NAMESPACE } from './utils.js';

(function () {
  const namespace = NAMESPACE;

  if (!window[namespace] || !window[namespace].agentId) {
    console.error(
      `${namespace}: Embedder script not found or missing agentId. Cannot initialize widget.`
    );
    return;
  }

  const clientId = window[namespace].agentId;
  const commandQueue = window[namespace].q || [];
  // Closure-private registry of host-page event subscriptions ('handoff',
  // 'open', 'close', 'ready'). Was previously exposed on the public API as
  // `_eventListeners` so internal modules could reach in via window.chatvia,
  // but that also let any third-party script on the embedding page snoop on
  // takeover events. Now nobody outside this closure can read or mutate it —
  // the widget gets a write-only `emit` callback to fire events.
  const eventListeners = {};
  const emitWidgetEvent = (event, data) => {
    const cbs = eventListeners[event];
    if (!cbs || cbs.length === 0) return;
    // Copy so a listener that unsubscribes itself doesn't shift the array
    // mid-iteration and skip the next listener.
    for (const cb of cbs.slice()) {
      try {
        cb(data);
      } catch (err) {
        console.error(`chatvia: event listener for "${event}" threw:`, err);
      }
    }
  };

  console.log(`chatvia: Instantiating ChatWidget from main.js for client ${clientId}`);

  try {
    // Mode is validated in embed.js; default here is a safety net for direct
    // callers that bypass the embed script.
    const options = {
      mountId: window[namespace].mountId,
      language: window[namespace].language,
      theme: window[namespace].theme,
      auth: window[namespace].auth,
      mode: window[namespace].mode || 'bubble',
      // Hook used by internal modules (handoff, etc.) to fan a single
      // event out to host-page listeners. Closure-private — see comment above.
      emit: emitWidgetEvent
    };
    const widgetInstance = new ChatWidget(clientId, options);

    // Create the public API object
    const api = {
      // Properties
      agentId: clientId,

      /**
       * Initialize the widget
       * @returns {Promise<void>}
       */
      init: async () => {
        await widgetInstance.init();
      },

      /**
       * Open the chat window
       */
      open: () => {
        widgetInstance.toggleChat(true);
      },

      /**
       * Close the chat window
       */
      close: () => {
        widgetInstance.toggleChat(false);
      },

      /**
       * Toggle the chat window open/closed
       * @returns {boolean} - New state (true = open, false = closed)
       */
      toggle: () => {
        widgetInstance.toggleChat();
        return widgetInstance.isOpen;
      },

      /**
       * Check if the chat window is open
       * @returns {boolean}
       */
      isOpen: () => {
        return widgetInstance.isOpen;
      },

      /**
       * Send a message programmatically
       * @param {string} message - The message text to send
       * @returns {Promise<void>}
       */
      send: async message => {
        if (!message || typeof message !== 'string') {
          console.warn("chatvia: 'send' called without valid message text.");
          return;
        }
        await widgetInstance.sendUserMessage(message.trim());
      },

      /**
       * Alias for send() - backward compatibility
       * @param {string} message - The message text to send
       * @returns {Promise<void>}
       */
      sendMessage: async message => {
        await api.send(message);
      },

      /**
       * Start a new chat conversation
       * @returns {Promise<void>}
       */
      newChat: async () => {
        await widgetInstance.startNewConversation();
      },

      /**
       * Destroy the widget and clean up resources
       */
      destroy: () => {
        widgetInstance.destroy();
      },

      /**
       * Check if currently in human agent mode (handoff active)
       * @returns {boolean}
       */
      isHumanMode: () => {
        return widgetInstance.isInHumanMode();
      },

      /**
       * Check if currently waiting for human agent (pending)
       * @returns {boolean}
       */
      isPending: () => {
        return widgetInstance.isInPendingMode();
      },

      /**
       * Get current handoff mode ('ai', 'pending', 'human')
       * @returns {string}
       */
      getHandoffMode: () => {
        return widgetInstance.getHandoffMode();
      },

      /**
       * Get admin name when in human mode
       * @returns {string|null}
       */
      getAdminName: () => {
        return widgetInstance.getAdminName();
      },

      /**
       * Request human assistance (triggers handoff flow)
       * @returns {Promise<object>}
       */
      requestHuman: async () => {
        return widgetInstance.requestHumanAssistance();
      },

      /**
       * Subscribe to widget events
       * @param {string} event - Event name ('open', 'close', 'message', 'ready', 'handoff')
       * @param {Function} callback - Callback function
       * @returns {Function} - Unsubscribe function
       */
      on: (event, callback) => {
        if (typeof callback !== 'function') {
          console.warn("chatvia: 'on' requires a function callback");
          return () => {};
        }

        if (!eventListeners[event]) {
          eventListeners[event] = [];
        }
        eventListeners[event].push(callback);

        // Return unsubscribe function
        return () => {
          const idx = eventListeners[event].indexOf(callback);
          if (idx > -1) eventListeners[event].splice(idx, 1);
        };
      },

      /**
       * Unsubscribe from widget events
       * @param {string} event - Event name
       * @param {Function} callback - Callback to remove (optional, removes all if not provided)
       */
      off: (event, callback) => {
        if (!eventListeners[event]) return;
        if (callback) {
          const idx = eventListeners[event].indexOf(callback);
          if (idx > -1) eventListeners[event].splice(idx, 1);
        } else {
          eventListeners[event] = [];
        }
      },

      // Legacy function-style API for backward compatibility
      __call: async (command, ...args) => {
        switch (command) {
          case 'init':
            return api.init();
          case 'open':
            return api.open();
          case 'close':
            return api.close();
          case 'toggle':
            return api.toggle();
          case 'send':
          case 'sendMessage':
            return api.send(args[0]);
          case 'newChat':
            return api.newChat();
          case 'destroy':
            return api.destroy();
          default:
            console.warn(`chatvia: Unknown command '${command}'`);
        }
      }
    };

    // Set up DOM event listeners to emit custom events
    document.addEventListener('chat:opened', () => emitWidgetEvent('open'));
    document.addEventListener('chat:closed', () => emitWidgetEvent('close'));

    // Make API callable as function for legacy support: chatbot('open')
    const proxyHandler = {
      apply: (target, thisArg, args) => target.__call(...args),
      get: (target, prop) => target[prop]
    };

    window[namespace] = new Proxy(api, proxyHandler);

    // Process queued commands
    console.log(`chatvia: Processing ${commandQueue.length} queued commands.`);

    while (commandQueue.length > 0) {
      const commandArgs = commandQueue.shift();
      if (commandArgs) {
        console.log('chatvia: Executing queued command:', commandArgs);
        api.__call(...commandArgs);
      }
    }

    // Emit ready event
    setTimeout(() => emitWidgetEvent('ready'), 0);

    console.log('chatvia: Chat Widget setup complete.');
  } catch (error) {
    console.error('chatvia: Failed to instantiate or setup ChatWidget:', error);

    window[namespace] = function (command) {
      console.error(
        `chatvia: Widget failed to initialize. Cannot execute command: ${command}`,
        error
      );
    };

    window[namespace].error = 'Widget failed to initialize';
  }
})();
