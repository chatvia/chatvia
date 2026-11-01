import { closeOpenMarkdown } from '@/chat/lib/streamingMarkdown';
import { describe, expect, it } from 'vitest';

// A streamed answer cut off inside a marker renders styled, not as raw stars.
describe('closeOpenMarkdown', () => {
    it('closes an open marker so the text so far renders styled', () => {
        expect(closeOpenMarkdown('This is **impor')).toBe('This is **impor**');
    });

    it('shows only the text of a half-written link', () => {
        expect(closeOpenMarkdown('See [the docs](https://exa')).toBe('See the docs');
    });

    it('hides a half-written image', () => {
        expect(closeOpenMarkdown('Here: ![chart](https://exa')).toBe('Here: ');
    });

    it('leaves markers inside a code block alone', () => {
        expect(closeOpenMarkdown('```php\necho **x')).toBe('```php\necho **x');
    });
});
