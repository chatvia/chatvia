/**
 * App-wide event bus for the hosted chat surface. Created once in app.ts and
 * provided as `chatEventBus`, so the header, sidebar and chat page can share
 * the "expanded" layout flag and announce new conversations without prop
 * drilling. Read it in components through `useChatEventBus()`.
 */
import { safeStorage } from '@/lib/safeStorage';
import mitt from 'mitt';
import { inject, ref, type Ref } from 'vue';

type ChatEvents = {
    toggleChatExpand: boolean;
    conversationCreated: string;
};

export interface ChatEventBus {
    isChatExpanded: Ref<boolean>;
    toggleChatExpand: () => boolean;
    onChatExpandToggle: (callback: (expanded: boolean) => void) => void;
    offChatExpandToggle: (callback?: (expanded: boolean) => void) => void;
    emitConversationCreated: (conversationId: string) => void;
    onConversationCreated: (callback: (conversationId: string) => void) => void;
    offConversationCreated: (callback?: (conversationId: string) => void) => void;
}

const EXPANDED_STORAGE_KEY = 'isChatExpanded';

export function createChatEventBus(): ChatEventBus {
    const emitter = mitt<ChatEvents>();
    // Created at app boot, so storage access must not throw (see safeStorage).
    const isChatExpanded = ref(safeStorage.get(EXPANDED_STORAGE_KEY) === 'true');
    const emittedConversations = new Set<string>();

    return {
        isChatExpanded,
        toggleChatExpand() {
            isChatExpanded.value = !isChatExpanded.value;
            emitter.emit('toggleChatExpand', isChatExpanded.value);
            safeStorage.set(EXPANDED_STORAGE_KEY, isChatExpanded.value.toString());
            return isChatExpanded.value;
        },
        onChatExpandToggle(callback) {
            emitter.on('toggleChatExpand', callback);
        },
        offChatExpandToggle(callback) {
            emitter.off('toggleChatExpand', callback);
        },
        emitConversationCreated(conversationId) {
            // Emit each conversation once, even if several callers report it.
            if (emittedConversations.has(conversationId)) return;
            emittedConversations.add(conversationId);
            emitter.emit('conversationCreated', conversationId);
        },
        onConversationCreated(callback) {
            emitter.on('conversationCreated', callback);
        },
        offConversationCreated(callback) {
            emitter.off('conversationCreated', callback);
        },
    };
}

/** The bus provided by app.ts. Every chat surface is mounted under it. */
export function useChatEventBus(): ChatEventBus {
    const bus = inject<ChatEventBus>('chatEventBus');
    if (!bus) {
        throw new Error('chatEventBus is not provided; it is registered in app.ts.');
    }

    return bus;
}
