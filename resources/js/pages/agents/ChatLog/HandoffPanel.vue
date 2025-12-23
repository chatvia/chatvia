<script lang="ts" setup>
import MessageCard from '@/components/chat/MessageCard.vue';
import LockedFeature from '@/components/LockedFeature.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Textarea } from '@/components/ui/textarea';
import { useCapabilityGuard } from '@/composables/useCapabilityGuard';
import { Bot, Circle, LoaderCircle, MessageSquare, Phone, PhoneOff, Send, User } from 'lucide-vue-next';
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import { useChatLog } from './useChatLog';

const { currentConversationId, messagesData, messages, handoff, fetchMessages, appendMessage, formatTimeAgo } = useChatLog();

const { hasCapability } = useCapabilityGuard();
const hasAccess = computed(() => hasCapability('handoff'));

const messageInput = ref('');
const messagesContainer = ref<any>(null);
const scrollAnchor = ref<HTMLElement | null>(null);
const isTyping = ref(false);
let typingDebounce: ReturnType<typeof setTimeout> | null = null;

// Handle message input typing
function handleTyping() {
    if (!handoff.isHandedOff.value) return;

    if (!isTyping.value) {
        isTyping.value = true;
        handoff.sendTyping(true);
    }

    if (typingDebounce) clearTimeout(typingDebounce);
    typingDebounce = setTimeout(() => {
        isTyping.value = false;
        handoff.sendTyping(false);
    }, 1000);
}

// Send message
async function sendMessage() {
    if (!messageInput.value.trim() || handoff.isSendingMessage.value) return;

    const content = messageInput.value.trim();
    messageInput.value = '';

    try {
        await handoff.sendMessage(content);
        // Refresh messages to show the new one
        if (currentConversationId.value) {
            await fetchMessages(currentConversationId.value);
            scrollToBottom();
        }
    } catch (err) {
        // Error already handled in composable
        messageInput.value = content; // Restore message on error
    }
}

// Take over conversation
async function handleTakeOver() {
    try {
        await handoff.takeOver();
        // Refresh messages
        if (currentConversationId.value) {
            await fetchMessages(currentConversationId.value);
            scrollToBottom();
        }
    } catch (err) {
        // Error handled in composable
    }
}

// Return to AI
async function handleReturn() {
    try {
        await handoff.returnToAi();
    } catch (err) {
        // Error handled in composable
    }
}

function scrollToBottom() {
    nextTick(() => {
        // Use scroll anchor - most reliable method
        if (scrollAnchor.value) {
            scrollAnchor.value.scrollIntoView({ behavior: 'smooth', block: 'end' });
        }
    });
}

// Watch for new messages from WebSocket
watch(
    () => handoff.newMessageReceived.value,
    (newMsg) => {
        if (newMsg && currentConversationId.value) {
            console.log('[HandoffPanel] New message received via WebSocket:', newMsg);
            // Append message directly to the local state (no server refetch needed)
            appendMessage(newMsg);
            scrollToBottom();

            // Mark user messages as read when we receive them
            if (newMsg.role === 'user') {
                handoff.markMessagesAsRead();
            }
        }
    },
);

// Watch for messages count change
watch(
    () => messages.value.length,
    () => {
        scrollToBottom();
    },
);

// Watch for typing state changes to scroll
watch(
    () => handoff.userIsTyping.value,
    (isTyping) => {
        if (isTyping) {
            scrollToBottom();
        }
    },
);

// Scroll to bottom on mount
onMounted(() => {
    setTimeout(scrollToBottom, 100);
});
</script>

<template>
    <LockedFeature
        v-if="!hasAccess"
        description="Upgrade your plan to access live chat handoff and take over conversations from AI to chat directly with users."
    />

    <div v-else class="flex h-full flex-col">
        <!-- Status Bar -->
        <div class="bg-muted/30 flex items-center justify-between gap-3 border-b px-4 py-3">
            <div class="flex items-center gap-3">
                <!-- Online Status -->
                <div class="flex items-center gap-2">
                    <Circle class="h-2.5 w-2.5 fill-current" :class="handoff.userIsOnline.value ? 'text-green-500' : 'text-gray-400'" />
                    <span class="text-sm font-medium">
                        {{ handoff.onlineUser.value?.name || 'Visitor' }}
                    </span>
                    <Badge :variant="handoff.userIsOnline.value ? 'default' : 'secondary'" class="px-1.5 py-0 text-[10px]">
                        {{ handoff.userIsOnline.value ? 'Online' : 'Offline' }}
                    </Badge>
                </div>

                <!-- Typing Indicator -->
                <div v-if="handoff.userIsTyping.value" class="text-muted-foreground flex animate-pulse items-center gap-1.5 text-xs">
                    <span class="flex gap-0.5">
                        <span class="h-1.5 w-1.5 animate-bounce rounded-full bg-current" style="animation-delay: 0ms"></span>
                        <span class="h-1.5 w-1.5 animate-bounce rounded-full bg-current" style="animation-delay: 150ms"></span>
                        <span class="h-1.5 w-1.5 animate-bounce rounded-full bg-current" style="animation-delay: 300ms"></span>
                    </span>
                    <span>typing...</span>
                </div>
            </div>

            <!-- Handoff Status & Actions -->
            <div class="flex items-center gap-2">
                <Badge
                    :variant="handoff.isHandedOff.value ? 'default' : 'outline'"
                    :class="handoff.isHandedOff.value ? 'bg-amber-500 hover:bg-amber-600' : ''"
                >
                    {{ handoff.isHandedOff.value ? 'Live Chat Active' : 'AI Mode' }}
                </Badge>

                <Button
                    v-if="!handoff.isHandedOff.value"
                    size="sm"
                    @click="handleTakeOver"
                    :disabled="handoff.isLoading.value || !currentConversationId || !handoff.userIsOnline.value"
                    class="gap-1.5"
                    :title="!handoff.userIsOnline.value ? 'User is offline' : ''"
                >
                    <LoaderCircle v-if="handoff.isLoading.value" class="h-3.5 w-3.5 animate-spin" />
                    <Phone v-else class="h-3.5 w-3.5" />
                    Take Over
                </Button>

                <Button v-else size="sm" variant="outline" @click="handleReturn" :disabled="handoff.isLoading.value" class="gap-1.5">
                    <LoaderCircle v-if="handoff.isLoading.value" class="h-3.5 w-3.5 animate-spin" />
                    <PhoneOff v-else class="h-3.5 w-3.5" />
                    Return to AI
                </Button>
            </div>
        </div>

        <!-- Error Display -->
        <div v-if="handoff.error.value" class="bg-destructive/10 text-destructive px-4 py-2 text-sm">
            {{ handoff.error.value }}
        </div>

        <!-- Empty State (not handed off) -->
        <div v-if="!handoff.isHandedOff.value" class="text-muted-foreground flex flex-1 flex-col items-center justify-center p-8">
            <div class="bg-muted mb-4 flex h-16 w-16 items-center justify-center rounded-full">
                <Bot class="h-8 w-8" />
            </div>
            <h3 class="mb-2 text-lg font-medium">AI is handling this conversation</h3>
            <p class="mb-4 max-w-md text-center text-sm">
                Click "Take Over" to join the conversation and chat directly with the user. The AI will stop responding while you're in control.
            </p>
            <div class="flex items-center gap-2 text-xs">
                <Circle class="h-2 w-2 fill-current" :class="handoff.userIsOnline.value ? 'text-green-500' : 'text-gray-400'" />
                <span>User is {{ handoff.userIsOnline.value ? 'currently online' : 'offline' }}</span>
            </div>
        </div>

        <!-- Chat Interface (handed off) -->
        <template v-else>
            <!-- Messages -->
            <ScrollArea ref="messagesContainer" class="h-0 flex-1">
                <div v-if="!currentConversationId" class="text-muted-foreground flex h-full flex-col items-center justify-center py-20">
                    <MessageSquare class="mb-3 h-10 w-10 opacity-30" />
                    <p class="text-sm">Select a conversation</p>
                </div>
                <div v-else class="space-y-5 px-6 py-5">
                    <div v-for="m in messages" :key="m.id">
                        <div class="flex" :class="m.role === 'user' ? 'justify-end' : 'justify-start'">
                            <div class="flex max-w-[75%] flex-col gap-2" :class="m.role === 'user' ? 'items-end' : 'items-start'">
                                <!-- Role indicator -->
                                <div class="text-muted-foreground flex items-center gap-1.5 px-1 text-[10px]">
                                    <User v-if="m.role === 'user'" class="h-3 w-3" />
                                    <Bot v-else-if="m.role === 'assistant'" class="h-3 w-3" />
                                    <User v-else class="h-3 w-3 text-amber-500" />
                                    <span :class="m.role === 'agent' ? 'font-medium text-amber-600' : ''">
                                        {{ m.role === 'user' ? 'Visitor' : m.role === 'agent' ? 'You' : 'AI' }}
                                    </span>
                                </div>

                                <!-- Message Bubble -->
                                <div
                                    class="rounded-2xl px-4 py-3"
                                    :class="{
                                        'bg-primary text-primary-foreground rounded-br-md': m.role === 'user',
                                        'bg-muted/60 rounded-bl-md': m.role === 'assistant',
                                        'rounded-bl-md border border-amber-200 bg-amber-100 dark:border-amber-800 dark:bg-amber-900/30':
                                            m.role === 'agent',
                                    }"
                                >
                                    <div v-if="m.role === 'user'" class="text-base leading-relaxed break-words whitespace-pre-wrap">
                                        {{ m.content }}
                                    </div>
                                    <MessageCard v-else :message="m" :hide-feedback="true" />
                                </div>

                                <!-- Time and Read Status -->
                                <div class="text-muted-foreground flex items-center gap-1.5 px-1 text-[10px]">
                                    <span>{{ formatTimeAgo(m.created_at) }}</span>
                                    <!-- Read status for agent messages -->
                                    <span v-if="m.role === 'agent'" class="flex items-center gap-0.5" :class="m.read_at ? 'text-primary' : ''">
                                        <svg
                                            xmlns="http://www.w3.org/2000/svg"
                                            class="h-3 w-3"
                                            viewBox="0 0 24 24"
                                            fill="none"
                                            stroke="currentColor"
                                            stroke-width="2.5"
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                        >
                                            <path d="M18 6L7 17l-5-5" />
                                            <path d="M22 6L11 17" />
                                        </svg>
                                        <span class="text-[9px]">{{ m.read_at ? 'Read' : 'Sent' }}</span>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- User typing indicator (visitor is typing) -->
                    <div v-if="handoff.userIsTyping.value" class="flex justify-start">
                        <div class="text-muted-foreground bg-primary/10 flex items-center gap-2 rounded-full px-4 py-2 text-sm">
                            <span class="text-xs">Visitor is typing</span>
                            <span class="flex gap-0.5">
                                <span class="h-1.5 w-1.5 animate-bounce rounded-full bg-current" style="animation-delay: 0ms"></span>
                                <span class="h-1.5 w-1.5 animate-bounce rounded-full bg-current" style="animation-delay: 150ms"></span>
                                <span class="h-1.5 w-1.5 animate-bounce rounded-full bg-current" style="animation-delay: 300ms"></span>
                            </span>
                        </div>
                    </div>

                    <!-- Scroll anchor - always at the bottom -->
                    <div ref="scrollAnchor" class="h-1" />
                </div>
            </ScrollArea>

            <!-- Message Input -->
            <div class="flex-shrink-0 border-t p-4">
                <form @submit.prevent="sendMessage" class="flex gap-2">
                    <Textarea
                        v-model="messageInput"
                        placeholder="Type your message..."
                        class="max-h-32 min-h-[44px] flex-1 resize-none"
                        rows="1"
                        @input="handleTyping"
                        @keydown.enter.exact.prevent="sendMessage"
                    />
                    <Button type="submit" size="icon" class="h-11 w-11 shrink-0" :disabled="!messageInput.trim() || handoff.isSendingMessage.value">
                        <LoaderCircle v-if="handoff.isSendingMessage.value" class="h-4 w-4 animate-spin" />
                        <Send v-else class="h-4 w-4" />
                    </Button>
                </form>
            </div>
        </template>
    </div>
</template>
