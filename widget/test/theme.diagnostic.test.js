import { describe, it, expect, beforeEach } from 'vitest';
import * as config from '../src/state/config.js';

// Diagnostic: confirm the widget applies the owner's configured theme
// (settings.theme) on a fresh boot with no visitor override.
function fakeWidget(settingsTheme, optionsTheme = null) {
  const win = document.createElement('div');
  win.className = 'chatvia-widget-window';
  return {
    agentId: 'agent-test',
    settings: { theme: settingsTheme },
    themePreference: config.readStoredTheme('agent-test') || optionsTheme || null,
    chatWindow: win,
    bar: null,
  };
}

describe('applyTheme precedence', () => {
  beforeEach(() => localStorage.clear());

  it('applies dark from settings.theme when there is no visitor override', () => {
    const w = fakeWidget('dark');
    config.applyTheme(w);
    expect(w.chatWindow.classList.contains('chatvia-widget-dark')).toBe(true);
  });

  it('a baked data-theme (options.theme) OVERRIDES the configured settings.theme', () => {
    // This is the embed-snippet case: data-theme="light" beats settings.theme="dark".
    const w = fakeWidget('dark', 'light');
    config.applyTheme(w);
    expect(w.chatWindow.classList.contains('chatvia-widget-dark')).toBe(false);
  });
});
