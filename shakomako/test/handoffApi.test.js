import { describe, it, expect, beforeEach, vi } from 'vitest';

// Handoff calls must go through api() so they share its base URL (which can be
// overridden per embed tag), JSON headers, auth token and request timeout.
const apiMock = vi.fn();
vi.mock('../src/api.js', () => ({ default: (...args) => apiMock(...args) }));

const { checkHandoffStatus, sendMessageInHumanMode, HANDOFF_MODE } =
  await import('../src/state/handoff.js');

function stubWidget() {
  return {
    agentId: 'agent-1',
    sessionId: 'session-1',
    handoffMode: HANDOFF_MODE.AI,
    getCurrentActiveConversationId: () => 'conv-1'
  };
}

describe('handoff requests go through api()', () => {
  beforeEach(() => {
    apiMock.mockReset();
  });

  it('checks the handoff status via api()', async () => {
    apiMock.mockResolvedValue({ ok: true, json: async () => ({ handoff_status: 'ai' }) });

    await checkHandoffStatus(stubWidget());

    expect(apiMock).toHaveBeenCalledTimes(1);
    const req = apiMock.mock.calls[0][0];
    expect(req.endpoint).toBe('handoff/status');
    expect(req.method).toBe('GET');
    expect(req.headers).toMatchObject({
      'X-SESSION-ID': 'session-1',
      'X-AGENT-ID': 'agent-1',
      'X-CONVERSATION-ID': 'conv-1'
    });
  });

  it('sends a human-mode message via api()', async () => {
    apiMock.mockResolvedValue({ ok: true });

    const sent = await sendMessageInHumanMode(stubWidget(), 'hello');

    expect(sent).toBe(true);
    const req = apiMock.mock.calls[0][0];
    expect(req.endpoint).toBe('stream');
    expect(req.method).toBe('POST');
    expect(JSON.parse(req.body)).toMatchObject({ content: 'hello', conversation_id: 'conv-1' });
  });
});
