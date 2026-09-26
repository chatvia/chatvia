import { describe, it, expect, beforeAll } from 'vitest';
import { createMessageActions } from '../src/state/messages.js';
import { initI18n } from '../src/i18n.js';

// createMessageActions renders the per-message action row (copy + feedback
// thumbs). The active (green/red) state is derived from the message's
// persisted `feedback`, which the API returns as the STRING 'positive' /
// 'negative' (never a boolean) — see chat_messages.feedback. A prior version
// compared against booleans, so feedback loaded from history never lit up.

const widget = {}; // only referenced inside click handlers, which never fire here
const ACTIVE = 'chatvia-widget-active';

function thumbs(feedback) {
  const row = createMessageActions(widget, 'hello', 'msg-1', feedback, null);
  return {
    up: row.querySelector('.chatvia-widget-thumbs-up'),
    down: row.querySelector('.chatvia-widget-thumbs-down')
  };
}

describe('message feedback active state', () => {
  beforeAll(async () => {
    await initI18n({ language: 'en' });
  });

  it("marks thumbs-up active for 'positive'", () => {
    const { up, down } = thumbs('positive');
    expect(up.classList.contains(ACTIVE)).toBe(true);
    expect(down.classList.contains(ACTIVE)).toBe(false);
  });

  it("marks thumbs-down active for 'negative'", () => {
    const { up, down } = thumbs('negative');
    expect(down.classList.contains(ACTIVE)).toBe(true);
    expect(up.classList.contains(ACTIVE)).toBe(false);
  });

  it('leaves both inactive when there is no feedback', () => {
    const { up, down } = thumbs(null);
    expect(up.classList.contains(ACTIVE)).toBe(false);
    expect(down.classList.contains(ACTIVE)).toBe(false);
  });

  it('ignores a boolean true (regression: API sends strings, not booleans)', () => {
    const { up, down } = thumbs(true);
    expect(up.classList.contains(ACTIVE)).toBe(false);
    expect(down.classList.contains(ACTIVE)).toBe(false);
  });
});
