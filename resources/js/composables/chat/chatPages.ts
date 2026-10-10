/**
 * The hosted chat has two sets of pages: the full app (`/chat`, `/chat/c/{id}`)
 * and the iframe embed (`/chat/embed`, `/chat/embed/c/{id}`). The embed pages
 * get `embedded: true` from the server; these are the "new chat" and "open a
 * conversation" URLs of whichever set the visitor is on.
 */
import chat from '@/routes/chat';
import type { RouteQueryOptions } from '@/wayfinder';

export interface ChatPages {
    home: () => string;
    show: (conversationId: string, options?: RouteQueryOptions) => string;
}

export function chatPages(embedded: boolean): ChatPages {
    const pages = embedded ? chat.embed : chat;

    return {
        home: () => pages.home.url(),
        show: (conversationId, options) => pages.show.url(conversationId, options),
    };
}
