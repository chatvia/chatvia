<script setup lang="ts">
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';

withDefaults(
    defineProps<{
        welcomeMessage: string;
        chatWindowTitle: string;
        chatFooterText: string;
        composerPlaceholder: string;
        errors?: Record<string, string | undefined>;
        disabled?: boolean;
    }>(),
    { disabled: false, errors: () => ({}) },
);

defineEmits<{
    'update:welcomeMessage': [value: string];
    'update:chatWindowTitle': [value: string];
    'update:chatFooterText': [value: string];
    'update:composerPlaceholder': [value: string];
}>();
</script>

<template>
    <div class="space-y-6">
        <div class="space-y-2">
            <Label for="welcome_message">Welcome Message</Label>
            <Textarea
                id="welcome_message"
                :model-value="welcomeMessage"
                placeholder="Hi there! How can I help you today?"
                rows="3"
                :disabled="disabled"
                @update:model-value="$emit('update:welcomeMessage', String($event))"
            />
            <p v-if="errors?.welcome_message" class="text-destructive text-sm">{{ errors.welcome_message }}</p>
            <p class="text-muted-foreground text-xs">First message shown to users</p>
        </div>

        <div class="space-y-2">
            <Label for="chat_window_title">Chat Window Title</Label>
            <Input
                id="chat_window_title"
                :model-value="chatWindowTitle"
                placeholder="Chat with our AI Assistant"
                :disabled="disabled"
                @update:model-value="$emit('update:chatWindowTitle', String($event))"
            />
            <p v-if="errors?.chat_window_title" class="text-destructive text-sm">{{ errors.chat_window_title }}</p>
        </div>

        <div class="space-y-2">
            <Label for="chat_footer_text">Chat Footer Text</Label>
            <Input
                id="chat_footer_text"
                :model-value="chatFooterText"
                placeholder="Consider checking important information"
                :disabled="disabled"
                @update:model-value="$emit('update:chatFooterText', String($event))"
            />
            <p v-if="errors?.chat_footer_text" class="text-destructive text-sm">{{ errors.chat_footer_text }}</p>
        </div>

        <div class="space-y-2">
            <Label for="composer_placeholder">Composer Placeholder</Label>
            <Input
                id="composer_placeholder"
                :model-value="composerPlaceholder"
                placeholder="Ask anything..."
                :disabled="disabled"
                @update:model-value="$emit('update:composerPlaceholder', String($event))"
            />
            <p v-if="errors?.composer_placeholder" class="text-destructive text-sm">{{ errors.composer_placeholder }}</p>
            <p class="text-muted-foreground text-xs">Placeholder text in the chat input field</p>
        </div>
    </div>
</template>
