import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import {
  pinMessageToTop,
  maintainPin,
  followStream,
  scrollToBottom
} from '../src/state/controls.js';
import { CSS_CLASSES } from '../src/utils.js';

// ChatGPT-style "pin the new question to the top" relies on geometry jsdom can't
// lay out, so we mock the messages container's layout metrics and assert the
// reserved-spacer math + the scroll target. PIN_TOP_GAP (16px, matching the
// container's top padding) is baked into the expected numbers below.
const PIN_TOP_GAP = 16;

// `anchorContentOffset` is the anchor's fixed position within the scrollable
// content (distance from the top of all content to the anchor's top). Its
// on-screen rect.top therefore moves inversely with scrollTop — exactly like a
// real element — so re-assert logic that reads getBoundingClientRect() behaves
// correctly as scrollTop changes.
function makeWidget({ scrollTop, anchorContentOffset, containerTop, clientHeight, contentHeight }) {
  const container = document.createElement('div');
  document.body.appendChild(container);

  const spacer = document.createElement('div');
  spacer.className = CSS_CLASSES.SCROLL_SPACER;
  Object.defineProperty(spacer, 'offsetHeight', {
    get() {
      return parseFloat(this.style.height) || 0;
    }
  });
  container.appendChild(spacer);

  Object.defineProperty(container, 'clientHeight', { get: () => clientHeight });
  Object.defineProperty(container, 'scrollHeight', {
    get: () => contentHeight + spacer.offsetHeight,
    configurable: true
  });
  container.getBoundingClientRect = () => ({ top: containerTop });
  container.scrollTo = vi.fn(opts => {
    container.scrollTop = opts.top;
  });
  container.scrollTop = scrollTop;

  const anchor = document.createElement('div');
  anchor.className = `${CSS_CLASSES.MESSAGE} ${CSS_CLASSES.MESSAGE_USER}`;
  // rect.top = containerTop + (contentOffset - scrollTop)
  anchor.getBoundingClientRect = () => ({
    top: containerTop + (anchorContentOffset - container.scrollTop)
  });
  container.insertBefore(anchor, spacer);

  return { messagesContainer: container, _scrollSpacer: spacer, anchor };
}

describe('scroll pin (ChatGPT-style top anchor)', () => {
  beforeEach(() => {
    // Run rAF callbacks synchronously so assertions don't have to await frames.
    vi.stubGlobal('requestAnimationFrame', cb => {
      cb();
      return 1;
    });
    vi.stubGlobal('cancelAnimationFrame', () => {});
    // jsdom doesn't implement scrollIntoView (scrollToBottom's fallback path).
    Element.prototype.scrollIntoView = vi.fn();
  });

  afterEach(() => {
    vi.unstubAllGlobals();
    document.body.innerHTML = '';
  });

  it('reserves a spacer and instant-scrolls the message to the top', () => {
    // anchorContentOffset 1000 → desiredScrollTop = 1000 - 16 = 984
    // spacerHeight = max(0, 984 + 500 - 1050) = 434
    const widget = makeWidget({
      scrollTop: 600,
      anchorContentOffset: 1000,
      containerTop: 0,
      clientHeight: 500,
      contentHeight: 1050
    });

    pinMessageToTop(widget, widget.anchor);

    expect(widget._pinActive).toBe(true);
    expect(widget._pinReleased).toBe(false);
    expect(widget._scrollAnchorEl).toBe(widget.anchor);
    expect(widget._scrollSpacer.style.height).toBe('434px');
    // Instant, never smooth — a smooth scroll gets cancelled by streaming mutation.
    expect(widget.messagesContainer.scrollTo).toHaveBeenCalledWith({
      top: 1000 - PIN_TOP_GAP,
      behavior: 'instant'
    });
    expect(widget.messagesContainer.scrollTop).toBe(984);
  });

  it('shrinks the spacer as the reply grows and re-asserts the pin', () => {
    const widget = makeWidget({
      scrollTop: 984,
      anchorContentOffset: 1000,
      containerTop: 0,
      clientHeight: 500,
      contentHeight: 1300 // reply has grown
    });
    widget._pinActive = true;
    widget._pinReleased = false;
    widget._scrollAnchorEl = widget.anchor;
    widget._scrollSpacer.style.height = '434px';

    maintainPin(widget);

    // desiredScrollTop still 984; spacerHeight = max(0, 984 + 500 - 1300) = 184
    expect(widget._scrollSpacer.style.height).toBe('184px');
    // Held (not released) → re-asserts the scroll so finalize can't nudge it.
    expect(widget.messagesContainer.scrollTo).toHaveBeenCalledWith({
      top: 984,
      behavior: 'instant'
    });
  });

  it('does not re-assert the scroll once the visitor has scrolled away', () => {
    // A wheel/touch sets _pinReleased (see setupScrollListener); maintainPin must
    // then only keep the spacer sized and never yank the visitor back.
    const widget = makeWidget({
      scrollTop: 300, // visitor has scrolled up to read earlier messages
      anchorContentOffset: 1000,
      containerTop: 0,
      clientHeight: 500,
      contentHeight: 1300
    });
    widget._pinActive = true;
    widget._pinReleased = true; // gesture already released it
    widget._scrollAnchorEl = widget.anchor;
    widget._scrollSpacer.style.height = '184px';
    widget.messagesContainer.scrollTo.mockClear();

    maintainPin(widget);

    // Spacer still maintained, but no scroll re-assert.
    expect(widget._scrollSpacer.style.height).toBe('184px');
    expect(widget.messagesContainer.scrollTo).not.toHaveBeenCalled();
  });

  it('scrollToBottom releases the pin and collapses the spacer', () => {
    const widget = makeWidget({
      scrollTop: 0,
      anchorContentOffset: 0,
      containerTop: 0,
      clientHeight: 500,
      contentHeight: 1050
    });
    widget._pinActive = true;
    widget._pinReleased = false;
    widget._scrollAnchorEl = widget.anchor;
    widget._scrollSpacer.style.height = '434px';

    scrollToBottom(widget);

    expect(widget._pinActive).toBe(false);
    expect(widget._pinReleased).toBe(false);
    expect(widget._scrollAnchorEl).toBeNull();
    expect(widget._scrollSpacer.style.height).toBe('0px');
  });

  it('followStream re-asserts an active pin but falls back to bottom otherwise', () => {
    const widget = makeWidget({
      scrollTop: 600,
      anchorContentOffset: 1000,
      containerTop: 0,
      clientHeight: 500,
      contentHeight: 1050
    });

    // No active pin → behaves like scrollToBottom (snaps the container down).
    followStream(widget);
    expect(widget._pinActive).toBe(false);
    expect(widget.messagesContainer.scrollTop).toBe(widget.messagesContainer.scrollHeight);

    // Active pin, held → re-asserts the scroll to the pin target.
    widget._pinActive = true;
    widget._pinReleased = false;
    widget._scrollAnchorEl = widget.anchor;
    widget.messagesContainer.scrollTop = 984;
    widget.messagesContainer.scrollTo.mockClear();
    followStream(widget);
    expect(widget.messagesContainer.scrollTo).toHaveBeenCalledWith({
      top: 984,
      behavior: 'instant'
    });
  });
});
