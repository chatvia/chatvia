<script setup lang="ts">
/**
 * A read-only id with a copy button, shown at the top of edit sheets so the
 * user can grab the external id to pass to the public API. The check mark
 * replaces the copy icon for two seconds after a copy.
 */
import { Button } from '@/shared/ui/button';
import { useClipboard } from '@vueuse/core';
import { Check, Copy } from 'lucide-vue-next';

const props = defineProps<{
    label: string;
    value: string;
    /** Tooltip explaining what the id is for. */
    copyTitle: string;
    /** Accessible name of the copy button. */
    copyLabel: string;
}>();

const { copy, copied } = useClipboard({ legacy: true, copiedDuring: 2000 });
</script>

<template>
    <div class="bg-muted/40 flex items-center gap-2 rounded-md border px-3 py-2">
        <span class="text-muted-foreground text-xs">{{ label }}</span>
        <code class="min-w-0 flex-1 truncate font-mono text-xs" :title="value">{{ value }}</code>
        <Button
            type="button"
            variant="ghost"
            size="sm"
            class="h-6 shrink-0 px-1.5"
            :title="copyTitle"
            :aria-label="copyLabel"
            @click="copy(props.value)"
        >
            <Check v-if="copied" class="size-3.5 text-green-600" />
            <Copy v-else class="size-3.5" />
        </Button>
    </div>
</template>
