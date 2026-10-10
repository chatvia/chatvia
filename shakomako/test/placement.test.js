import { describe, it, expect } from 'vitest';
import { resolvePlacement } from '../src/state/config.js';

describe('resolvePlacement() — bubble corner follows the text direction', () => {
  it('keeps the configured corner in a left-to-right language', () => {
    expect(resolvePlacement('bottom-right', 'ltr')).toBe('bottom-right');
    expect(resolvePlacement('bottom-left', 'ltr')).toBe('bottom-left');
  });

  it('mirrors the corner in a right-to-left language', () => {
    expect(resolvePlacement('bottom-right', 'rtl')).toBe('bottom-left');
    expect(resolvePlacement('bottom-left', 'rtl')).toBe('bottom-right');
  });

  it('treats a missing or unknown placement as bottom-right, mirrored for RTL', () => {
    expect(resolvePlacement(null, 'ltr')).toBe('bottom-right');
    expect(resolvePlacement(undefined, 'rtl')).toBe('bottom-left');
    expect(resolvePlacement('top-left', 'rtl')).toBe('bottom-left');
  });
});
