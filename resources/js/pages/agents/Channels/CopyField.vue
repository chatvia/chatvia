<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
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

const copy = async () => {
    if (!props.value) return;
    await navigator.clipboard.writeText(props.value);
    toast.success(t('agents.channels.copy_field.copied', { label: props.label }));
};
</script>

<template>
    <div class="space-y-1.5">
        <Label :for="id">{{ label }}</Label>
        <div class="flex gap-2">
            <div class="relative min-w-0 flex-1">
                <component :is="icon" class="text-muted-foreground/70 pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2" />
                <Input
                    :id="id"
                    :model-value="value ?? ''"
                    readonly
                    class="pl-9 font-mono text-xs"
                    data-1p-ignore
                    data-lpignore="true"
                    data-bwignore
                    data-form-type="other"
                />
            </div>
            <Button variant="outline" size="icon" class="shrink-0" :aria-label="t('agents.channels.copy_field.copy_label', { label })" @click="copy">
                <Copy class="size-4" />
            </Button>
        </div>
    </div>
</template>
