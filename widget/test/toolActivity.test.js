import { describe, it, expect, beforeAll, beforeEach } from 'vitest';
import { initI18n } from '../src/i18n.js';
import { startStreamingMessage, handleToolEvent } from '../src/state/messages.js';

// The transient activity shimmer rendered during a tool call. The backend
// resolves a per-connector `activity_label` (config/connectors.php); the widget
// shows it verbatim and degrades gracefully when it's absent so a first-party
// capability is never mislabeled as an "external tool".

const TOOL_CALLING = '.chatvia-widget-tool-calling';

function makeWidget() {
  const messagesContainer = document.createElement('div');
  document.body.appendChild(messagesContainer);
  return {
    messagesContainer,
    settings: { avatar_url: null },
    scrollToBottom() {},
    followStream() {},
    pinMessageToTop() {}
  };
}

function callingText() {
  return document.querySelector(TOOL_CALLING)?.textContent ?? null;
}

describe('tool activity indicator', () => {
  beforeAll(async () => {
    await initI18n('en');
  });

  beforeEach(() => {
    document.body.innerHTML = '';
  });

  it('renders the backend activity_label verbatim', () => {
    const widget = makeWidget();
    startStreamingMessage(widget);

    handleToolEvent(widget, {
      type: 'tool',
      phase: 'start',
      tool_call_id: 'tc-1',
      kind: 'mcp',
      label: 'Forms',
      activity_label: 'Preparing your form…'
    });

    expect(callingText()).toBe('Preparing your form…');
  });

  it('falls back to "Connecting to {label}…" when no activity_label is set', () => {
    const widget = makeWidget();
    startStreamingMessage(widget);

    handleToolEvent(widget, {
      type: 'tool',
      phase: 'start',
      tool_call_id: 'tc-1',
      kind: 'mcp',
      label: 'Stripe'
    });

    expect(callingText()).toBe('Connecting to Stripe…');
  });

  it('falls back to a generic phrase with neither label nor activity_label', () => {
    const widget = makeWidget();
    startStreamingMessage(widget);

    handleToolEvent(widget, { type: 'tool', phase: 'start', tool_call_id: 'tc-1' });

    // Never the old "external tool" wording.
    expect(callingText()).toBe('Working…');
    expect(callingText()).not.toMatch(/external/i);
  });

  it('updates the phrase to the most recent call (find → render)', () => {
    const widget = makeWidget();
    startStreamingMessage(widget);

    handleToolEvent(widget, {
      phase: 'start',
      tool_call_id: 'tc-find',
      activity_label: 'Finding the right form…'
    });
    handleToolEvent(widget, {
      phase: 'start',
      tool_call_id: 'tc-render',
      activity_label: 'Preparing your form…'
    });

    // Single shimmer node, showing the latest phrase.
    expect(document.querySelectorAll(TOOL_CALLING)).toHaveLength(1);
    expect(callingText()).toBe('Preparing your form…');
  });

  it('keeps the indicator until every active call ends, then clears it', () => {
    const widget = makeWidget();
    startStreamingMessage(widget);

    handleToolEvent(widget, { phase: 'start', tool_call_id: 'a', activity_label: 'Working A…' });
    handleToolEvent(widget, { phase: 'start', tool_call_id: 'b', activity_label: 'Working B…' });

    handleToolEvent(widget, { phase: 'end', tool_call_id: 'a' });
    expect(callingText()).not.toBeNull(); // 'b' still running

    handleToolEvent(widget, { phase: 'end', tool_call_id: 'b' });
    expect(callingText()).toBeNull(); // all done → cleared
  });

  it('shows no indicator for an internal builtin tool, but still captures its artifacts', () => {
    const widget = makeWidget();
    startStreamingMessage(widget);

    // Builtin capability (forms): internal plumbing — no "Calling …" chip even
    // though it carries a label + activity_label.
    handleToolEvent(widget, {
      phase: 'start',
      tool_call_id: 'tc-form',
      kind: 'mcp',
      label: 'Forms',
      activity_label: 'Preparing your form…',
      internal: true
    });
    expect(callingText()).toBeNull();

    // Its end-phase form artifact is still stashed for rendering.
    handleToolEvent(widget, {
      phase: 'end',
      tool_call_id: 'tc-form',
      artifacts: [{ kind: 'form', data: { form_instance_id: 'f1' } }]
    });
    expect(callingText()).toBeNull();
    expect(widget._streamFormArtifacts).toEqual([{ form_instance_id: 'f1' }]);
  });

  it('still shows the indicator for a non-internal (external) tool', () => {
    const widget = makeWidget();
    startStreamingMessage(widget);

    handleToolEvent(widget, {
      phase: 'start',
      tool_call_id: 'tc-ext',
      kind: 'mcp',
      label: 'Stripe',
      activity_label: 'Searching Stripe…',
      internal: false
    });

    expect(callingText()).toBe('Searching Stripe…');
  });
});
