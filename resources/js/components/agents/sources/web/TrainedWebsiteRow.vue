<script setup lang="ts">
/**
 * One trained website: choose how often it refreshes on its own, view all its
 * links in the dialog, delete it, and — when its discovered links are still
 * loaded from before training — expand them inline.
 */
import SourceActionButton from '@/components/agents/sources/SourceActionButton.vue';
import DiscoveredLinksPanel from '@/components/agents/sources/web/DiscoveredLinksPanel.vue';
import WebsiteInfo from '@/components/agents/sources/web/WebsiteInfo.vue';
import WebsiteStatusIcon from '@/components/agents/sources/web/WebsiteStatusIcon.vue';
import type { WebsiteRefreshInterval } from '@/api/webSources';
import { Button } from '@/components/ui/button';
import { Select, SelectContent, SelectGroup, SelectItem, SelectLabel, SelectTrigger, SelectValue } from '@/components/ui/select';
import type { DiscoveredLinks } from '@/composables/agents/sources/web/useDiscoveredLinks';
import type { WebSource } from '@/composables/agents/sources/web/useWebSources';
import { useWebsiteStatusLabel } from '@/composables/agents/sources/web/useWebsiteStatusLabel';
import { ChevronDown, ChevronRight, Link2, RefreshCw, Trash2 } from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps<{
    source: WebSource;
    links: DiscoveredLinks;
}>();

const emit = defineEmits<{
    viewLinks: [source: WebSource];
    delete: [source: WebSource];
    setRefresh: [source: WebSource, interval: WebsiteRefreshInterval];
}>();

const { t } = useI18n();
const statusLabel = useWebsiteStatusLabel();

const hasLoadedLinks = computed(() => (props.source.discovered_links?.length ?? 0) > 0);
const isExpanded = computed(() => props.links.isExpanded(props.source.id));

const refreshOptions = computed<{ value: WebsiteRefreshInterval; label: string }[]>(() => [
    { value: 'off', label: t('agents.sources.web.refresh_off') },
    { value: 'daily', label: t('agents.sources.web.refresh_daily') },
    { value: 'weekly', label: t('agents.sources.web.refresh_weekly') },
    { value: 'monthly', label: t('agents.sources.web.refresh_monthly') },
]);

function onRefreshChange(value: unknown): void {
    if (typeof value === 'string') {
        emit('setRefresh', props.source, value as WebsiteRefreshInterval);
    }
}
</script>

<template>
    <div class="rounded-lg border">
        <div class="flex items-center justify-between p-3">
            <WebsiteInfo :source="source">
                <template #badges>
                    <WebsiteStatusIcon :status="source.status" :tooltip="statusLabel(source.status)" />
                </template>
            </WebsiteInfo>
            <div class="flex items-center gap-1">
                <Select :model-value="source.refresh_interval" @update:model-value="onRefreshChange">
                    <SelectTrigger size="sm" class="text-muted-foreground gap-1.5 text-xs" :aria-label="t('agents.sources.web.refresh_label')">
                        <RefreshCw class="size-3.5" />
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectGroup>
                            <SelectLabel class="text-muted-foreground max-w-60 text-xs font-normal whitespace-normal">
                                {{ t('agents.sources.web.refresh_hint') }}
                            </SelectLabel>
                            <SelectItem v-for="option in refreshOptions" :key="option.value" :value="option.value">{{ option.label }}</SelectItem>
                        </SelectGroup>
                    </SelectContent>
                </Select>
                <SourceActionButton
                    :icon="Link2"
                    :label="t('agents.sources.web.view_all_links')"
                    class="text-muted-foreground hover:text-foreground h-8 w-8 flex-shrink-0 p-0"
                    @click="emit('viewLinks', source)"
                />
                <Button
                    v-if="hasLoadedLinks"
                    size="sm"
                    variant="ghost"
                    :aria-label="isExpanded ? t('agents.sources.web.collapse_links') : t('agents.sources.web.expand_links')"
                    class="text-muted-foreground hover:text-foreground h-8 w-8 flex-shrink-0 p-0"
                    @click="links.toggleExpanded(source.id)"
                >
                    <ChevronDown v-if="isExpanded" class="h-4 w-4" />
                    <ChevronRight v-else class="h-4 w-4" />
                </Button>
                <SourceActionButton
                    :icon="Trash2"
                    :label="t('agents.sources.web.delete_source')"
                    class="text-destructive hover:text-destructive/80 h-8 w-8 flex-shrink-0 p-0"
                    @click="emit('delete', source)"
                />
            </div>
        </div>

        <DiscoveredLinksPanel v-if="hasLoadedLinks && isExpanded" :source="source" :links="links" />
    </div>
</template>
