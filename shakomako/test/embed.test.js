import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';

// embed.js is the loader customers paste on their site. It runs once on import,
// so every test resets the module registry and window.chatvia, then imports it
// fresh against a hand-built <script id="chatbot-embed-script"> tag.
function addEmbedTag(attributes = {}) {
  const tag = document.createElement('script');
  tag.id = 'chatbot-embed-script';
  tag.setAttribute('src', 'https://cdn.example.test/widget/embed.js');
  tag.setAttribute('data-agent-id', 'agent-123');
  for (const [name, value] of Object.entries(attributes)) {
    tag.setAttribute(name, value);
  }
  document.head.appendChild(tag);
  return tag;
}

function fakeVersionResponse() {
  return {
    ok: true,
    status: 200,
    statusText: 'OK',
    json: async () => ({ version: 'v9' })
  };
}

describe('embed.js loader', () => {
  let fetchMock;

  beforeEach(() => {
    vi.resetModules();
    delete window.chatvia;
    document.head.innerHTML = '';
    fetchMock = vi.fn().mockResolvedValue(fakeVersionResponse());
    vi.stubGlobal('fetch', fetchMock);
  });

  afterEach(() => {
    vi.unstubAllGlobals();
    // Restore jsdom's own readyState getter after a test overrode it.
    delete document.readyState;
    delete window.chatvia;
  });

  it('loads the bundle from the script host after DOMContentLoaded, not from "[object Event]"', async () => {
    addEmbedTag();
    Object.defineProperty(document, 'readyState', { configurable: true, get: () => 'loading' });

    await import('../src/embed.js');
    expect(fetchMock).not.toHaveBeenCalled();

    document.dispatchEvent(new Event('DOMContentLoaded'));

    await vi.waitFor(() => expect(fetchMock).toHaveBeenCalled());
    const url = fetchMock.mock.calls[0][0];
    expect(url).not.toContain('[object');
    expect(url).toMatch(/^https:\/\/cdn\.example\.test\/widget\/version\.json\?t=\d+$/);
  });

  it('loads the bundle straight away when the DOM is already parsed', async () => {
    addEmbedTag();

    await import('../src/embed.js');

    await vi.waitFor(() => expect(fetchMock).toHaveBeenCalled());
    expect(fetchMock.mock.calls[0][0]).toMatch(
      /^https:\/\/cdn\.example\.test\/widget\/version\.json\?t=\d+$/
    );
    await vi.waitFor(() =>
      expect(document.getElementById('chatvia-embed-script-v9')?.getAttribute('src')).toBe(
        'https://cdn.example.test/widget/v9/widget.min.js'
      )
    );
  });

  it('hands the self-hosting endpoint attributes to the widget bundle', async () => {
    addEmbedTag({
      'data-api-url': 'https://chat.example.test/widget/',
      'data-reverb-host': 'ws.example.test',
      'data-reverb-port': '8443',
      'data-reverb-key': 'app-key',
      'data-reverb-scheme': 'https'
    });

    await import('../src/embed.js');

    expect(window.chatvia.endpoints).toEqual({
      apiUrl: 'https://chat.example.test/widget',
      scriptApiUrl: 'https://cdn.example.test/widget',
      reverb: { host: 'ws.example.test', port: 8443, key: 'app-key', scheme: 'https' }
    });
  });

  it('ignores invalid endpoint attributes', async () => {
    const warn = vi.spyOn(console, 'warn').mockImplementation(() => {});
    addEmbedTag({ 'data-api-url': 'javascript:alert(1)', 'data-reverb-port': 'abc' });

    await import('../src/embed.js');

    expect(window.chatvia.endpoints).toEqual({
      scriptApiUrl: 'https://cdn.example.test/widget',
      reverb: {}
    });
    expect(warn).toHaveBeenCalledWith(expect.stringContaining('data-api-url'));
    warn.mockRestore();
  });
});
