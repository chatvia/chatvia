<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { Send, Square } from 'lucide-vue-next';
import { nextTick, ref, watch } from 'vue';

interface Props {
    isThinking?: boolean;
    text?: string;
    placeholder?: string;
}

const props = defineProps<Props>();

const userInput = ref(props.text || '');
const messageInput = ref<HTMLTextAreaElement>();
const isComposing = ref(false);

// Watch for changes in the text prop and update userInput
watch(
    () => props.text,
    (newText) => {
        if (newText !== undefined && newText !== userInput.value) {
            userInput.value = newText;
            // Auto-adjust textarea height after text update, but only if textarea exists
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
    textarea.style.height = Math.min(200, textarea.scrollHeight) + 'px';
};

// Auto-resize on input change
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

    // Reset textarea height
    if (messageInput.value && messageInput.value.style) {
        messageInput.value.style.height = 'auto';
    }
};

// Track composition state
const handleFocus = () => {
    isComposing.value = true;
};

const handleBlur = () => {
    isComposing.value = false;
};
</script>

<template>
    <div class="relative">
        <div
            class="rounded-2xl border transition-all duration-200"
            :class="[isComposing ? 'border-primary shadow-md' : 'border-border shadow-sm hover:shadow-md', 'bg-card']"
        >
            <div class="relative">
                <Textarea
                    ref="messageInput"
                    v-model="userInput"
                    @keydown.enter="handleEnterKey"
                    @input="adjustTextareaHeight"
                    @focus="handleFocus"
                    @blur="handleBlur"
                    :placeholder="placeholder || 'Ask anything...'"
                    class="text-foreground placeholder:text-muted-foreground max-h-[200px] min-h-[60px] resize-none rounded-2xl border-0 bg-transparent px-4 py-4 pr-12 focus-visible:ring-0 focus-visible:outline-none sm:px-6 sm:py-5"
                    :disabled="isThinking"
                    autofocus
                />
            </div>

            <div class="border-border/50 flex items-center justify-end border-t px-3 py-2 sm:px-4">
                <div class="flex items-center gap-2">
                    <Button v-if="isThinking" @click="stopStreaming" size="icon" variant="ghost" class="hover:bg-destructive/10 h-9 w-9 rounded-full">
                        <Square class="h-4 w-4" />
                    </Button>

                    <Button
                        v-else
                        @click="sendMessage"
                        :disabled="userInput.trim() === ''"
                        size="icon"
                        variant="ghost"
                        class="h-9 w-9 rounded-full transition-all"
                        :class="{
                            'opacity-50': userInput.trim() === '',
                            'scale-110 shadow-lg': userInput.trim() !== '',
                        }"
                    >
                        <Send class="h-4 w-4" />
                    </Button>
                </div>
            </div>
        </div>
    </div>
</template>
