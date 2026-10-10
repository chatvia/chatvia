// Every localStorage access is wrapped: Safari throws a SecurityError on any
// localStorage operation when storage is partitioned or blocked — "Prevent
// Cross-Site Tracking" (default on), "Block All Cookies", Private Browsing, or
// a sandboxed/third-party iframe. An unguarded throw here used to propagate
// into AgentManager.verifyClient(), which swallowed it and reported the agent
// as unverified, aborting init BEFORE the widget ever mounted — so the widget
// was simply invisible in Safari while working in Chrome. Degrading to "no
// persistence" keeps the widget rendering; it just re-verifies and re-creates
// sessions each load instead of caching them. (config.js guards its theme
// storage the same way.)
export class PersistenceManager {
  constructor(agentId = null) {
    this.agentId = agentId;
  }

  set(name, value) {
    const key = this.agentId ? `${this.agentId}-${name}` : name;
    try {
      // Treat null/undefined as a removal. localStorage.setItem coerces them to
      // the literal strings "null"/"undefined", which then read back as truthy —
      // e.g. setActiveConversationId(null) used to persist "null", so the next
      // "do I have an active conversation?" check wrongly answered yes. Removing
      // the key makes get() return a real null.
      if (value === null || value === undefined) {
        localStorage.removeItem(key);
        return;
      }
      localStorage.setItem(key, value);
    } catch {
      /* storage unavailable — non-fatal, widget still renders */
    }
  }

  get(name) {
    const key = this.agentId ? `${this.agentId}-${name}` : name;
    try {
      return localStorage.getItem(key);
    } catch {
      return null;
    }
  }

  remove(name) {
    const key = this.agentId ? `${this.agentId}-${name}` : name;
    try {
      localStorage.removeItem(key);
    } catch {
      /* storage unavailable — non-fatal */
    }
  }

  clear() {
    try {
      if (this.agentId) {
        // Remove all keys for this specific agent
        const keys = Object.keys(localStorage);
        keys.forEach(key => {
          if (key.startsWith(`${this.agentId}-`)) {
            localStorage.removeItem(key);
          }
        });
      } else {
        localStorage.clear();
      }
    } catch {
      /* storage unavailable — non-fatal */
    }
  }
}
