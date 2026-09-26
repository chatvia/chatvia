import { describe, it, expect, beforeEach } from 'vitest';
import { initI18n, changeLanguage, t, getDirection } from '../src/i18n.js';

// i18n holds module-level state, so each test sets the active language first.
describe('t() — translation with English fallback', () => {
  beforeEach(async () => {
    await initI18n({ language: 'en' });
  });

  it('translates a present key in the active language', async () => {
    await changeLanguage('fr');
    // open_chat exists in every bundled language, so this is the French string.
    expect(t('widget.open_chat')).not.toBe('widget.open_chat');
  });

  it('falls back to English when a key is missing in the active language', async () => {
    await changeLanguage('fr');
    // request_human was only added to en.json — a not-yet-translated locale must
    // render the English string, never the raw "handoff.request_human" key.
    expect(t('handoff.request_human')).toBe('Request human assistance');
  });

  it('returns the key itself when absent everywhere', () => {
    expect(t('nonsense.missing_key')).toBe('nonsense.missing_key');
  });

  it('interpolates variables', () => {
    expect(t('handoff.message_agent', { name: 'Sam' })).toBe('Message Sam...');
  });
});

describe('getDirection', () => {
  it('reports rtl for Arabic and ltr for English', async () => {
    await changeLanguage('ar');
    expect(getDirection()).toBe('rtl');
    await changeLanguage('en');
    expect(getDirection()).toBe('ltr');
  });
});
