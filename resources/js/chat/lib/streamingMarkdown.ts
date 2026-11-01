/**
 * Markdown for an answer that is still streaming. The text so far often ends
 * inside a marker (`**bold`, `` `code``, `[link](https://exa`), which marked
 * shows as plain characters until the closing half arrives, so the stars
 * flash on screen and then vanish. Closing the open markers first renders
 * the text styled from its first letter. A half-written link shows only its
 * text and a half-written image nothing, until each is complete.
 */
import remend, { INCOMPLETE_IMAGE_PLACEHOLDER } from 'remend';

const INCOMPLETE_IMAGE = new RegExp(`!\\[[^\\]]*\\]\\(${INCOMPLETE_IMAGE_PLACEHOLDER}\\)`, 'g');

/** The streamed text with its open markers closed. Only for text still arriving. */
export function closeOpenMarkdown(content: string): string {
    return remend(content, { linkMode: 'text-only' }).replace(INCOMPLETE_IMAGE, '');
}
