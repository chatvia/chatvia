/**
 * Keyboard trigger for the bar / chat launcher.
 *
 * The dashboard lets the agent owner pick one of a small finite set of
 * shortcuts, persisted under `bar.trigger` and surfaced on the public
 * widget-settings API as `keyboard_trigger`. This module parses that string
 * into:
 *   - a matcher: does this `keydown` event satisfy the configured shortcut?
 *   - a label:   what should the bar's shortcut hint render?
 *
 * Keep the canonical list aligned with the backend validation rule at
 * config/model-settings.php and the dashboard's `triggerOptions` list in
 * resources/js/pages/agents/setup/Setup.vue — adding a value here without
 * updating both will land an option the server rejects or the dashboard hides.
 */

export const TRIGGER_VALUES = ['cmd+k', 'cmd+j', 'cmd+/', '/', 'shift+/'];
export const DEFAULT_TRIGGER = 'cmd+k';

/**
 * Coerce arbitrary input from the settings API (could be missing or stale)
 * into a known trigger string. Unknown values fall back to the default so the
 * launcher is never unreachable.
 */
export function normalizeTrigger(value) {
  return TRIGGER_VALUES.includes(value) ? value : DEFAULT_TRIGGER;
}

function isMacPlatform() {
  return (
    typeof navigator !== 'undefined' &&
    typeof navigator.platform === 'string' &&
    navigator.platform.toUpperCase().includes('MAC')
  );
}

/**
 * Test whether a `keydown` event satisfies the configured trigger.
 *
 * Mac uses Cmd as the platform modifier; everything else uses Ctrl. For the
 * bare `/` and `shift+/` triggers we explicitly require NO Cmd/Ctrl/Alt so
 * the user can still type a slash into a focused input on a normal page
 * without accidentally toggling the launcher (the caller decides whether the
 * focus context warrants ignoring the shortcut — see events.js).
 */
export function matchesTrigger(event, trigger) {
  const t = normalizeTrigger(trigger);
  const isMac = isMacPlatform();
  const platformMod = isMac ? event.metaKey : event.ctrlKey;
  const key = (event.key || '').toLowerCase();

  switch (t) {
    case 'cmd+k':
      return platformMod && !event.altKey && key === 'k';
    case 'cmd+j':
      return platformMod && !event.altKey && key === 'j';
    case 'cmd+/':
      return platformMod && !event.altKey && key === '/';
    case '/':
      return !platformMod && !event.altKey && !event.shiftKey && key === '/';
    case 'shift+/':
      // `Shift + /` is the `?` key on US layouts. event.key is '?' when Shift
      // is held, so check both the explicit character and the key + modifier
      // combo to cover non-US layouts that label the same physical key.
      return !platformMod && !event.altKey && (key === '?' || (event.shiftKey && key === '/'));
    default:
      return false;
  }
}

/**
 * Render label for the bar's shortcut hint. On mac the Cmd glyph is `⌘`;
 * everywhere else we spell it `Ctrl`. The `/` and `?` variants are identical
 * on every platform.
 */
export function triggerLabel(trigger) {
  const t = normalizeTrigger(trigger);
  const mod = isMacPlatform() ? '⌘' : 'Ctrl+';
  switch (t) {
    case 'cmd+k':
      return `${mod}K`;
    case 'cmd+j':
      return `${mod}J`;
    case 'cmd+/':
      return `${mod}/`;
    case '/':
      return '/';
    case 'shift+/':
      return '?';
    default:
      return `${mod}K`;
  }
}
