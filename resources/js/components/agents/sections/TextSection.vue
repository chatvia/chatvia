<script setup lang="ts">
import AppearanceTextFields from '@/components/agents/AppearanceTextFields.vue';
import { Label } from '@/components/ui/label';
import { TagsInput, TagsInputInput, TagsInputItem, TagsInputItemDelete, TagsInputItemText } from '@/components/ui/tags-input';

withDefaults(
    defineProps<{
        welcomeMessage: string;
        chatWindowTitle: string;
        composerPlaceholder: string;
        bubbles?: string[];
        showBubbles?: boolean;
        errors?: Record<string, string | undefined>;
        disabled?: boolean;
    }>(),
    { showBubbles: false, bubbles: () => [], errors: () => ({}), disabled: false },
);

defineEmits<{
    'update:welcomeMessage': [value: string];
    'update:chatWindowTitle': [value: string];
    'update:composerPlaceholder': [value: string];
    'update:bubbles': [value: string[]];
}>();
</script>

<template>
    <div class="space-y-6">
        <AppearanceTextFields
            :welcome-message="welcomeMessage"
            :chat-window-title="chatWindowTitle"
            :composer-placeholder="composerPlaceholder"
            :errors="errors"
            :disabled="disabled"
            @update:welcome-message="$emit('update:welcomeMessage', $event)"
            @update:chat-window-title="$emit('update:chatWindowTitle', $event)"
            @update:composer-placeholder="$emit('update:composerPlaceholder', $event)"
        />

        <div v-if="showBubbles" class="space-y-2">
            <Label>Chat bubbles</Label>
            <p class="text-muted-foreground text-xs">Quick message prompts to help visitors start a conversation.</p>
            <TagsInput
                :model-value="bubbles"
                :disabled="disabled"
                class="w-full"
                @update:model-value="$emit('update:bubbles', ($event as string[]) ?? [])"
            >
                <TagsInputItem v-for="(bubble, index) in bubbles" :key="index" :value="bubble">
                    <TagsInputItemText />
                    <TagsInputItemDelete />
                </TagsInputItem>
                <TagsInputInput placeholder="e.g., How can I help you?" />
            </TagsInput>
            <p v-if="errors?.bubbles" class="text-destructive text-sm">{{ errors.bubbles }}</p>
        </div>
    </div>
</template>
