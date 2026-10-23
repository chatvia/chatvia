<script lang="ts" setup>
/**
 * The operator's reply box in a live chat. Enter sends, Shift+Enter adds a
 * line. It reports typing as `typing(true)` on the first keystroke and
 * `typing(false)` after a short pause; the parent decides whether to forward
 * it. The text is a v-model, so the parent can put it back when a send fails.
 */
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { LoaderCircle, Send } from 'lucide-vue-next';
import { onUnmounted } from 'vue';
import { useI18n } from 'vue-i18n';

/** How long after the last keystroke the operator counts as "stopped typing". */
const TYPING_IDLE_MS = 1000;

const props = defineProps<{
    sending: boolean;
    disabled?: boolean;
    placeholder?: string;
}>();

const emit = defineEmits<{
    send: [content: string];
    typing: [isTyping: boolean];
}>();

const text = defineModel<string>({ default: '' });

const { t } = useI18n();

let isTyping = false;
let typingDebounce: ReturnType<typeof setTimeout> | null = null;

function stopTyping() {
    if (typingDebounce) clearTimeout(typingDebounce);
    typingDebounce = null;

    if (isTyping) {
        isTyping = false;
        emit('typing', false);
    }
}

function handleInput() {
    if (!isTyping) {
        isTyping = true;
        emit('typing', true);
    }

    if (typingDebounce) clearTimeout(typingDebounce);
    typingDebounce = setTimeout(stopTyping, TYPING_IDLE_MS);
}

function submit() {
    const content = text.value.trim();
    if (!content || props.sending || props.disabled) return;

    stopTyping();
    text.value = '';
    emit('send', content);
}

onUnmounted(stopTyping);
</script>

<template>
    <form @submit.prevent="submit" class="flex gap-2">
        <Textarea
            v-model="text"
            :placeholder="placeholder ?? t('agents.chatlog.handoff.message_placeholder')"
            :disabled="disabled"
            class="max-h-32 min-h-[44px] flex-1 resize-none"
            rows="1"
            @input="handleInput"
            @keydown.enter.exact.prevent="submit"
        />
        <Button
            type="submit"
            size="icon"
            class="h-11 w-11 shrink-0"
            :disabled="!text.trim() || sending || disabled"
            :aria-label="t('agents.chatlog.handoff.send_message')"
        >
            <LoaderCircle v-if="sending" class="h-4 w-4 animate-spin" />
            <Send v-else class="h-4 w-4" />
        </Button>
    </form>
</template>
