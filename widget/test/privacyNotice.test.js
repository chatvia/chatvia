import { describe, it, expect, beforeAll, beforeEach } from 'vitest';
import { ChatWidget } from '../src/widget.js';
import { initI18n } from '../src/i18n.js';
import { applyPrivacyNotice, readPrivacyDismissed } from '../src/state/config.js';
import { CSS_CLASSES } from '../src/utils.js';

// The privacy-consent strip ("By chatting, you agree to our privacy policy")
// only renders when the agent has a valid http(s) privacy_policy_url AND the
// visitor hasn't dismissed it. `_createPrivacyNotice` + `applyPrivacyNotice`
// read only `this.settings` / `this.agentId` / the two DOM refs, so we exercise
// them on a bare prototype instance instead of booting the whole widget.
function buildNotice(settings, agentId = 'agent-x') {
  const stub = Object.create(ChatWidget.prototype);
  stub.settings = settings;
  stub.agentId = agentId;
  stub.privacyNotice = stub._createPrivacyNotice();
  return stub;
}

const linkSelector = '.' + CSS_CLASSES.PRIVACY_NOTICE_LINK;
const dismissSelector = '.' + CSS_CLASSES.PRIVACY_NOTICE_DISMISS;

describe('privacy-consent strip', () => {
  beforeAll(async () => {
    await initI18n({ language: 'en' });
  });

  // jsdom runs under an opaque origin (about:blank), so window.localStorage is
  // unavailable — the code guards it with try/catch. Install an in-memory mock
  // so the dismiss-persistence path is actually exercised.
  const store = new Map();
  const mockLocalStorage = {
    getItem: k => (store.has(k) ? store.get(k) : null),
    setItem: (k, v) => store.set(k, String(v)),
    removeItem: k => store.delete(k),
    clear: () => store.clear()
  };
  beforeEach(() => {
    store.clear();
    Object.defineProperty(window, 'localStorage', {
      value: mockLocalStorage,
      configurable: true
    });
  });

  it('renders the consent line + link when a valid url is set', () => {
    const w = buildNotice({ privacy_policy_url: 'https://acme.test/privacy' });
    applyPrivacyNotice(w);

    expect(w.privacyNotice.style.display).not.toBe('none');

    const link = w.privacyNotice.querySelector(linkSelector);
    expect(link).not.toBeNull();
    expect(link.getAttribute('href')).toBe('https://acme.test/privacy');
    expect(link.getAttribute('target')).toBe('_blank');
    expect(link.getAttribute('rel')).toContain('noopener');
    expect(link.textContent).toBe('privacy policy');
    // The link sits inline inside the sentence, not as the whole text.
    expect(w.privacyNoticeText.textContent).toContain('By chatting');
  });

  it('stays hidden when no url is configured', () => {
    const w = buildNotice({});
    applyPrivacyNotice(w);

    expect(w.privacyNotice.style.display).toBe('none');
    expect(w.privacyNotice.querySelector(linkSelector)).toBeNull();
  });

  it('rejects non-http(s) urls (no javascript: links)', () => {
    const w = buildNotice({ privacy_policy_url: 'javascript:alert(1)' });
    applyPrivacyNotice(w);

    expect(w.privacyNotice.style.display).toBe('none');
    expect(w.privacyNotice.querySelector(linkSelector)).toBeNull();
  });

  it('hides and remembers the choice when dismissed', () => {
    const w = buildNotice({ privacy_policy_url: 'https://acme.test/privacy' }, 'agent-dismiss');
    applyPrivacyNotice(w);
    expect(w.privacyNotice.style.display).not.toBe('none');

    w.privacyNotice.querySelector(dismissSelector).click();

    expect(w.privacyNotice.style.display).toBe('none');
    expect(readPrivacyDismissed('agent-dismiss')).toBe(true);

    // A later re-apply keeps it hidden for this agent.
    applyPrivacyNotice(w);
    expect(w.privacyNotice.style.display).toBe('none');
  });
});
