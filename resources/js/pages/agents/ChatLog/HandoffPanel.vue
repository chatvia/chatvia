<script lang="ts" setup>
/**
 * Live Chat tab of the chat log: the visitor's online and typing state, take
 * over / return to AI, and — once taken over — the transcript plus a reply box
 * that streams the operator's messages into the visitor's widget.
 */
import HandoffComposer from '@/components/handoff/HandoffComposer.vue';
import HandoffMessageBubble from '@/components/handoff/HandoffMessageBubble.vue';
import TypingDots from '@/components/handoff/TypingDots.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Bot, Circle, LoaderCircle, MessageSquare, Phone, PhoneOff } from 'lucide-vue-next';
import { nextTick, onMounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useChatLog } from './useChatLog';

const { t } = useI18n();

const { currentConversationId, messages, handoff, fetchMessages, appendMessage } = useChatLog();

const messageInput = ref('');
const scrollAnchor = ref<HTMLElement | null>(null);

function handleTyping(isTyping: boolean) {
    if (handoff.isHandedOff.value) void handoff.sendTyping(isTyping);
}

async function reloadAndScroll() {
    if (!currentConversationId.value) return;
    await fetchMessages(currentConversationId.value);
    scrollToBottom();
}

// useHandoff already shows request errors in the panel, so failures only need local handling here.
async function sendMessage(content: string) {
    try {
        await handoff.sendMessage(content);
        await reloadAndScroll();
    } catch {
        messageInput.value = content;
    }
}

async function handleTakeOver() {
    try {
        await handoff.takeOver();
        await reloadAndScroll();
    } catch {
        // Shown by useHandoff (see above).
    }
}

async function handleReturn() {
    try {
        await handoff.returnToAi();
    } catch {
        // Shown by useHandoff (see above).
    }
}

function scrollToBottom() {
    nextTick(() => scrollAnchor.value?.scrollIntoView({ behavior: 'smooth', block: 'end' }));
}

// Messages pushed over the channel are appended locally, with no refetch.
// The visitor's own messages count as read as soon as they arrive here.
watch(
    () => handoff.newMessageReceived.value,
    (newMessage) => {
        if (!newMessage || !currentConversationId.value) return;

        appendMessage(newMessage);
        scrollToBottom();
        if (newMessage.role === 'user') void handoff.markMessagesAsRead();
    },
);

watch(() => messages.value.length, scrollToBottom);

watch(
    () => handoff.userIsTyping.value,
    (isTyping) => {
        if (isTyping) scrollToBottom();
    },
);

// Waits a moment for the panel to lay out before the first scroll.
onMounted(() => {
    setTimeout(scrollToBottom, 100);
});
</script>

<template>
    <div class="flex h-full flex-col">
        <!-- Status Bar -->
        <div class="bg-muted/30 flex items-center justify-between gap-3 border-b px-4 py-3">
            <div class="flex items-center gap-3">
                <!-- Online Status -->
                <div class="flex items-center gap-2">
                    <Circle class="h-2.5 w-2.5 fill-current" :class="handoff.userIsOnline.value ? 'text-green-500' : 'text-gray-400'" />
                    <span class="text-sm font-medium">
                        {{ handoff.onlineUser.value?.name || t('agents.chatlog.handoff.visitor') }}
                    </span>
                    <Badge :variant="handoff.userIsOnline.value ? 'default' : 'secondary'" class="px-1.5 py-0 text-[10px]">
                        {{ handoff.userIsOnline.value ? t('agents.chatlog.handoff.online') : t('agents.chatlog.handoff.offline') }}
                    </Badge>
                </div>

                <!-- Typing Indicator -->
                <div v-if="handoff.userIsTyping.value" class="text-muted-foreground flex animate-pulse items-center gap-1.5 text-xs">
                    <TypingDots />
                    <span>{{ t('agents.chatlog.handoff.typing') }}</span>
                </div>
            </div>

            <!-- Handoff Status & Actions -->
            <div class="flex items-center gap-2">
                <Badge
                    :variant="handoff.isHandedOff.value ? 'default' : 'outline'"
                    :class="handoff.isHandedOff.value ? 'bg-amber-500 hover:bg-amber-600' : ''"
                >
                    {{ handoff.isHandedOff.value ? t('agents.chatlog.handoff.live_chat_active') : t('agents.chatlog.handoff.ai_mode') }}
                </Badge>

                <Button
                    v-if="!handoff.isHandedOff.value"
                    size="sm"
                    @click="handleTakeOver"
                    :disabled="handoff.isLoading.value || !currentConversationId || !handoff.userIsOnline.value"
                    class="gap-1.5"
                    :title="!handoff.userIsOnline.value ? t('agents.chatlog.handoff.user_offline') : ''"
                >
                    <LoaderCircle v-if="handoff.isLoading.value" class="h-3.5 w-3.5 animate-spin" />
                    <Phone v-else class="h-3.5 w-3.5" />
                    {{ t('agents.chatlog.handoff.take_over') }}
                </Button>

                <Button v-else size="sm" variant="outline" @click="handleReturn" :disabled="handoff.isLoading.value" class="gap-1.5">
                    <LoaderCircle v-if="handoff.isLoading.value" class="h-3.5 w-3.5 animate-spin" />
                    <PhoneOff v-else class="h-3.5 w-3.5" />
                    {{ t('agents.chatlog.handoff.return_to_ai') }}
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
            <h3 class="mb-2 text-lg font-medium">{{ t('agents.chatlog.handoff.ai_handling_title') }}</h3>
            <p class="mb-4 max-w-md text-center text-sm">
                {{ t('agents.chatlog.handoff.ai_handling_description') }}
            </p>
            <div class="flex items-center gap-2 text-xs">
                <Circle class="h-2 w-2 fill-current" :class="handoff.userIsOnline.value ? 'text-green-500' : 'text-gray-400'" />
                <span>{{
                    handoff.userIsOnline.value
                        ? t('agents.chatlog.handoff.user_currently_online')
                        : t('agents.chatlog.handoff.user_currently_offline')
                }}</span>
            </div>
        </div>

        <!-- Chat Interface (handed off) -->
        <template v-else>
            <!-- Messages -->
            <ScrollArea class="h-0 flex-1">
                <div v-if="!currentConversationId" class="text-muted-foreground flex h-full flex-col items-center justify-center py-20">
                    <MessageSquare class="mb-3 h-10 w-10 opacity-30" />
                    <p class="text-sm">{{ t('agents.chatlog.handoff.select_conversation') }}</p>
                </div>
                <div v-else class="space-y-5 px-6 py-5">
                    <HandoffMessageBubble v-for="m in messages" :key="m.id" :message="m" />

                    <!-- User typing indicator (visitor is typing) -->
                    <div v-if="handoff.userIsTyping.value" class="flex justify-start">
                        <div class="text-muted-foreground bg-primary/10 flex items-center gap-2 rounded-full px-4 py-2 text-sm">
                            <span class="text-xs">{{ t('agents.chatlog.handoff.visitor_typing') }}</span>
                            <TypingDots />
                        </div>
                    </div>

                    <!-- Scroll anchor - always at the bottom -->
                    <div ref="scrollAnchor" class="h-1" />
                </div>
            </ScrollArea>

            <!-- Message Input -->
            <div class="flex-shrink-0 border-t p-4">
                <HandoffComposer v-model="messageInput" :sending="handoff.isSendingMessage.value" @send="sendMessage" @typing="handleTyping" />
            </div>
        </template>
    </div>
</template>
