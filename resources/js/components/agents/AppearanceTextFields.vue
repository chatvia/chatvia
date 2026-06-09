<script setup lang="ts">
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { MessageCircle, Pencil, Type } from 'lucide-vue-next';

withDefaults(
    defineProps<{
        welcomeMessage: string;
        chatWindowTitle: string;
        composerPlaceholder: string;
        errors?: Record<string, string | undefined>;
        disabled?: boolean;
    }>(),
    { disabled: false, errors: () => ({}) },
);

defineEmits<{
    'update:welcomeMessage': [value: string];
    'update:chatWindowTitle': [value: string];
    'update:composerPlaceholder': [value: string];
}>();
</script>

<template>
    <div class="space-y-6">
        <div class="space-y-2">
            <Label for="chat_window_title">Chat Window Title</Label>
            <div class="relative">
                <div class="text-muted-foreground pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                    <Type class="h-4 w-4" />
                </div>
                <Input
                    id="chat_window_title"
                    :model-value="chatWindowTitle"
                    placeholder="Chat with our AI Assistant"
                    :disabled="disabled"
                    class="pl-9"
                    autocomplete="off"
                    data-1p-ignore
                    data-lpignore="true"
                    data-form-type="other"
                    @update:model-value="$emit('update:chatWindowTitle', String($event))"
                />
            </div>
            <p v-if="errors?.chat_window_title" class="text-destructive text-sm">{{ errors.chat_window_title }}</p>
        </div>

        <div class="space-y-2">
            <Label for="welcome_message">Welcome Message</Label>
            <div class="relative">
                <div class="text-muted-foreground pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                    <MessageCircle class="h-4 w-4" />
                </div>
                <Input
                    id="welcome_message"
                    :model-value="welcomeMessage"
                    placeholder="Hi there! How can I help you today?"
                    :disabled="disabled"
                    class="pl-9"
                    autocomplete="off"
                    data-1p-ignore
                    data-lpignore="true"
                    data-form-type="other"
                    @update:model-value="$emit('update:welcomeMessage', String($event))"
                />
            </div>
            <p v-if="errors?.welcome_message" class="text-destructive text-sm">{{ errors.welcome_message }}</p>
        </div>

        <div class="space-y-2">
            <Label for="composer_placeholder">Composer Placeholder</Label>
            <div class="relative">
                <div class="text-muted-foreground pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                    <Pencil class="h-4 w-4" />
                </div>
                <Input
                    id="composer_placeholder"
                    :model-value="composerPlaceholder"
                    placeholder="Ask anything..."
                    :disabled="disabled"
                    class="pl-9"
                    autocomplete="off"
                    data-1p-ignore
                    data-lpignore="true"
                    data-form-type="other"
                    @update:model-value="$emit('update:composerPlaceholder', String($event))"
                />
            </div>
            <p v-if="errors?.composer_placeholder" class="text-destructive text-sm">{{ errors.composer_placeholder }}</p>
        </div>
    </div>
</template>
