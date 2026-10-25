import DOMPurify, { type Config } from 'dompurify';

/**
 * One sanitizer for HTML built from untrusted text: assistant answers,
 * visitor messages and tool results, rendered with v-html in the widget, the
 * hosted chat and the dashboard.
 *
 * That text is attacker-influenceable (a prompt-injected knowledge chunk or
 * tool result can steer the model). DOMPurify already strips scripts and event
 * handlers, but it keeps tags and attributes that make the browser load a URL
 * on its own: <img src>, <video poster>, <audio>/<source>, <picture>, SVG
 * <image>, `srcset`, `background`, `style="background:url(…)"`. Each one is a
 * zero-click channel that sends the conversation to an attacker's host (and
 * fires again in the operator's dashboard). So the config forbids those tags
 * and attributes outright, and the hooks below limit <img> to this site and
 * drop inline styles that could load anything.
 */

/** Tags that load media, embed other documents or take input. */
export const FORBIDDEN_TAGS = [
    'video',
    'audio',
    'source',
    'picture',
    'track',
    'iframe',
    'object',
    'embed',
    'form',
    'input',
    'button',
    'style',
    // SVG elements that fetch an external image.
    'image',
    'feimage',
];

/** Attributes that load a URL without a click. */
export const FORBIDDEN_ATTRIBUTES = ['poster', 'background', 'srcset', 'formaction'];

/**
 * The DOMPurify options for untrusted HTML. `style` is kept as an attribute
 * because math (KaTeX) lays itself out with inline heights and offsets; the
 * hook below drops every style that could make a request instead.
 */
export const UNTRUSTED_HTML_CONFIG: Config = {
    ADD_ATTR: ['target'],
    FORBID_TAGS: FORBIDDEN_TAGS,
    FORBID_ATTR: FORBIDDEN_ATTRIBUTES,
};

/**
 * Whether an inline style can render without a network request. CSS loads
 * resources through url(), image(), image-set() and @import, and a backslash
 * escape can spell any of them, so any of those (or a protocol-relative `//`)
 * makes the style unsafe.
 */
export function isSafeInlineStyle(style: string): boolean {
    const compact = style.toLowerCase().replace(/\s+/g, '');

    return !/url\(|image\(|image-set\(|@import|expression\(|\\|\/\//.test(compact);
}

const allowedImageOrigins = new Set<string>();

/**
 * Whether an <img> may load from `src`: inline data: images (no request),
 * this site, or the app's own origin (chunk images are absolute to the main
 * domain even on an agent's custom domain).
 */
function isAllowedImageSource(src: string): boolean {
    if (src.startsWith('data:image/')) {
        return true;
    }

    try {
        const url = new URL(src, window.location.origin);

        return (url.protocol === 'http:' || url.protocol === 'https:') && allowedImageOrigins.has(url.origin);
    } catch {
        return false;
    }
}

let hooksInstalled = false;

/** Registers the image and style hooks once, on first use in a browser. */
function installHooks(): void {
    if (hooksInstalled || typeof window === 'undefined') {
        return;
    }

    hooksInstalled = true;
    allowedImageOrigins.add(window.location.origin);

    const appUrl = document.querySelector('meta[name="app-url"]')?.getAttribute('content');
    if (appUrl) {
        try {
            allowedImageOrigins.add(new URL(appUrl).origin);
        } catch {
            /* malformed meta: same-origin only */
        }
    }

    DOMPurify.addHook('afterSanitizeAttributes', (node) => {
        if (!(node instanceof Element)) {
            return;
        }

        const style = node.getAttribute('style');
        if (style !== null && !isSafeInlineStyle(style)) {
            node.removeAttribute('style');
        }

        if (node.tagName === 'IMG' && !isAllowedImageSource(node.getAttribute('src') ?? '')) {
            node.removeAttribute('src');
        }
    });
}

/**
 * Sanitizes HTML built from untrusted text. `forbidTags` adds tags on top of
 * the shared list (a view that should show no images at all passes `img`).
 */
export function sanitizeUntrustedHtml(html: string, forbidTags: string[] = []): string {
    installHooks();

    return DOMPurify.sanitize(html, {
        ...UNTRUSTED_HTML_CONFIG,
        FORBID_TAGS: [...FORBIDDEN_TAGS, ...forbidTags],
    });
}
