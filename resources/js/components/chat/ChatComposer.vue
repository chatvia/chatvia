<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { useVoiceRecorder } from '@/composables/useVoiceRecorder';
import { ArrowUp, Loader2, Mic, Square } from 'lucide-vue-next';
import { nextTick, onMounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';

interface Props {
    isThinking?: boolean;
    text?: string;
    placeholder?: string;
    disabled?: boolean;
}

const props = defineProps<Props>();
const { t } = useI18n();

const userInput = ref(props.text || '');
const messageInput = ref<HTMLTextAreaElement>();
const isFocused = ref(false);

// Voice recording
const {
    isRecording,
    isTranscribing,
    isSupported: isVoiceSupported,
    startRecording,
    stopRecording,
    cancelRecording: _cancelRecording,
} = useVoiceRecorder();

watch(
    () => props.text,
    (newText) => {
        if (newText !== undefined && newText !== userInput.value) {
            userInput.value = newText;
            nextTick(() => {
                if (messageInput.value) {
                    adjustTextareaHeight();
                }
            });
        }
    },
    { immediate: true },
);

const emit = defineEmits<{
    messageAdded: [content: string];
    stopStreaming: [];
}>();

const handleEnterKey = (event: KeyboardEvent) => {
    if (!event.shiftKey) {
        event.preventDefault();
        sendMessage();
    } else {
        adjustTextareaHeight(event);
    }
};

const adjustTextareaHeight = (event?: Event) => {
    const textarea = (event?.target as HTMLTextAreaElement) || messageInput.value;
    if (!textarea || !textarea.style) return;

    textarea.style.height = 'auto';
    textarea.style.height = Math.min(160, textarea.scrollHeight) + 'px';
};

watch(userInput, () => {
    nextTick(() => {
        adjustTextareaHeight();
    });
});

const stopStreaming = () => {
    emit('stopStreaming');
};

const sendMessage = () => {
    if (!userInput.value.trim()) return;

    emit('messageAdded', userInput.value);
    userInput.value = '';

    if (messageInput.value && messageInput.value.style) {
        messageInput.value.style.height = 'auto';
    }
};

const handleFocus = () => {
    isFocused.value = true;
};

const handleBlur = () => {
    isFocused.value = false;
};

onMounted(() => {
    nextTick(() => {
        if (messageInput.value && typeof messageInput.value.focus === 'function') {
            messageInput.value.focus();
        }
    });
});

const canSend = () => userInput.value.trim() !== '' && !props.disabled;

// Voice recording handlers
const toggleRecording = async () => {
    if (isRecording.value) {
        const transcript = await stopRecording();
        if (transcript) {
            // Append transcript to existing input
            userInput.value = userInput.value ? `${userInput.value} ${transcript}` : transcript;

            nextTick(() => {
                adjustTextareaHeight();
                if (messageInput.value && typeof messageInput.value.focus === 'function') {
                    messageInput.value.focus();
                }
            });
        }
    } else {
        await startRecording();
    }
};
</script>

<template>
    <div class="w-full">
        <div
            class="bg-card relative rounded-3xl border transition-all duration-200"
            :class="[
                disabled ? 'cursor-not-allowed opacity-50' : '',
                isFocused && !disabled
                    ? 'border-primary/40 ring-primary/10 shadow-lg ring-1'
                    : 'border-gray-300 shadow-sm hover:border-gray-400 dark:border-gray-600 dark:hover:border-gray-500',
                isThinking ? 'border-primary/40' : '',
            ]"
        >
            <div class="flex items-center">
                <!-- Left side: textarea -->
                <div class="min-w-0 flex-1">
                    <Textarea
                        ref="messageInput"
                        v-model="userInput"
                        @keydown.enter="handleEnterKey"
                        @input="adjustTextareaHeight"
                        @focus="handleFocus"
                        @blur="handleBlur"
                        :placeholder="disabled ? t('composer.disabled') : placeholder || t('composer.default_placeholder')"
                        class="text-foreground placeholder:text-muted-foreground/50 max-h-[160px] min-h-[28px] w-full resize-none !border-0 !bg-transparent px-4 py-4 text-sm !shadow-none focus-visible:!border-0 focus-visible:ring-0 focus-visible:outline-none dark:!bg-transparent"
                        :disabled="isThinking || disabled"
                        rows="1"
                    />
                </div>

                <!-- Right side: Mic and Send/Stop buttons -->
                <div class="flex items-center gap-1.5 p-3">
                    <!-- Voice recording button -->
                    <Button
                        v-if="isVoiceSupported && !isThinking && !disabled"
                        @click="toggleRecording"
                        :disabled="isTranscribing"
                        size="icon"
                        variant="ghost"
                        class="h-10 w-10 shrink-0 rounded-full transition-all duration-200"
                        :class="[
                            isRecording
                                ? 'animate-pulse bg-red-500 text-white hover:bg-red-600'
                                : 'hover:bg-muted text-muted-foreground hover:text-foreground',
                        ]"
                        :title="isRecording ? t('composer.stop_recording') : t('composer.start_voice_input')"
                    >
                        <Loader2 v-if="isTranscribing" class="h-5 w-5 animate-spin" />
                        <Mic v-else class="h-5 w-5" />
                    </Button>

                    <Button v-if="isThinking" @click="stopStreaming" size="icon" variant="destructive" class="h-10 w-10 shrink-0 rounded-full">
                        <Square class="h-5 w-5" />
                    </Button>

                    <Button
                        v-else
                        @click="sendMessage"
                        :disabled="!canSend()"
                        size="icon"
                        class="h-10 w-10 shrink-0 rounded-full transition-all duration-200"
                        :class="[canSend() ? 'bg-primary text-primary-foreground hover:bg-primary/90 shadow-md' : 'bg-muted text-muted-foreground']"
                    >
                        <ArrowUp class="h-5 w-5" :class="{ 'stroke-[2.5px]': canSend() }" />
                    </Button>
                </div>
            </div>
        </div>
    </div>
</template>
