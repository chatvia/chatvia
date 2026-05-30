<script setup lang="ts">
import { Alert, AlertDescription } from '@/components/ui/alert';
import { TagsInput, TagsInputInput, TagsInputItem, TagsInputItemDelete, TagsInputItemText } from '@/components/ui/tags-input';
import { Info } from 'lucide-vue-next';

withDefaults(
    defineProps<{
        starters: string[];
        errors?: Record<string, string | undefined>;
        disabled?: boolean;
    }>(),
    { errors: () => ({}), disabled: false },
);

defineEmits<{
    'update:starters': [value: string[]];
}>();
</script>

<template>
    <div class="space-y-4">
        <div>
            <h3 class="text-sm font-medium">Conversation Starters</h3>
            <p class="text-muted-foreground mt-1 text-xs">
                Suggest questions to help users start conversations. Press Enter to add each one.
            </p>
        </div>

        <TagsInput
            :model-value="starters"
            :disabled="disabled"
            class="w-full"
            @update:model-value="$emit('update:starters', ($event as string[]) ?? [])"
        >
            <TagsInputItem v-for="(starter, index) in starters" :key="index" :value="starter">
                <TagsInputItemText />
                <TagsInputItemDelete />
            </TagsInputItem>
            <TagsInputInput placeholder="E.g., How can I track my order?" />
        </TagsInput>
        <p v-if="errors?.starters" class="text-destructive text-sm">{{ errors.starters }}</p>

        <Alert>
            <Info class="h-4 w-4" />
            <AlertDescription>Good starter messages are specific questions that showcase your agent's capabilities</AlertDescription>
        </Alert>
    </div>
</template>
