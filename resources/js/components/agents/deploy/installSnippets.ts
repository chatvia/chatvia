/**
 * The install code shown on the agent's Deploy page: the Widget and Command
 * Bar `<script>` tags and the Hosted Chat `<iframe>`. Customers paste this
 * text into their sites, so it is built in one place and kept stable.
 */

/** A settings group as the server sends it (saved values or config defaults). */
export type SurfaceSettings = Record<string, unknown> | null | undefined;

/** The saved value, else the default, else the fallback; empty strings count as unset. */
export function pickSetting(saved: SurfaceSettings, defaults: SurfaceSettings, key: string, fallback: string): string {
    const value = saved?.[key] ?? defaults?.[key];
    return value != null && value !== '' ? String(value) : fallback;
}

interface ScriptSnippetOptions {
    scriptUrl: string;
    agentId: string;
    /** Private agents need a signed token; the snippet carries a placeholder for it. */
    isProtected: boolean;
}

function scriptTag(attributes: string[], isProtected: boolean): string {
    const lines = ['<script id="chatbot-embed-script"', ...attributes];
    if (isProtected) lines.push('    data-auth="YOUR_SIGNED_TOKEN"');
    lines.push('    defer></script>');
    return lines.join('\n');
}

/** The floating chat widget, with the configured language and theme. */
export function widgetSnippet(options: ScriptSnippetOptions & { language: string; theme: string }): string {
    return scriptTag(
        [
            `    src="${options.scriptUrl}"`,
            `    data-agent-id="${options.agentId}"`,
            `    data-language="${options.language}"`,
            `    data-theme="${options.theme}"`,
        ],
        options.isProtected,
    );
}

/**
 * The Command Bar. Trigger and placement are not in the snippet: the widget
 * reads them from the agent's saved bar settings when it loads, so later
 * dashboard changes apply without pasting the code again.
 */
export function barSnippet(options: ScriptSnippetOptions): string {
    return scriptTag([`    src="${options.scriptUrl}"`, `    data-agent-id="${options.agentId}"`, '    data-mode="bar"'], options.isProtected);
}

export interface IframeOptions {
    width: string;
    height: string;
    title: string;
}

/** The Hosted Chat embedded in a page. */
export function iframeSnippet(src: string, options: IframeOptions): string {
    return `<iframe src="${src}" width="${options.width}" height="${options.height}" title="${options.title}" frameborder="0" allowfullscreen loading="lazy"></iframe>`;
}
