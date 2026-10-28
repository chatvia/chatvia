<script setup lang="ts">
import { Input } from '@/shared/ui/input';
import { Label } from '@/shared/ui/label';
import type { Component } from 'vue';

defineProps<{
    id: string;
    label: string;
    icon: Component;
    hint?: string;
    placeholder?: string;
    /** Render as a password field (masked). */
    secret?: boolean;
    error?: string;
}>();

const model = defineModel<string>({ required: true });

const emit = defineEmits<{ enter: [] }>();
</script>

<template>
    <div class="space-y-1.5">
        <Label :for="id">
            {{ label }}
            <span v-if="hint" class="text-muted-foreground font-normal">{{ hint }}</span>
        </Label>
        <div class="relative">
            <component :is="icon" class="text-muted-foreground/70 pointer-events-none absolute start-3 top-1/2 size-4 -translate-y-1/2" />
            <!-- These fields hold API credentials, never login passwords — the data-*
                 attributes opt out of 1Password/LastPass/Bitwarden/Dashlane autofill,
                 and `new-password` stops Chrome's own manager on masked fields. -->
            <Input
                :id="id"
                v-model="model"
                :type="secret ? 'password' : 'text'"
                :placeholder="placeholder"
                class="ps-9 font-mono"
                :autocomplete="secret ? 'new-password' : 'off'"
                autocapitalize="off"
                autocorrect="off"
                spellcheck="false"
                data-1p-ignore
                data-lpignore="true"
                data-bwignore
                data-form-type="other"
                @keydown.enter="emit('enter')"
            />
        </div>
        <p v-if="error" class="text-destructive text-xs">{{ error }}</p>
    </div>
</template>
