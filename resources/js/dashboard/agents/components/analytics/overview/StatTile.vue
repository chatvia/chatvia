<script setup lang="ts">
/** One headline number of the analytics overview (icon, label, value). */
import { Card, CardContent } from '@/shared/ui/card';
import type { Component } from 'vue';

defineProps<{
    icon: Component;
    label: string;
    value: string | number;
}>();
</script>

<template>
    <Card>
        <CardContent class="!p-3 !pb-3">
            <div class="flex items-center gap-2">
                <component :is="icon" class="text-muted-foreground h-4 w-4" />
                <p class="text-muted-foreground text-sm font-medium">{{ label }}</p>
            </div>
            <p class="mt-1 text-xl font-bold">{{ value }}</p>
        </CardContent>
    </Card>
</template>
