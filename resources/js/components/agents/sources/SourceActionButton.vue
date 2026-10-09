<script setup lang="ts">
/**
 * An icon-only ghost button with a tooltip, used for the row actions on the
 * knowledge source pages (delete, view links, summary, retrain, …).
 *
 * `label` is both the tooltip text and the button's accessible name. The
 * `tooltip` slot replaces the tooltip body when it needs more than one line.
 * Classes and listeners (`class`, `@click`, `disabled`) go to the button, not
 * the tooltip wrapper; a passed `variant` or `size` overrides the ghost/sm
 * defaults because `$attrs` is bound after them.
 */
import { Button } from '@/components/ui/button';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import type { Component } from 'vue';

defineOptions({ inheritAttrs: false });

withDefaults(
    defineProps<{
        label: string;
        icon: Component;
        iconClass?: string;
    }>(),
    { iconClass: 'h-4 w-4' },
);
</script>

<template>
    <TooltipProvider>
        <Tooltip>
            <TooltipTrigger as-child>
                <Button size="sm" variant="ghost" :aria-label="label" v-bind="$attrs">
                    <component :is="icon" :class="iconClass" />
                </Button>
            </TooltipTrigger>
            <TooltipContent>
                <slot name="tooltip">
                    <p>{{ label }}</p>
                </slot>
            </TooltipContent>
        </Tooltip>
    </TooltipProvider>
</template>
