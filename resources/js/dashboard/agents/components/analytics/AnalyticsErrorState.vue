<script setup lang="ts">
/** The "could not load" card of the analytics pages, with a retry link. */
import { Card, CardContent } from '@/shared/ui/card';

defineProps<{
    message: string;
    retryLabel: string;
}>();

const emit = defineEmits<{ retry: [] }>();
</script>

<template>
    <div class="flex items-center justify-center py-12">
        <Card class="max-w-md">
            <CardContent class="pt-6">
                <div class="space-y-2 text-center">
                    <p class="text-destructive font-medium">{{ message }}</p>
                    <button @click="emit('retry')" class="text-muted-foreground hover:text-foreground text-sm underline">
                        {{ retryLabel }}
                    </button>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
