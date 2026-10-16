<script setup lang="ts">
/**
 * A small "?" button that opens a popover: a bold first line, then paragraphs.
 * The click stops at the button, so it can sit inside a clickable row or a
 * <label> without toggling it.
 */
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { CircleHelp } from 'lucide-vue-next';

defineProps<{
    // The accessible name of the "?" button.
    label: string;
    title: string;
    paragraphs: string[];
}>();
</script>

<template>
    <Popover>
        <PopoverTrigger as-child>
            <button
                type="button"
                class="text-muted-foreground hover:text-foreground cursor-pointer rounded-full transition-colors"
                :aria-label="label"
                @click.stop.prevent
            >
                <CircleHelp class="h-3.5 w-3.5" />
            </button>
        </PopoverTrigger>
        <PopoverContent align="start" class="w-80 text-xs leading-relaxed">
            <p class="text-foreground font-medium">{{ title }}</p>
            <p v-for="(paragraph, index) in paragraphs" :key="index" class="text-muted-foreground mt-1.5">{{ paragraph }}</p>
        </PopoverContent>
    </Popover>
</template>
