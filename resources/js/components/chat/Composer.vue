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
    placeholder?: string,
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
    if (!userInput.value.trim() || !selectedModel.value) return;

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
            class="rounded-3xl border transition-all duration-200 bg-background shadow-sm"
            :class="[
                isComposing ? 'border-border shadow-md' : 'border-border/60',
            ]"
        >
            <div class="relative">
                <Textarea
                    ref="messageInput"
                    v-model="userInput"
                    @keydown.enter="handleEnterKey"
                    @input="adjustTextareaHeight"
                    @focus="handleFocus"
                    @blur="handleBlur"
                    :placeholder="placeholder || 'Message...'"
                    class="min-h-[52px] max-h-[200px] resize-none rounded-3xl border-0 bg-transparent px-4 py-3.5 text-[15px] leading-relaxed text-foreground placeholder:text-muted-foreground/50 focus-visible:outline-none focus-visible:ring-0 pr-12"
                    :disabled="isThinking"
                    autofocus
                />
            </div>

            <div class="flex items-center justify-between px-3 pb-2.5">
                <div class="flex items-center gap-1.5">
                    <ModelSelector
                        @modelChanged="handleModelChange"
                        :models="models"
                        :defaultModel="defaultModel"
                        :isLoadingModels="isLoadingModels"
                    />
                </div>

                <div class="flex items-center gap-1.5">
                    <Button
                        v-if="isThinking"
                        @click="stopStreaming"
                        size="icon"
                        variant="ghost"
                        class="h-8 w-8 rounded-lg hover:bg-muted transition-colors"
                    >
                        <Square class="h-3.5 w-3.5 fill-current" />
                    </Button>

                    <Button
                        v-else
                        @click="sendMessage"
                        :disabled="userInput.trim() === '' || !selectedModel"
                        size="icon"
                        variant="ghost"
                        class="h-8 w-8 rounded-lg transition-colors disabled:opacity-30"
                        :class="{
                            'hover:bg-muted': userInput.trim() !== '' && selectedModel
                        }"
                    >
                        <Send class="h-3.5 w-3.5" />
                    </Button>
                </div>
            </div>
        </div>
    </div>
</template>


