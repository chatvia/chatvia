/**
 * The current page's CSRF token, from the `csrfToken` page prop.
 *
 * Requests send it as X-CSRF-TOKEN instead of reading the XSRF-TOKEN cookie:
 * on a hosted chat page (/chat/...) the browser holds two such cookies, the
 * dashboard's and the chat's own session's, so the cookie is ambiguous. app.ts
 * keeps the token current on every page visit.
 */
let token: string | null = null;

export function setCsrfToken(value: unknown): void {
    token = typeof value === 'string' && value !== '' ? value : null;
}

export function csrfHeaders(): Record<string, string> {
    return token ? { 'X-CSRF-TOKEN': token } : {};
}
