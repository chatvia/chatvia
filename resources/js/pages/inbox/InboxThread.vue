<script lang="ts" setup>
/**
 * The Inbox's open chat: who it is, whether they are online or typing, the
 * messages, and the reply box. Sending takes the chat from the AI; "Give back
 * to AI" hands it back. A chat that cannot be joined (not on the Chat Widget,
 * live chat switched off, no permission, or a colleague holds it) shows a
 * note in place of the reply box.
 */
import { chatLogUrl } from '@/api/chatLog';
import HandoffComposer from '@/components/handoff/HandoffComposer.vue';
import HandoffMessageBubble from '@/components/handoff/HandoffMessageBubble.vue';
import TypingDots from '@/components/handoff/TypingDots.vue';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Skeleton } from '@/components/ui/skeleton';
import { getInitials } from '@/composables/useInitials';
import { getSourceDisplayName } from '@/pages/agents/ChatLog/chatLogFormat';
import { Link } from '@inertiajs/vue3';
import { ArrowLeft, Bot, ExternalLink, Info, LoaderCircle, MessagesSquare, PhoneOff } from 'lucide-vue-next';
import { computed, nextTick, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { injectInbox } from './useInbox';

const emit = defineEmits<{
    back: [];
}>();

const { t } = useI18n();

const {
    selected,
    messages,
    hasEarlierMessages,
    isLoadingMessages,
    replyText,
    handoff,
    heldByMe,
    heldByOther,
    canReply,
    loadEarlierMessages,
    send,
    giveBackToAi,
    sendTyping,
} = injectInbox();

const { isLoading, isSendingMessage, error, userIsOnline, userIsTyping, handoffState } = handoff;

const scrollAnchor = ref<HTMLElement | null>(null);

const name = computed(() => selected.value?.visitor_name || t('inbox.anonymous_visitor'));

// Live presence only exists on the widget; elsewhere the list's recent activity says it.
const isOnline = computed(() => (selected.value?.source === 'widget' ? userIsOnline.value : (selected.value?.is_online ?? false)));

const readOnlyNote = computed(() => {
    const row = selected.value;
    if (!row || canReply.value) return null;
    if (heldByOther.value) return t('inbox.thread.held_by_other', { name: handoffState.value.adminName || t('inbox.thread.a_colleague') });
    if (row.source !== 'widget') return t('inbox.thread.widget_only', { surface: getSourceDisplayName(row.source) });

    return t('inbox.thread.cannot_join');
});

const statusLabel = computed(() => {
    if (heldByMe.value) return t('inbox.thread.status_you');
    if (handoffState.value.status === 'pending') return t('inbox.status.waiting');

    return t('inbox.thread.status_ai');
});

function scrollToBottom() {
    nextTick(() => scrollAnchor.value?.scrollIntoView({ block: 'end' }));
}

watch(() => messages.value.length, scrollToBottom);
watch(userIsTyping, (typing) => typing && scrollToBottom());
</script>

<template>
    <div v-if="!selected" class="text-muted-foreground flex h-full flex-col items-center justify-center p-8 text-center">
        <MessagesSquare class="mb-3 h-12 w-12 opacity-30" />
        <p class="text-foreground text-sm font-medium">{{ t('inbox.thread.pick_title') }}</p>
        <p class="mt-1 max-w-xs text-xs">{{ t('inbox.thread.pick_hint') }}</p>
    </div>

    <div v-else class="flex h-full flex-col">
        <!-- Header -->
        <div class="flex items-center gap-3 border-b px-3 py-2.5 sm:px-4">
            <Button variant="ghost" size="icon" class="h-8 w-8 shrink-0 lg:hidden" :aria-label="t('inbox.thread.back')" @click="emit('back')">
                <ArrowLeft class="h-4 w-4 rtl:rotate-180" />
            </Button>

            <div class="relative shrink-0">
                <Avatar class="h-9 w-9">
                    <AvatarFallback class="text-xs">{{ getInitials(name) }}</AvatarFallback>
                </Avatar>
                <span v-if="isOnline" class="ring-background absolute end-0 bottom-0 h-2.5 w-2.5 rounded-full bg-green-500 ring-2" />
            </div>

            <div class="min-w-0 flex-1">
                <div class="flex items-center gap-2">
                    <span class="truncate text-sm font-semibold">{{ name }}</span>
                    <Badge
                        variant="outline"
                        class="shrink-0 px-1.5 py-0 text-[10px]"
                        :class="heldByMe ? 'border-amber-500 text-amber-600' : handoffState.status === 'pending' ? 'border-red-500 text-red-600' : ''"
                    >
                        {{ statusLabel }}
                    </Badge>
                </div>
                <div class="text-muted-foreground flex items-center gap-1.5 text-xs">
                    <template v-if="userIsTyping">
                        <TypingDots />
                        <span>{{ t('agents.chatlog.handoff.typing') }}</span>
                    </template>
                    <template v-else>
                        <span>{{ isOnline ? t('inbox.online') : t('inbox.offline') }}</span>
                        <span aria-hidden="true">·</span>
                        <span class="truncate">{{ selected.agent.name }}</span>
                        <span v-if="selected.visitor_email" class="hidden truncate sm:inline">· {{ selected.visitor_email }}</span>
                    </template>
                </div>
            </div>

            <Button v-if="heldByMe" size="sm" variant="outline" class="shrink-0 gap-1.5" :disabled="isLoading" @click="giveBackToAi">
                <LoaderCircle v-if="isLoading" class="h-3.5 w-3.5 animate-spin" />
                <PhoneOff v-else class="h-3.5 w-3.5" />
                <span class="hidden sm:inline">{{ t('inbox.thread.give_back') }}</span>
            </Button>

            <Button as-child variant="ghost" size="icon" class="h-8 w-8 shrink-0">
                <Link
                    :href="chatLogUrl(selected.agent.id, selected.conversation_id)"
                    :aria-label="t('inbox.thread.open_in_chat_log')"
                    :title="t('inbox.thread.open_in_chat_log')"
                >
                    <ExternalLink class="h-4 w-4" />
                </Link>
            </Button>
        </div>

        <div v-if="error" class="bg-destructive/10 text-destructive px-4 py-2 text-sm">{{ error }}</div>

        <!-- Messages -->
        <ScrollArea class="h-0 flex-1">
            <div v-if="isLoadingMessages && messages.length === 0" class="space-y-4 px-6 py-5">
                <Skeleton class="h-12 w-2/3" />
                <Skeleton class="ms-auto h-10 w-1/2" />
                <Skeleton class="h-16 w-3/4" />
            </div>
            <div v-else class="space-y-5 px-4 py-5 sm:px-6">
                <div v-if="hasEarlierMessages" class="flex justify-center">
                    <Button variant="ghost" size="sm" class="text-xs" @click="loadEarlierMessages">{{ t('inbox.thread.load_earlier') }}</Button>
                </div>

                <div v-if="messages.length === 0" class="text-muted-foreground py-10 text-center text-sm">{{ t('inbox.no_messages') }}</div>

                <HandoffMessageBubble v-for="message in messages" :key="message.id" :message="message" />

                <div v-if="userIsTyping" class="flex justify-start">
                    <div class="text-muted-foreground bg-primary/10 flex items-center gap-2 rounded-full px-4 py-2 text-sm">
                        <span class="text-xs">{{ t('agents.chatlog.handoff.visitor_typing') }}</span>
                        <TypingDots />
                    </div>
                </div>

                <div ref="scrollAnchor" class="h-1" />
            </div>
        </ScrollArea>

        <!-- Reply -->
        <div class="shrink-0 border-t p-3 sm:p-4">
            <div v-if="readOnlyNote" class="text-muted-foreground bg-muted/50 flex items-start gap-2 rounded-md px-3 py-2.5 text-sm">
                <Info class="mt-0.5 h-4 w-4 shrink-0" />
                <span>{{ readOnlyNote }}</span>
            </div>
            <template v-else>
                <p v-if="!heldByMe" class="text-muted-foreground mb-2 flex items-center gap-1.5 text-xs">
                    <Bot class="h-3.5 w-3.5" />
                    {{ isOnline ? t('inbox.thread.send_takes_over') : t('inbox.thread.send_takes_over_offline') }}
                </p>
                <HandoffComposer
                    v-model="replyText"
                    :sending="isSendingMessage || isLoading"
                    :placeholder="t('inbox.thread.reply_placeholder', { name })"
                    @send="send"
                    @typing="sendTyping"
                />
            </template>
        </div>
    </div>
</template>
