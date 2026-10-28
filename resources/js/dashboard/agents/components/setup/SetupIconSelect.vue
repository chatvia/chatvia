<script setup lang="ts">
/** A labelled select with an icon inside its trigger, as used by the Behavior section. */
import { Label } from '@/shared/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/shared/ui/select';
import type { SelectOption } from '@/dashboard/agents/composables/setup/useSetupBehavior';
import type { Component } from 'vue';

defineProps<{
    id: string;
    label: string;
    icon: Component;
    options: SelectOption[];
    disabled?: boolean;
}>();

const value = defineModel<string>({ required: true });
</script>

<template>
    <div class="space-y-1.5">
        <Label :for="id" class="text-sm font-medium">{{ label }}</Label>
        <div class="relative">
            <div class="text-muted-foreground pointer-events-none absolute inset-y-0 start-0 z-10 flex items-center ps-3">
                <component :is="icon" class="h-4 w-4" />
            </div>
            <Select v-model="value" :disabled="disabled">
                <SelectTrigger :id="id" class="h-10 w-full ps-9"><SelectValue /></SelectTrigger>
                <SelectContent>
                    <SelectItem v-for="option in options" :key="option.value" :value="option.value">{{ option.label }}</SelectItem>
                </SelectContent>
            </Select>
        </div>
    </div>
</template>
