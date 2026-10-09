<script setup lang="ts">
// A read-only, monospace field with a copy button — for URLs the workspace
// pastes into the other app's settings (Slack Request URL, Teams endpoint).
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useClipboard } from '@vueuse/core';
import { Copy } from 'lucide-vue-next';
import type { Component } from 'vue';
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner';

const { t } = useI18n();

const props = defineProps<{
    id: string;
    label: string;
    icon: Component;
    value: string | null;
}>();

const { copy } = useClipboard({ legacy: true });

const copyValue = async () => {
    if (!props.value) return;
    await copy(props.value);
    toast.success(t('agents.channels.copy_field.copied', { label: props.label }));
};
</script>

<template>
    <div class="space-y-1.5">
        <Label :for="id">{{ label }}</Label>
        <div class="flex gap-2">
            <div class="relative min-w-0 flex-1">
                <component :is="icon" class="text-muted-foreground/70 pointer-events-none absolute start-3 top-1/2 size-4 -translate-y-1/2" />
                <Input
                    :id="id"
                    :model-value="value ?? ''"
                    readonly
                    class="ps-9 font-mono text-xs"
                    data-1p-ignore
                    data-lpignore="true"
                    data-bwignore
                    data-form-type="other"
                />
            </div>
            <Button
                variant="outline"
                size="icon"
                class="shrink-0"
                :aria-label="t('agents.channels.copy_field.copy_label', { label })"
                @click="copyValue"
            >
                <Copy class="size-4" />
            </Button>
        </div>
    </div>
</template>
