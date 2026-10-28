<script setup lang="ts">
/**
 * One on/off row of the Setup forms: a label (optionally with a "?" help
 * popover), an optional hint line, and the switch. Extra classes passed by the
 * parent (e.g. a bordered card look) land on the row.
 */
import HelpPopover from '@/dashboard/agents/components/HelpPopover.vue';
import { Label } from '@/shared/ui/label';
import { Switch } from '@/shared/ui/switch';

defineProps<{
    id: string;
    label: string;
    hint?: string;
    // The "?" popover: accessible name of its button, a bold first line, then paragraphs.
    help?: { ariaLabel: string; title: string; paragraphs: string[] };
    disabled?: boolean;
}>();

const enabled = defineModel<boolean>({ required: true });
</script>

<template>
    <div class="flex items-center justify-between gap-3 px-4 py-3">
        <div v-if="hint || help">
            <div v-if="help" class="flex items-center gap-1.5">
                <Label :for="id" class="cursor-pointer text-sm font-medium">{{ label }}</Label>
                <HelpPopover :label="help.ariaLabel" :title="help.title" :paragraphs="help.paragraphs" />
            </div>
            <Label v-else :for="id" class="cursor-pointer text-sm font-medium">{{ label }}</Label>
            <p v-if="hint" class="text-muted-foreground mt-0.5 text-xs">{{ hint }}</p>
        </div>
        <Label v-else :for="id" class="cursor-pointer text-sm font-medium">{{ label }}</Label>
        <Switch :id="id" v-model="enabled" :disabled="disabled" />
    </div>
</template>
