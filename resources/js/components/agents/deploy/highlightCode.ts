/**
 * Syntax highlighting for the code shown on the agent's Deploy page (HTML
 * install snippets and the JavaScript API example). Loads only the two
 * highlight.js languages the page needs.
 */
import hljs from 'highlight.js/lib/core';
import javascript from 'highlight.js/lib/languages/javascript';
import xml from 'highlight.js/lib/languages/xml';

hljs.registerLanguage('xml', xml);
hljs.registerLanguage('javascript', javascript);

export type CodeLanguage = 'xml' | 'javascript';

/** Highlighted HTML for `v-html`; highlight.js escapes the code itself. */
export function highlightCode(code: string, language: CodeLanguage): string {
    return hljs.highlight(code, { language, ignoreIllegals: true }).value;
}
