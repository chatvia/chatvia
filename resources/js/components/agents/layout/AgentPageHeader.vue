<script setup lang="ts">
/**
 * The title row of an agent page: optional icon, title and description, the
 * page's own actions, and a badge showing whether the agent is public (eye)
 * or protected (lock).
 */
import { Badge } from '@/components/ui/badge';
import { Eye, Lock } from 'lucide-vue-next';

defineProps<{
    title: string;
    description: string;
    isPublic: boolean;
}>();
</script>

<template>
    <div class="bg-background border-border border-b px-4 py-4 lg:px-6">
        <div class="flex items-center justify-between">
            <div class="min-w-0 flex-1">
                <h1 class="text-foreground flex items-center gap-2 truncate text-lg font-semibold lg:text-xl">
                    <slot name="icon" />
                    {{ title }}
                </h1>
                <p v-if="description" class="text-muted-foreground mt-1 hidden text-xs sm:block lg:text-sm">
                    {{ description }}
                </p>
            </div>

            <div class="flex flex-shrink-0 gap-2 lg:gap-4">
                <div class="flex items-center gap-2">
                    <slot name="actions" />
                </div>

                <Badge
                    :variant="isPublic ? 'destructive' : 'secondary'"
                    :class="[
                        'text-xs',
                        !isPublic
                            ? 'border-green-200 bg-green-100 text-green-700 dark:border-green-800 dark:bg-green-900/20 dark:text-green-400'
                            : '',
                    ]"
                >
                    <component :is="isPublic ? Eye : Lock" class="h-3 w-3" />
                </Badge>
            </div>
        </div>
    </div>
</template>
