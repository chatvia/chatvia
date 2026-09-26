import { describe, it, expect } from 'vitest';
import { safeImageUrl, formatBytes } from '../src/utils.js';

describe('safeImageUrl — operator image URL allowlist', () => {
  it('accepts https and raster/svg data URIs', () => {
    expect(safeImageUrl('https://cdn.example/logo.png')).toBe('https://cdn.example/logo.png');
    expect(safeImageUrl('data:image/png;base64,AAAA')).toBe('data:image/png;base64,AAAA');
    expect(safeImageUrl('data:image/svg+xml;base64,AAAA')).toBe('data:image/svg+xml;base64,AAAA');
  });

  it('rejects http (mixed content / passive beacon)', () => {
    expect(safeImageUrl('http://cdn.example/logo.png')).toBeNull();
  });

  it('rejects javascript:, file:, blob: and junk', () => {
    expect(safeImageUrl('javascript:alert(1)')).toBeNull();
    expect(safeImageUrl('file:///etc/passwd')).toBeNull();
    expect(safeImageUrl('blob:https://x/abc')).toBeNull();
    expect(safeImageUrl('not a url')).toBeNull();
  });

  it('rejects empty / non-string input', () => {
    expect(safeImageUrl('')).toBeNull();
    expect(safeImageUrl(null)).toBeNull();
    expect(safeImageUrl(undefined)).toBeNull();
    expect(safeImageUrl(42)).toBeNull();
  });
});

describe('formatBytes', () => {
  it('returns empty string for falsy sizes', () => {
    expect(formatBytes(0)).toBe('');
    expect(formatBytes(undefined)).toBe('');
    expect(formatBytes(null)).toBe('');
  });

  it('formats bytes, KB and MB', () => {
    expect(formatBytes(512)).toBe('512 B');
    expect(formatBytes(2048)).toBe('2 KB');
    expect(formatBytes(1024 * 1024 * 1.5)).toBe('1.5 MB');
  });
});
