<script lang="ts" setup>
/**
 * One message of a live chat as staff see it: the visitor on the end side,
 * the AI and the human operator on the start side (the operator in amber,
 * with a sent/read mark). Shared by the Chat Log's Live Chat tab and the Inbox.
 */
import type { ChatLogMessage } from '@/api/chatLog';
import MessageCard from '@/components/chat/MessageCard.vue';
import { useTimeAgo } from '@/composables/useTimeAgo';
import { Bot, CheckCheck, User } from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps<{
    message: ChatLogMessage;
}>();

const { t } = useI18n();
const { formatTimeAgo } = useTimeAgo();

const roleLabel = computed(() => {
    if (props.message.role === 'user') return t('agents.chatlog.handoff.role_visitor');

    return props.message.role === 'agent' ? t('agents.chatlog.handoff.role_you') : t('agents.chatlog.handoff.role_ai');
});
</script>

<template>
    <div class="flex" :class="message.role === 'user' ? 'justify-end' : 'justify-start'">
        <div class="flex max-w-[75%] flex-col gap-2" :class="message.role === 'user' ? 'items-end' : 'items-start'">
            <div class="text-muted-foreground flex items-center gap-1.5 px-1 text-[10px]">
                <User v-if="message.role === 'user'" class="h-3 w-3" />
                <Bot v-else-if="message.role === 'assistant'" class="h-3 w-3" />
                <User v-else class="h-3 w-3 text-amber-500" />
                <span :class="message.role === 'agent' ? 'font-medium text-amber-600' : ''">{{ roleLabel }}</span>
            </div>

            <div
                class="rounded-2xl px-4 py-3"
                :class="{
                    'bg-primary text-primary-foreground rounded-ee-md': message.role === 'user',
                    'bg-muted/60 rounded-es-md': message.role === 'assistant',
                    'rounded-es-md border border-amber-200 bg-amber-100 dark:border-amber-800 dark:bg-amber-900/30': message.role === 'agent',
                }"
            >
                <div v-if="message.role === 'user'" class="text-base leading-relaxed break-words whitespace-pre-wrap">
                    {{ message.content }}
                </div>
                <MessageCard v-else :message="message" />
            </div>

            <div class="text-muted-foreground flex items-center gap-1.5 px-1 text-[10px]">
                <span>{{ formatTimeAgo(message.created_at) }}</span>
                <span v-if="message.role === 'agent'" class="flex items-center gap-0.5" :class="message.read_at ? 'text-primary' : ''">
                    <CheckCheck class="h-3 w-3" />
                    <span class="text-[9px]">{{ message.read_at ? t('agents.chatlog.handoff.read') : t('agents.chatlog.handoff.sent') }}</span>
                </span>
            </div>
        </div>
    </div>
</template>
