/**
 * BarWidget — convenience wrapper around ChatWidget that forces `bar` mode.
 *
 * Use this when the embedding page wants a floating launcher bar (a horizontal
 * "ask anything" pill, opens a full-height slider panel) instead of the
 * default corner chat bubble. The underlying behavior, API, and backend are
 * identical to ChatWidget — only the launcher's shape differs.
 *
 * Equivalent to:
 *   new ChatWidget(agentId, { mode: 'bar', ...options });
 */

import { ChatWidget } from './widget.js';

export class BarWidget extends ChatWidget {
  constructor(agentId, options = {}) {
    super(agentId, {
      position: 'bottom',
      placeholder: 'Ask a question...',
      ...options,
      // Forced — overrides any caller-supplied mode.
      mode: 'bar'
    });
  }
}

// Re-exported so a single import covers both launchers.
export { ChatWidget };
