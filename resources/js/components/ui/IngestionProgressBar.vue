<script setup lang="ts">
import { computed } from 'vue';
import { LoaderCircle } from 'lucide-vue-next';

interface Props {
    progress: number;
    label?: string;
}

const props = withDefaults(defineProps<Props>(), {
    label: 'Training in progress'
});

const displayProgress = computed(() => Math.round(props.progress ?? 0));
</script>

<template>
    <div class="mt-3 space-y-2">
        <div class="flex items-center justify-between text-xs">
            <div class="flex items-center gap-2">
                <LoaderCircle class="h-3.5 w-3.5 text-muted-foreground animate-spin" />
                <span class="text-muted-foreground">{{ label }}</span>
            </div>
            <span class="font-medium tabular-nums">{{ displayProgress }}%</span>
        </div>
        <div class="w-full bg-secondary rounded-full h-2 overflow-hidden">
            <div 
                class="bg-primary h-full transition-all duration-300 ease-out"
                :style="{ width: `${displayProgress}%` }"
            ></div>
        </div>
    </div>
</template>

