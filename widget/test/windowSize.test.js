import { describe, it, expect } from 'vitest';
import { normalizeWindowSize } from '../src/state/config.js';

// Regression: the dashboard sends window_size as the semantic value
// ('small'/'medium'/'large'), but applyWindowSize() and the expand/collapse
// controls compare against the CSS class ('chatvia-widget-large'). Without this
// mapping the size class is never applied, so the dropdown did nothing.
describe('normalizeWindowSize — maps the API window size to its CSS class', () => {
  it('maps the semantic values the dashboard sends to their CSS class', () => {
    expect(normalizeWindowSize('small')).toBe('chatvia-widget-small');
    expect(normalizeWindowSize('medium')).toBe('chatvia-widget-medium');
    expect(normalizeWindowSize('large')).toBe('chatvia-widget-large');
  });

  it('passes a value that is already a CSS class through unchanged', () => {
    expect(normalizeWindowSize('chatvia-widget-medium')).toBe('chatvia-widget-medium');
  });

  it('falls back to large for missing or unknown values', () => {
    expect(normalizeWindowSize(undefined)).toBe('chatvia-widget-large');
    expect(normalizeWindowSize(null)).toBe('chatvia-widget-large');
    expect(normalizeWindowSize('huge')).toBe('chatvia-widget-large');
  });
});
