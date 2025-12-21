<script lang="ts" setup>
import { Link } from '@inertiajs/vue3';
import {
    Globe,
    Youtube,
    FileText,
    FileCode,
    File,
    Video,
    Music,
    FileAudio,
    WholeWord,
    HelpCircle,
} from 'lucide-vue-next';

defineProps({
    agentId: {
        type: String,
        required: true,
    },
    sourceStats: {
        type: Object,
        default: () => ({}),
    },
    variant: {
        type: String,
        default: 'submenu', // 'submenu' or 'dropdown'
    },
    isActiveRoute: {
        type: Function,
        required: true,
    },
});

// Helper function to get source count
const getSourceCount = (statsKey: string, stats: any): string => {
    if (!stats || !(statsKey in stats)) {
        return '';
    }
    const count = stats[statsKey] || 0;
    return count > 0 ? ` (${count})` : '';
};

// Define the menu items in the desired order
const menuItems = [
    {
        id: 'files',
        label: 'Files',
        icon: File,
        route: 'agents.sources.files',
        statsKey: 'files',
        permission: 'agent.sources.train.files',
    },
    {
        id: 'text',
        label: 'Text',
        icon: FileText,
        route: 'agents.sources.text',
        statsKey: 'texts',
        permission: 'agent.sources.train.text',
    },
    {
        id: 'qa',
        label: 'Q&A',
        icon: HelpCircle,
        route: 'agents.sources.qa',
        statsKey: 'qa',
        permission: 'agent.sources.train.qa',
    },
    {
        id: 'web',
        label: 'Web',
        icon: Globe,
        route: 'agents.sources.web',
        statsKey: 'websites',
        permission: 'agent.sources.train.web',
    },
    {
        id: 'audio',
        label: 'Audio',
        icon: FileAudio,
        route: 'agents.sources.audio',
        statsKey: 'audio',
        permission: 'agent.sources.train.audio',
    },
    {
        id: 'video',
        label: 'Video',
        icon: Video,
        route: 'agents.sources.video',
        statsKey: 'video',
        permission: 'agent.sources.train.video',
    },
];
</script>

<template>
    <!-- Dropdown variant -->
    <template v-if="variant === 'dropdown'">
        <slot
            v-for="item in menuItems"
            :key="item.id"
            :item="item"
            :label="item.label + getSourceCount(item.statsKey, sourceStats)"
        >
            <!-- Default slot content if parent doesn't provide one -->
            <Link
                :href="route(item.route, { agent: agentId })"
                class="flex items-center gap-2"
            >
                <component :is="item.icon" class="h-4 w-4" />
                <span>{{ item.label }}{{ getSourceCount(item.statsKey, sourceStats) }}</span>
            </Link>
        </slot>
    </template>

    <!-- Submenu variant -->
    <template v-else>
        <slot
            v-for="item in menuItems"
            :key="item.id"
            :item="item"
            :label="item.label + getSourceCount(item.statsKey, sourceStats)"
            :is-active="isActiveRoute(item.route)"
        >
            <!-- Default slot content if parent doesn't provide one -->
            <Link :href="route(item.route, { agent: agentId })">
                <component :is="item.icon" class="h-4 w-4" />
                <span>{{ item.label }}{{ getSourceCount(item.statsKey, sourceStats) }}</span>
            </Link>
        </slot>
    </template>
</template>

