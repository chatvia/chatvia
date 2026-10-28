import { resolveAppearanceText, resolveStarters } from '@/chat/lib/appearanceText';
import { describe, expect, it } from 'vitest';

// The hosted chat re-localizes these strings in the browser, so they must pick
// the same text the server's AppearanceTextResolver does: the manual edit,
// else the AI translation, else the base value.
describe('resolveAppearanceText', () => {
    const appearance = {
        chat_window_title: 'Support',
        welcome_message: 'Hi! How can we help?',
        composer_placeholder: 'Ask anything',
        notice: 'Answers can be wrong.',
        text_translations: {
            chat_window_title: { de: 'Hilfe', fr: 'Aide' },
            welcome_message: { de: 'Hallo! Wie können wir helfen?' },
            notice: { de: 'Antworten können falsch sein.' },
        },
        text_overrides: {
            chat_window_title: { de: 'Kundendienst', fr: '' },
        },
    };

    it('prefers the manual edit, then the AI translation, then the base text', () => {
        expect(resolveAppearanceText(appearance, 'de')).toEqual({
            chat_window_title: 'Kundendienst',
            welcome_message: 'Hallo! Wie können wir helfen?',
            composer_placeholder: 'Ask anything',
            notice: 'Antworten können falsch sein.',
        });
    });

    it('skips an empty manual edit', () => {
        expect(resolveAppearanceText(appearance, 'fr').chat_window_title).toBe('Aide');
    });

    it('gives the base text for a language with nothing of its own, and null for a missing value', () => {
        expect(resolveAppearanceText(appearance, 'ja').welcome_message).toBe('Hi! How can we help?');
        expect(resolveAppearanceText({}, 'de')).toEqual({ chat_window_title: null, welcome_message: null, composer_placeholder: null, notice: null });
        expect(resolveAppearanceText(null, 'de').chat_window_title).toBeNull();
    });
});

describe('resolveStarters', () => {
    it('localizes each starter with the same order of preference and keeps its icon', () => {
        const starters = [
            { text: 'Track my order', icon: 'truck', translations: { de: 'Bestellung verfolgen' }, overrides: { de: 'Wo ist mein Paket?' } },
            { text: 'Talk to sales', translations: { de: 'Mit dem Vertrieb sprechen' } },
            { text: 'Opening hours', overrides: { de: '' } },
        ];

        expect(resolveStarters(starters, 'de')).toEqual([
            { text: 'Wo ist mein Paket?', icon: 'truck' },
            { text: 'Mit dem Vertrieb sprechen', icon: null },
            { text: 'Opening hours', icon: null },
        ]);
    });

    it('reads a missing list as no starters', () => {
        expect(resolveStarters(undefined, 'de')).toEqual([]);
    });
});
