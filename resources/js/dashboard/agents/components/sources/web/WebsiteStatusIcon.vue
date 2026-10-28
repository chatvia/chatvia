<script setup lang="ts">
/** The small coloured status icon next to a website's name, with a tooltip. */
import type { WebsiteStatus } from '@/dashboard/agents/api/webSources';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/shared/ui/tooltip';
import { AlertCircle, CheckCircle, LoaderCircle, NetworkIcon, Play } from 'lucide-vue-next';
import { computed, type Component } from 'vue';

const props = withDefaults(
    defineProps<{
        status: WebsiteStatus;
        tooltip: string;
        /** Spin the icon (used while a website is being crawled). */
        spin?: boolean;
    }>(),
    { spin: false },
);

const ICONS: Record<WebsiteStatus, Component> = {
    pending: Play,
    crawling: LoaderCircle,
    crawled: NetworkIcon,
    ingesting: LoaderCircle,
    ingested: CheckCircle,
    failed: AlertCircle,
};

const COLORS: Record<WebsiteStatus, string> = {
    pending: 'text-yellow-600',
    crawling: 'text-blue-600',
    crawled: 'text-purple-600',
    ingesting: 'text-blue-600',
    ingested: 'text-green-600',
    failed: 'text-red-600',
};

const icon = computed(() => ICONS[props.status] ?? Play);
const color = computed(() => COLORS[props.status] ?? 'text-gray-600');
</script>

<template>
    <TooltipProvider>
        <Tooltip>
            <TooltipTrigger as-child>
                <div class="flex items-center">
                    <component :is="icon" :class="['h-4 w-4 flex-shrink-0', color, { 'animate-spin': spin }]" />
                </div>
            </TooltipTrigger>
            <TooltipContent>
                <p>{{ tooltip }}</p>
            </TooltipContent>
        </Tooltip>
    </TooltipProvider>
</template>
