import { safeStorage } from '@/lib/safeStorage';
import mitt from 'mitt';
import { ref } from 'vue';

export function createChatEventBus() {
    const emitter = mitt();
    // Created at app boot, so storage access must not throw (see safeStorage).
    const storedExpandState = safeStorage.get('isChatExpanded') === 'true';
    const isChatExpanded = ref(storedExpandState || false);
    const emittedConversations = new Set();

    return {
        emitter,
        isChatExpanded,
        toggleChatExpand() {
            this.isChatExpanded.value = !this.isChatExpanded.value;
            this.emitter.emit('toggleChatExpand', this.isChatExpanded.value);
            safeStorage.set('isChatExpanded', this.isChatExpanded.value.toString());
            return this.isChatExpanded.value;
        },
        onChatExpandToggle(callback) {
            this.emitter.on('toggleChatExpand', callback);
        },
        offChatExpandToggle(callback) {
            this.emitter.off('toggleChatExpand', callback);
        },
        emitConversationCreated(conversation) {
            const conversationId = conversation;
            // Emit each conversation once, even if several callers report it.
            if (!emittedConversations.has(conversationId)) {
                emittedConversations.add(conversationId);
                this.emitter.emit('conversationCreated', conversation);
            }
        },
        onConversationCreated(callback) {
            this.emitter.on('conversationCreated', callback);
        },
        offConversationCreated(callback) {
            this.emitter.off('conversationCreated', callback);
        },
    };
}
