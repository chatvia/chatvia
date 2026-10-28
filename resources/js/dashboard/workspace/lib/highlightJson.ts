/**
 * JSON syntax highlighting with highlight.js, shared by the JSON editor and the
 * connector tool schemas. Only the JSON grammar is registered, so the bundle
 * stays small. The result is HTML for `v-html`: highlight.js escapes the input
 * itself, and the fallback escapes it by hand.
 */
import hljs from 'highlight.js/lib/core';
import json from 'highlight.js/lib/languages/json';

hljs.registerLanguage('json', json);

function escapeHtml(source: string): string {
    return source.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}

/** Highlighted HTML for a JSON source string (escaped plain text if highlighting fails). */
export function highlightJson(source: string): string {
    try {
        return hljs.highlight(source, { language: 'json', ignoreIllegals: true }).value;
    } catch {
        return escapeHtml(source);
    }
}
