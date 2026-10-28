<script setup lang="ts">
/**
 * An icon-only button of the live-preview toolbar. `label` is both its tooltip
 * and its accessible name. Classes and listeners go to the button. Must sit
 * inside a TooltipProvider.
 */
import { Tooltip, TooltipContent, TooltipTrigger } from '@/shared/ui/tooltip';
import type { Component } from 'vue';

defineOptions({ inheritAttrs: false });

defineProps<{
    label: string;
    icon: Component;
}>();
</script>

<template>
    <Tooltip>
        <TooltipTrigger as-child>
            <button
                type="button"
                class="text-muted-foreground hover:text-foreground cursor-pointer rounded-md p-1.5 transition-colors"
                :aria-label="label"
                v-bind="$attrs"
            >
                <component :is="icon" class="h-4 w-4" />
            </button>
        </TooltipTrigger>
        <TooltipContent>
            <p>{{ label }}</p>
        </TooltipContent>
    </Tooltip>
</template>
