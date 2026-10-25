<script lang="ts" setup>
/**
 * The knowledge-source links (Files, Text, Q&A, Web, Audio, Video) of the agent
 * sidebar, filtered by the teammate's permissions and suffixed with counts.
 * Audio and Video hide while the admin has that file type turned off, and Web
 * while no web scraper is set up, unless the agent already has
 * such sources. Renders through a slot so the sidebar can use it as a
 * dropdown or a submenu.
 */
import type { AgentPageUrl } from '@/composables/agents/layout/useAgentNavigation';
import { type KnowledgeFileType, useKnowledgeFileTypes } from '@/composables/agents/sources/files/useKnowledgeFileTypes';
import { useTeamPermissions } from '@/composables/useTeamPermissions';
import sources from '@/routes/agents/sources';
import { Link, usePage } from '@inertiajs/vue3';
import { File, FileAudio, FileText, Globe, HelpCircle, Video } from 'lucide-vue-next';
import { type Component, computed } from 'vue';
import { useI18n } from 'vue-i18n';

/** Source counts keyed by kind (files, texts, websites, …); missing kinds show no count. */
type SourceCounts = Partial<Record<string, number>>;

const props = withDefaults(
    defineProps<{
        agentId: string;
        sourceStats?: SourceCounts | null;
        variant?: 'submenu' | 'dropdown';
        isActiveRoute: (routeName: string) => boolean;
    }>(),
    {
        sourceStats: () => ({}),
        variant: 'submenu',
    },
);

const { hasPermission } = useTeamPermissions();
const { isAccepted } = useKnowledgeFileTypes();
const { t } = useI18n();
const page = usePage();

// " (3)" suffix for a menu label, or nothing when the kind has no sources.
const getSourceCount = (statsKey: string, stats: SourceCounts | null | undefined): string => {
    if (!stats || !(statsKey in stats)) {
        return '';
    }
    const count = stats[statsKey] || 0;
    return count > 0 ? ` (${count})` : '';
};

interface SourceMenuItem {
    id: string;
    labelKey: string;
    icon: Component;
    /** Route name, only for the active state. */
    route: string;
    url: AgentPageUrl;
    statsKey: string;
    permission: string;
    /** The file type the page uploads, when it uploads only one. */
    fileType?: KnowledgeFileType;
    /** Needs the web scraper (Settings → Knowledge → Web scraper). */
    importsWebsites?: boolean;
}

const menuItemDefinitions: SourceMenuItem[] = [
    {
        id: 'files',
        labelKey: 'agents.sources.page_title.files',
        icon: File,
        route: 'agents.sources.files',
        url: sources.files.url,
        statsKey: 'files',
        permission: 'agent.sources.train.files',
    },
    {
        id: 'text',
        labelKey: 'agents.sources.page_title.text',
        icon: FileText,
        route: 'agents.sources.text.index',
        url: sources.text.index.url,
        statsKey: 'texts',
        permission: 'agent.sources.train.text',
    },
    {
        id: 'qa',
        labelKey: 'agents.sources.page_title.qa',
        icon: HelpCircle,
        route: 'agents.sources.qa.index',
        url: sources.qa.index.url,
        statsKey: 'qa',
        permission: 'agent.sources.train.qa',
    },
    {
        id: 'web',
        labelKey: 'agents.sources.page_title.web',
        icon: Globe,
        route: 'agents.sources.web',
        url: sources.web.url,
        statsKey: 'websites',
        permission: 'agent.sources.train.web',
        importsWebsites: true,
    },
    {
        id: 'audio',
        labelKey: 'agents.sources.page_title.audio',
        icon: FileAudio,
        route: 'agents.sources.audio',
        url: sources.audio.url,
        statsKey: 'audio',
        permission: 'agent.sources.train.audio',
        fileType: 'audio',
    },
    {
        id: 'video',
        labelKey: 'agents.sources.page_title.video',
        icon: Video,
        route: 'agents.sources.video',
        url: sources.video.url,
        statsKey: 'video',
        permission: 'agent.sources.train.video',
        fileType: 'video',
    },
];

// Shown when the type is accepted and its service set up, or when the agent
// still has such sources to manage.
const isAvailable = (item: SourceMenuItem): boolean => {
    if ((props.sourceStats?.[item.statsKey] ?? 0) > 0) {
        return true;
    }

    if (item.importsWebsites && page.props.canImportWebsites === false) {
        return false;
    }

    return !item.fileType || isAccepted(item.fileType);
};

// Filter menu items based on user permissions
const menuItems = computed(() => {
    return menuItemDefinitions
        .filter((item) => hasPermission(item.permission) && isAvailable(item))
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
            <Link :href="item.url(props.agentId)" class="flex items-center gap-2">
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
            <Link :href="item.url(props.agentId)">
                <component :is="item.icon" class="h-4 w-4" />
                <span>{{ item.label }}{{ getSourceCount(item.statsKey, props.sourceStats) }}</span>
            </Link>
        </slot>
    </template>
</template>
