import { describe, it, expect, beforeEach } from 'vitest';
import {
  initI18n,
  changeLanguage,
  t,
  getDirection,
  getCurrentLanguage,
  getAvailableLanguages
} from '../src/i18n.js';
import en from '../languages/en.json';
import fi from '../languages/fi.json';
import no from '../languages/no.json';
import pl from '../languages/pl.json';
import sv from '../languages/sv.json';

// Every leaf key path of a translation file, e.g. "widget.open_chat".
const keyPaths = (dict, prefix = '') =>
  Object.entries(dict).flatMap(([key, value]) =>
    value && typeof value === 'object' ? keyPaths(value, `${prefix}${key}.`) : [`${prefix}${key}`]
  );

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

describe('dashboard languages', () => {
  // The dashboard and the agent language picker offer these; the widget must
  // render them in the visitor's language rather than falling back to English.
  const complete = { fi, no, pl, sv };

  it.each(Object.keys(complete))('bundles %s with every English key', async code => {
    expect(keyPaths(complete[code]).sort()).toEqual(keyPaths(en).sort());

    await changeLanguage(code);
    expect(getCurrentLanguage()).toBe(code);
    expect(t('widget.open_chat')).toBe(complete[code].widget.open_chat);
  });

  it('lists the new languages as available', () => {
    const codes = getAvailableLanguages().map(language => language.code);
    expect(codes).toEqual(expect.arrayContaining(['fi', 'no', 'pl', 'sv']));
  });

  it('maps Norwegian Bokmål and Nynorsk browser codes to Norwegian', async () => {
    await changeLanguage('nb-NO');
    expect(getCurrentLanguage()).toBe('no');
    await changeLanguage('nn');
    expect(getCurrentLanguage()).toBe('no');
  });
});
