import mitt from 'mitt';
import { ref } from 'vue';

export function createChatEventBus() {
    const emitter = mitt();
    const storedExpandState = localStorage.getItem('isChatExpanded') === 'true';
    const isChatExpanded = ref(storedExpandState || false);
    const emittedConversations = new Set();

    return {
        emitter,
        isChatExpanded,
        toggleChatExpand() {
            this.isChatExpanded.value = !this.isChatExpanded.value;
            this.emitter.emit('toggleChatExpand', this.isChatExpanded.value);
            localStorage.setItem('isChatExpanded', this.isChatExpanded.value.toString());
            console.log('Event emitted:', this.isChatExpanded.value);
            return this.isChatExpanded.value;
        },
        onChatExpandToggle(callback) {
            console.log('Registering event listener for toggleChatExpand');
            this.emitter.on('toggleChatExpand', callback);
        },
        offChatExpandToggle(callback) {
            this.emitter.off('toggleChatExpand', callback);
        },
        emitConversationCreated(conversation) {
            const conversationId = conversation;
            if (!emittedConversations.has(conversationId)) {
                emittedConversations.add(conversationId);
                this.emitter.emit('conversationCreated', conversation);
                console.log('Conversation created event emitted for ID:', conversationId);
            } else {
                console.log('Skipping duplicate event emission for conversation ID:', conversationId);
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
