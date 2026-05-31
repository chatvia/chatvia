<script setup lang="ts">
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { computed } from 'vue';

const NONE = '__none__';

const props = withDefaults(
    defineProps<{
        promptId: string;
        prompts: { id: string; name: string }[];
        errors?: Record<string, string | undefined>;
        disabled?: boolean;
    }>(),
    { errors: () => ({}), disabled: false },
);

const emit = defineEmits<{
    'update:promptId': [value: string];
}>();

// Reka Select rejects empty-string values, so map "no prompt" to a sentinel.
const selectValue = computed({
    get: () => (props.promptId ? props.promptId : NONE),
    set: (value: string) => emit('update:promptId', value === NONE ? '' : value),
});
</script>

<template>
    <div class="space-y-2">
        <Label>System prompt</Label>
        <Select v-model="selectValue" :disabled="disabled">
            <SelectTrigger class="w-full">
                <SelectValue placeholder="No prompt" />
            </SelectTrigger>
            <SelectContent>
                <SelectItem :value="NONE">No prompt</SelectItem>
                <SelectItem v-for="p in prompts" :key="p.id" :value="p.id">{{ p.name }}</SelectItem>
            </SelectContent>
        </Select>
        <p class="text-muted-foreground text-xs">Pick a reusable prompt from your library. Manage prompts under Settings → Prompts.</p>
        <p v-if="errors?.prompt_id" class="text-destructive text-sm">{{ errors.prompt_id }}</p>
    </div>
</template>
