<script lang="ts" setup>
import { useTeamPermissions } from '@/composables/useTeamPermissions';
import { Link } from '@inertiajs/vue3';
import { File, FileAudio, FileText, Globe, HelpCircle, Video } from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

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
const { t } = useI18n();

// Helper function to get source count
const getSourceCount = (statsKey: string, stats: any): string => {
    if (!stats || !(statsKey in stats)) {
        return '';
    }
    const count = stats[statsKey] || 0;
    return count > 0 ? ` (${count})` : '';
};

const menuItemDefinitions = [
    {
        id: 'files',
        labelKey: 'agents.sources.page_title.files',
        icon: File,
        route: 'agents.sources.files',
        statsKey: 'files',
        permission: 'agent.sources.train.files',
    },
    {
        id: 'text',
        labelKey: 'agents.sources.page_title.text',
        icon: FileText,
        route: 'agents.sources.text.index',
        statsKey: 'texts',
        permission: 'agent.sources.train.text',
    },
    {
        id: 'qa',
        labelKey: 'agents.sources.page_title.qa',
        icon: HelpCircle,
        route: 'agents.sources.qa.index',
        statsKey: 'qa',
        permission: 'agent.sources.train.qa',
    },
    {
        id: 'web',
        labelKey: 'agents.sources.page_title.web',
        icon: Globe,
        route: 'agents.sources.web',
        statsKey: 'websites',
        permission: 'agent.sources.train.web',
    },
    {
        id: 'audio',
        labelKey: 'agents.sources.page_title.audio',
        icon: FileAudio,
        route: 'agents.sources.audio',
        statsKey: 'audio',
        permission: 'agent.sources.train.audio',
    },
    {
        id: 'video',
        labelKey: 'agents.sources.page_title.video',
        icon: Video,
        route: 'agents.sources.video',
        statsKey: 'video',
        permission: 'agent.sources.train.video',
    },
];

// Filter menu items based on user permissions
const menuItems = computed(() => {
    return menuItemDefinitions
        .filter((item) => hasPermission(item.permission))
        .map((item) => ({
            ...item,
            label: t(item.labelKey),
        }));
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
