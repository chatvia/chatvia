<script setup lang="ts">
/**
 * Tri-state permission segmented control for one connector tool.
 * Allow → the tool runs inline; Approval → the turn pauses for the user's
 * confirmation; Blocked → the tool is excluded from the catalog.
 */
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/shared/ui/tooltip';
import { useToolPermissionOptions } from '@/dashboard/workspace/composables/useConnectorTools';
import type { McpToolPermission } from '@/shared/types/connector';

defineProps<{ permission: McpToolPermission }>();
const emit = defineEmits<{ select: [permission: McpToolPermission] }>();

const permissionOptions = useToolPermissionOptions();
</script>

<template>
    <TooltipProvider :delay-duration="200">
        <div class="bg-muted/40 mt-0.5 flex shrink-0 items-center gap-0.5 rounded-md border p-0.5">
            <Tooltip v-for="option in permissionOptions" :key="option.value">
                <TooltipTrigger as-child>
                    <button
                        type="button"
                        class="rounded px-1.5 py-1 transition-colors"
                        :class="
                            permission === option.value ? 'bg-background text-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground'
                        "
                        :aria-label="option.label"
                        :aria-pressed="permission === option.value"
                        @click="emit('select', option.value)"
                    >
                        <component :is="option.icon" class="h-3.5 w-3.5" />
                    </button>
                </TooltipTrigger>
                <TooltipContent side="top">{{ option.label }}</TooltipContent>
            </Tooltip>
        </div>
    </TooltipProvider>
</template>
