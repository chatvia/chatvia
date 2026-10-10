import { describe, it, expect } from 'vitest';
import { ChatWidget } from '../src/widget.js';

// The dashboard's Command Bar setting defaults to `top` (config/model-settings.php
// bar.placement). When the settings API sends no placement, the widget must use
// the same default instead of dropping the bar to the bottom of the page.
describe('bar placement default', () => {
  it('defaults to top, like the dashboard', () => {
    const widget = new ChatWidget('agent-1', { mode: 'bar' });
    expect(widget.barPosition).toBe('top');
  });

  it('keeps an explicit programmatic position', () => {
    const widget = new ChatWidget('agent-1', { mode: 'bar', position: 'bottom-right' });
    expect(widget.barPosition).toBe('bottom-right');
  });
});
