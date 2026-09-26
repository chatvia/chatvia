import { describe, it, expect } from 'vitest';
import { normalizeTrigger, matchesTrigger, triggerLabel } from '../src/keyboardTrigger.js';

// Build a minimal keydown-like object. Platform modifier is supplied as BOTH
// meta and ctrl so a "cmd+…" assertion holds whether the test host reports as
// mac (metaKey) or not (ctrlKey).
function ev(overrides = {}) {
  return { metaKey: false, ctrlKey: false, altKey: false, shiftKey: false, key: '', ...overrides };
}

describe('normalizeTrigger', () => {
  it('passes known triggers through', () => {
    expect(normalizeTrigger('cmd+k')).toBe('cmd+k');
    expect(normalizeTrigger('shift+/')).toBe('shift+/');
  });

  it('falls back to the default for unknown/missing values', () => {
    expect(normalizeTrigger('bogus')).toBe('cmd+k');
    expect(normalizeTrigger(undefined)).toBe('cmd+k');
  });
});

describe('matchesTrigger', () => {
  it('matches the platform-modifier shortcut', () => {
    expect(matchesTrigger(ev({ metaKey: true, ctrlKey: true, key: 'k' }), 'cmd+k')).toBe(true);
  });

  it('rejects the modifier shortcut when Alt is also held', () => {
    expect(
      matchesTrigger(ev({ metaKey: true, ctrlKey: true, altKey: true, key: 'k' }), 'cmd+k')
    ).toBe(false);
  });

  it('matches a bare slash with no modifiers', () => {
    expect(matchesTrigger(ev({ key: '/' }), '/')).toBe(true);
    // ...but not when a platform modifier is held (that is cmd+/, a different trigger)
    expect(matchesTrigger(ev({ metaKey: true, ctrlKey: true, key: '/' }), '/')).toBe(false);
  });

  it('matches shift+/ via the resulting "?" character', () => {
    expect(matchesTrigger(ev({ key: '?', shiftKey: true }), 'shift+/')).toBe(true);
  });
});

describe('triggerLabel', () => {
  it('produces a stable, platform-appropriate label', () => {
    expect(triggerLabel('cmd+k')).toMatch(/K$/);
    expect(triggerLabel('/')).toBe('/');
    expect(triggerLabel('shift+/')).toBe('?');
  });
});
