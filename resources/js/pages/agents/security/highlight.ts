/**
 * Syntax highlighting for the sign-in token code samples. Uses the highlight.js
 * core build with only the languages the samples need, registered once when
 * this module loads, to keep the page bundle small.
 */
import hljs from 'highlight.js/lib/core';
import cpp from 'highlight.js/lib/languages/cpp';
import csharp from 'highlight.js/lib/languages/csharp';
import go from 'highlight.js/lib/languages/go';
import java from 'highlight.js/lib/languages/java';
import javascript from 'highlight.js/lib/languages/javascript';
import kotlin from 'highlight.js/lib/languages/kotlin';
import php from 'highlight.js/lib/languages/php';
import python from 'highlight.js/lib/languages/python';
import rust from 'highlight.js/lib/languages/rust';
import swift from 'highlight.js/lib/languages/swift';
import xml from 'highlight.js/lib/languages/xml';
import type { TokenExample } from './Identity.examples';

/** A token example plus its highlighted HTML, ready to render. */
export type RenderedExample = TokenExample & { highlighted: string };

hljs.registerLanguage('cpp', cpp);
hljs.registerLanguage('csharp', csharp);
hljs.registerLanguage('go', go);
hljs.registerLanguage('java', java);
hljs.registerLanguage('javascript', javascript);
hljs.registerLanguage('kotlin', kotlin);
hljs.registerLanguage('php', php);
hljs.registerLanguage('python', python);
hljs.registerLanguage('rust', rust);
hljs.registerLanguage('swift', swift);
hljs.registerLanguage('xml', xml);

/** The highlighted HTML for `code` in a registered `language`. */
export const highlight = (code: string, language: string): string => hljs.highlight(code, { language, ignoreIllegals: true }).value;
