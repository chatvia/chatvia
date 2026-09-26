import { describe, it, expect } from 'vitest';
import { parseMarkdown, htmlToText, escapeHtml } from '../src/markdown.js';

// The markdown parser is regex-based and deliberately leans on DOMPurify as the
// real security boundary. These tests pin that boundary: the parser must never
// emit a working XSS vector, and the copy helper must never expose the
// element.innerHTML sink it replaced.
describe('parseMarkdown — formatting', () => {
  it('renders bold and italic', () => {
    expect(parseMarkdown('**bold**')).toContain('<strong>bold</strong>');
    expect(parseMarkdown('*italic*')).toContain('<em>italic</em>');
  });

  it('renders links with forced safe rel/target', () => {
    const html = parseMarkdown('[site](https://example.com)');
    expect(html).toContain('href="https://example.com"');
    expect(html).toContain('rel="noopener noreferrer"');
    expect(html).toContain('target="_blank"');
  });

  it('returns empty string for empty/nullish input', () => {
    expect(parseMarkdown('')).toBe('');
    expect(parseMarkdown(null)).toBe('');
    expect(parseMarkdown(undefined)).toBe('');
  });
});

describe('parseMarkdown — sanitization (security)', () => {
  it('neutralizes raw HTML by escaping it — no live element or inline handler', () => {
    const html = parseMarkdown('<img src=x onerror="alert(1)">');
    // The parser escapes all raw input before transforming, so the payload
    // becomes inert text (&lt;img…) rather than a live <img> with an onerror.
    expect(html).not.toMatch(/<[a-z]+[^>]*\son\w+=/i);
    expect(html).toContain('&lt;img');
  });

  it('drops javascript: link protocols (DOMPurify on generated anchors)', () => {
    const html = parseMarkdown('[click](javascript:alert(1))');
    expect(html.toLowerCase()).not.toContain('javascript:');
  });

  it('does not emit a live <script> element', () => {
    const html = parseMarkdown('hello <script>alert(1)</script>');
    expect(html).not.toMatch(/<script/i);
  });

  it('drops non-https image sources (no IP-beaconing http img)', () => {
    const html = parseMarkdown('![x](http://evil.example/track.gif)');
    expect(html).not.toContain('http://evil.example');
  });
});

describe('htmlToText — safe plain-text extraction for copy', () => {
  it('strips markup without executing it', () => {
    expect(htmlToText('<b>hi</b>')).toBe('hi');
    expect(htmlToText('<img src=x onerror="alert(1)">')).not.toContain('onerror');
  });

  it('passes through plain markdown source unchanged', () => {
    expect(htmlToText('**hi**')).toBe('**hi**');
  });

  it('handles nullish input', () => {
    expect(htmlToText(null)).toBe('');
    expect(htmlToText(undefined)).toBe('');
  });
});

describe('escapeHtml', () => {
  it('escapes the five core entities', () => {
    expect(escapeHtml(`<>&"'`)).toBe('&lt;&gt;&amp;&quot;&#039;');
  });
});
