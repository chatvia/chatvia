import { describe, it, expect } from 'vitest';
import { updateStartersContainer } from '../src/state/config.js';
import { CSS_CLASSES } from '../src/utils.js';

const btnSelector = '.' + CSS_CLASSES.STARTER_BUTTON;

// `updateStartersContainer` only reads `widget.settings.starters` /
// `widget.startersVisible` / `widget.startersContainer` and wires clicks to
// `widget.sendUserMessage`, so we exercise it on a plain stub instead of
// booting the whole widget.
function buildWidget(starters) {
  const sent = [];
  const widget = {
    startersContainer: document.createElement('div'),
    startersVisible: true,
    settings: { starters },
    sendUserMessage: text => sent.push(text),
    hideStarters() {}
  };
  return { widget, sent };
}

describe('updateStartersContainer', () => {
  it('renders the text of { text, icon } starters, not [object Object]', () => {
    const { widget } = buildWidget([
      { text: 'Track my order', icon: 'Sparkles' },
      { text: 'Talk to a human', icon: null }
    ]);

    updateStartersContainer(widget);

    const labels = [...widget.startersContainer.querySelectorAll(btnSelector)].map(b => b.textContent);
    expect(labels).toEqual(['Track my order', 'Talk to a human']);
  });

  it('sends the starter text (not the object) on click', () => {
    const { widget, sent } = buildWidget([{ text: 'Track my order', icon: 'Sparkles' }]);

    updateStartersContainer(widget);
    widget.startersContainer.querySelector(btnSelector).click();

    expect(sent).toEqual(['Track my order']);
  });

  it('still supports legacy plain-string starters', () => {
    const { widget, sent } = buildWidget(['Hello there']);

    updateStartersContainer(widget);
    const btn = widget.startersContainer.querySelector(btnSelector);
    btn.click();

    expect(btn.textContent).toBe('Hello there');
    expect(sent).toEqual(['Hello there']);
  });

  it('skips starters with empty text', () => {
    const { widget } = buildWidget([
      { text: 'Real one', icon: null },
      { text: '', icon: 'Sparkles' }
    ]);

    updateStartersContainer(widget);

    expect(widget.startersContainer.querySelectorAll(btnSelector)).toHaveLength(1);
  });
});
