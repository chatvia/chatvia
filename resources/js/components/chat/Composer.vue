<script setup lang="ts">
import { ref, nextTick, computed, watch } from 'vue';
import ModelSelector from "@/components/chat/ModelSelector.vue";
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { Square, Send, Keyboard } from 'lucide-vue-next';
import type { AIModel } from '@/types/chat';

interface Props {
    isThinking?: boolean,
    text?: string,
    models?: AIModel[],
    defaultModel?: AIModel | null,
    isLoadingModels?: boolean,
}

const props = defineProps<Props>();

const selectedModel = ref<AIModel | null>(props.defaultModel || null);
const userInput = ref(props.text || '');
const messageInput = ref<HTMLTextAreaElement>();
const isComposing = ref(false);

// Watch for changes in the text prop and update userInput
watch(() => props.text, (newText) => {
    if (newText !== undefined && newText !== userInput.value) {
        userInput.value = newText;
        // Auto-adjust textarea height after text update, but only if textarea exists
        nextTick(() => {
            if (messageInput.value) {
                adjustTextareaHeight();
            }
        });
    }
}, { immediate: true });

// Watch for changes in the defaultModel prop and update selectedModel
watch(() => props.defaultModel, (newDefaultModel) => {
    if (newDefaultModel && newDefaultModel !== selectedModel.value) {
        selectedModel.value = newDefaultModel;
        emit('modelSelected', newDefaultModel);
    }
}, { immediate: true });

const emit = defineEmits<{
    messageAdded: [content: string, model: AIModel]
    stopStreaming: []
    modelSelected: [model: AIModel]
}>();

// Character count
const characterCount = computed(() => userInput.value.length);
const maxCharacters = 4000;
const characterPercentage = computed(() => (characterCount.value / maxCharacters) * 100);

// Check if close to limit
const isNearLimit = computed(() => characterCount.value > maxCharacters * 0.9);
const isOverLimit = computed(() => characterCount.value > maxCharacters);

const handleEnterKey = (event: KeyboardEvent) => {
    if (!event.shiftKey) {
        event.preventDefault();
        sendMessage();
    } else {
        adjustTextareaHeight(event);
    }
};

const adjustTextareaHeight = (event?: Event) => {
    const textarea = event?.target as HTMLTextAreaElement || messageInput.value;
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
}

const handleModelChange = (model: AIModel) => {
    emit('modelSelected', model);
    selectedModel.value = model;

    // Use nextTick to ensure the DOM is updated before focusing
    nextTick(() => {
        if (messageInput.value && typeof messageInput.value.focus === 'function') {
            messageInput.value.focus();
        }
    });
}

const sendMessage = () => {
    if (!userInput.value.trim() || !selectedModel.value || isOverLimit.value) return;

    emit('messageAdded', userInput.value, selectedModel.value);
    userInput.value = '';

    // Reset textarea height
    if (messageInput.value && messageInput.value.style) {
        messageInput.value.style.height = 'auto';
    }
}

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
            :class="[
                isComposing ? 'border-primary shadow-md' : 'border-border shadow-sm hover:shadow-md',
                'bg-card'
            ]"
        >
            <!-- Character count indicator -->
            <div v-if="characterCount > 0" class="absolute -top-8 right-0 flex items-center gap-2">
                <span
                    class="text-xs transition-colors"
                    :class="{
                        'text-destructive': isOverLimit,
                        'text-warning': isNearLimit && !isOverLimit,
                        'text-muted-foreground': !isNearLimit
                    }"
                >
                    {{ characterCount.toLocaleString() }} / {{ maxCharacters.toLocaleString() }}
                </span>
            </div>

            <div class="relative">
                <Textarea
                    ref="messageInput"
                    v-model="userInput"
                    @keydown.enter="handleEnterKey"
                    @input="adjustTextareaHeight"
                    @focus="handleFocus"
                    @blur="handleBlur"
                    placeholder="Ask anything..."
                    class="min-h-[60px] max-h-[200px] resize-none rounded-2xl border-0 bg-transparent px-4 py-4 text-foreground placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-0 pr-12 sm:px-6 sm:py-5"
                    :disabled="isThinking"
                    :class="{ 'text-destructive': isOverLimit }"
                    autofocus
                />

                <!-- Character limit progress bar -->
                <div
                    v-if="characterCount > maxCharacters * 0.5"
                    class="absolute bottom-0 left-0 right-0 h-0.5 bg-muted rounded-b-2xl overflow-hidden"
                >
                    <div
                        class="h-full transition-all duration-300"
                        :class="{
                            'bg-destructive': isOverLimit,
                            'bg-warning': isNearLimit && !isOverLimit,
                            'bg-primary': !isNearLimit
                        }"
                        :style="{ width: `${Math.min(characterPercentage, 100)}%` }"
                    />
                </div>
            </div>

            <div class="flex items-center justify-between border-t border-border/50 px-3 py-2 sm:px-4">
                <div class="flex items-center gap-2">
                    <ModelSelector
                        @modelChanged="handleModelChange"
                        :models="models"
                        :defaultModel="defaultModel"
                        :isLoadingModels="isLoadingModels"
                    />

                    <!-- Keyboard shortcuts hint -->
                    <div class="hidden sm:flex items-center gap-1 text-xs text-muted-foreground">
                        <Keyboard class="h-3 w-3" />
                        <span>Enter to send</span>
                        <span class="text-muted-foreground/50">•</span>
                        <span>Shift + Enter for new line</span>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <Button
                        v-if="isThinking"
                        @click="stopStreaming"
                        size="icon"
                        variant="ghost"
                        class="h-9 w-9 rounded-full hover:bg-destructive/10"
                    >
                        <Square class="h-4 w-4" />
                    </Button>

                    <Button
                        v-else
                        @click="sendMessage"
                        :disabled="userInput.trim() === '' || !selectedModel || isOverLimit"
                        size="icon"
                        class="h-9 w-9 rounded-full transition-all"
                        :class="{
                            'opacity-50': userInput.trim() === '' || !selectedModel,
                            'scale-110 shadow-lg': userInput.trim() !== '' && selectedModel && !isOverLimit
                        }"
                    >
                        <Send class="h-4 w-4" />
                    </Button>
                </div>
            </div>
        </div>

        <p class="mt-3 text-center text-xs text-muted-foreground sm:mt-4">
            <span v-if="isOverLimit" class="text-destructive">
                Message exceeds character limit
            </span>
            <span v-else>
                Chat can make mistakes. Consider checking important information.
            </span>
        </p>
    </div>
</template>


