import { describe, it, expect, beforeAll } from 'vitest';
import { ChatWidget } from '../src/widget.js';
import { initI18n } from '../src/i18n.js';
import { CSS_CLASSES } from '../src/utils.js';

// `_createInputContainer` only reads `this.settings` / `this.options` /
// `this.attachments`, so we exercise it on a bare prototype instance instead
// of booting the whole widget (which needs network, storage, sessions…).
function buildComposer(settings) {
  const stub = Object.create(ChatWidget.prototype);
  stub.settings = settings;
  stub.options = {};
  stub.attachments = [];
  return stub._createInputContainer();
}

const attachSelector = '.' + CSS_CLASSES.ATTACH_BUTTON;

describe('composer file-upload gate', () => {
  beforeAll(async () => {
    await initI18n({ language: 'en' });
  });

  it('shows the paperclip when allow_file_uploads is true', () => {
    const composer = buildComposer({ allow_file_uploads: true });
    expect(composer.querySelector(attachSelector)).not.toBeNull();
    expect(composer.querySelector('input[type="file"]')).not.toBeNull();
  });

  it('hides the paperclip and the file input when allow_file_uploads is false', () => {
    const composer = buildComposer({ allow_file_uploads: false });
    expect(composer.querySelector(attachSelector)).toBeNull();
    expect(composer.querySelector('input[type="file"]')).toBeNull();
  });

  it('defaults to showing the paperclip when the flag is absent', () => {
    // Backend defaults allow_file_uploads to true, so a missing flag must not
    // silently disable uploads.
    const composer = buildComposer({});
    expect(composer.querySelector(attachSelector)).not.toBeNull();
  });
});
