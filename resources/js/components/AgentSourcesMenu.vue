<script lang="ts" setup>
import { useTeamPermissions } from '@/composables/useTeamPermissions';
import { Link } from '@inertiajs/vue3';
import { File, FileAudio, FileText, FunctionSquare, Globe, HelpCircle, Plug, Video } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps({
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

const { hasPermission } = useTeamPermissions();

// Helper function to get source count
const getSourceCount = (statsKey: string, stats: any): string => {
    if (!stats || !(statsKey in stats)) {
        return '';
    }
    const count = stats[statsKey] || 0;
    return count > 0 ? ` (${count})` : '';
};

// Define all menu items
const allMenuItems = [
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
        route: 'agents.sources.text.index',
        statsKey: 'texts',
        permission: 'agent.sources.train.text',
    },
    {
        id: 'qa',
        label: 'Q&A',
        icon: HelpCircle,
        route: 'agents.sources.qa.index',
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
    {
        id: 'functions',
        label: 'Functions',
        icon: FunctionSquare,
        route: 'agents.sources.functions.index',
        statsKey: 'functions',
        permission: 'agent.sources.train.functions',
    },
    {
        id: 'connectors',
        label: 'Connectors',
        icon: Plug,
        route: 'agents.sources.connectors.index',
        statsKey: 'connectors',
        permission: 'agent.sources.train.connectors',
    },
];

// Filter menu items based on user permissions
const menuItems = computed(() => {
    return allMenuItems.filter((item) => hasPermission(item.permission));
});
</script>

<template>
    <!-- Dropdown variant -->
    <template v-if="props.variant === 'dropdown'">
        <slot v-for="item in menuItems" :key="item.id" :item="item" :label="item.label + getSourceCount(item.statsKey, props.sourceStats)">
            <!-- Default slot content if parent doesn't provide one -->
            <Link :href="route(item.route, { agent: props.agentId })" class="flex items-center gap-2">
                <component :is="item.icon" class="h-4 w-4" />
                <span>{{ item.label }}{{ getSourceCount(item.statsKey, props.sourceStats) }}</span>
            </Link>
        </slot>
    </template>

    <!-- Submenu variant -->
    <template v-else>
        <slot
            v-for="item in menuItems"
            :key="item.id"
            :item="item"
            :label="item.label + getSourceCount(item.statsKey, props.sourceStats)"
            :is-active="props.isActiveRoute(item.route)"
        >
            <!-- Default slot content if parent doesn't provide one -->
            <Link :href="route(item.route, { agent: props.agentId })">
                <component :is="item.icon" class="h-4 w-4" />
                <span>{{ item.label }}{{ getSourceCount(item.statsKey, props.sourceStats) }}</span>
            </Link>
        </slot>
    </template>
</template>
