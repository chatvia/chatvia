<script lang="ts" setup>
/**
 * Live Chat tab of the chat log: the visitor's online and typing state, take
 * over / return to AI, and — once taken over — the transcript plus a reply box
 * that streams the operator's messages into the visitor's widget.
 */
import MessageCard from '@/components/chat/MessageCard.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Textarea } from '@/components/ui/textarea';
import { Bot, Circle, LoaderCircle, MessageSquare, Phone, PhoneOff, Send, User } from 'lucide-vue-next';
import { nextTick, onMounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useChatLog } from './useChatLog';

const { t } = useI18n();

const { currentConversationId, messages, handoff, fetchMessages, appendMessage, formatTimeAgo } = useChatLog();

/** How long after the last keystroke the operator counts as "stopped typing". */
const TYPING_IDLE_MS = 1000;

const messageInput = ref('');
const scrollAnchor = ref<HTMLElement | null>(null);
const isTyping = ref(false);
let typingDebounce: ReturnType<typeof setTimeout> | null = null;

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
    }, TYPING_IDLE_MS);
}

async function reloadAndScroll() {
    if (!currentConversationId.value) return;
    await fetchMessages(currentConversationId.value);
    scrollToBottom();
}

// useHandoff already shows request errors in the panel, so failures only need local handling here.
async function sendMessage() {
    if (!messageInput.value.trim() || handoff.isSendingMessage.value) return;

    const content = messageInput.value.trim();
    messageInput.value = '';

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
                    <span class="flex gap-0.5">
                        <span class="h-1.5 w-1.5 animate-bounce rounded-full bg-current" style="animation-delay: 0ms"></span>
                        <span class="h-1.5 w-1.5 animate-bounce rounded-full bg-current" style="animation-delay: 150ms"></span>
                        <span class="h-1.5 w-1.5 animate-bounce rounded-full bg-current" style="animation-delay: 300ms"></span>
                    </span>
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
                    <div v-for="m in messages" :key="m.id">
                        <div class="flex" :class="m.role === 'user' ? 'justify-end' : 'justify-start'">
                            <div class="flex max-w-[75%] flex-col gap-2" :class="m.role === 'user' ? 'items-end' : 'items-start'">
                                <!-- Role indicator -->
                                <div class="text-muted-foreground flex items-center gap-1.5 px-1 text-[10px]">
                                    <User v-if="m.role === 'user'" class="h-3 w-3" />
                                    <Bot v-else-if="m.role === 'assistant'" class="h-3 w-3" />
                                    <User v-else class="h-3 w-3 text-amber-500" />
                                    <span :class="m.role === 'agent' ? 'font-medium text-amber-600' : ''">
                                        {{
                                            m.role === 'user'
                                                ? t('agents.chatlog.handoff.role_visitor')
                                                : m.role === 'agent'
                                                  ? t('agents.chatlog.handoff.role_you')
                                                  : t('agents.chatlog.handoff.role_ai')
                                        }}
                                    </span>
                                </div>

                                <!-- Message Bubble -->
                                <div
                                    class="rounded-2xl px-4 py-3"
                                    :class="{
                                        'bg-primary text-primary-foreground rounded-ee-md': m.role === 'user',
                                        'bg-muted/60 rounded-es-md': m.role === 'assistant',
                                        'rounded-es-md border border-amber-200 bg-amber-100 dark:border-amber-800 dark:bg-amber-900/30':
                                            m.role === 'agent',
                                    }"
                                >
                                    <div v-if="m.role === 'user'" class="text-base leading-relaxed break-words whitespace-pre-wrap">
                                        {{ m.content }}
                                    </div>
                                    <MessageCard v-else :message="m" />
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
                                        <span class="text-[9px]">{{
                                            m.read_at ? t('agents.chatlog.handoff.read') : t('agents.chatlog.handoff.sent')
                                        }}</span>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- User typing indicator (visitor is typing) -->
                    <div v-if="handoff.userIsTyping.value" class="flex justify-start">
                        <div class="text-muted-foreground bg-primary/10 flex items-center gap-2 rounded-full px-4 py-2 text-sm">
                            <span class="text-xs">{{ t('agents.chatlog.handoff.visitor_typing') }}</span>
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
                        :placeholder="t('agents.chatlog.handoff.message_placeholder')"
                        class="max-h-32 min-h-[44px] flex-1 resize-none"
                        rows="1"
                        @input="handleTyping"
                        @keydown.enter.exact.prevent="sendMessage"
                    />
                    <Button
                        type="submit"
                        size="icon"
                        class="h-11 w-11 shrink-0"
                        :disabled="!messageInput.trim() || handoff.isSendingMessage.value"
                        :aria-label="t('agents.chatlog.handoff.send_message')"
                    >
                        <LoaderCircle v-if="handoff.isSendingMessage.value" class="h-4 w-4 animate-spin" />
                        <Send v-else class="h-4 w-4" />
                    </Button>
                </form>
            </div>
        </template>
    </div>
</template>
