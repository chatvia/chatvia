<script setup lang="ts">
/**
 * The left part of a website row: globe icon, hostname, link to the site,
 * optionally the crawl path filters, and when it was added.
 *
 * Slots: `leading` (before the globe, e.g. a checkbox), `badges` (after the
 * hostname) and `details` (below the date).
 */
import { useDateFormat } from '@/dashboard/composables/useDateFormat';
import type { WebSource } from '@/dashboard/agents/composables/sources/web/useWebSources';
import { ExternalLink, Globe } from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const props = withDefaults(
    defineProps<{
        source: WebSource;
        /** Show the include/exclude path filters the website was crawled with. */
        showCriteria?: boolean;
    }>(),
    { showCriteria: false },
);

const { t } = useI18n();
const { formatDateTime } = useDateFormat();

const crawlCriteria = computed(() => {
    const { include_paths, exclude_paths } = props.source.options;
    const criteria: string[] = [];

    if (include_paths?.trim()) {
        criteria.push(t('agents.sources.web.criteria_includes', { paths: include_paths }));
    }
    if (exclude_paths?.trim()) {
        criteria.push(t('agents.sources.web.criteria_excludes', { paths: exclude_paths }));
    }

    return criteria.length > 0 ? t('agents.sources.web.crawl_criteria', { criteria: criteria.join(', ') }) : '';
});
</script>

<template>
    <div class="flex min-w-0 flex-1 items-center gap-3">
        <slot name="leading" />
        <Globe class="text-muted-foreground h-5 w-5 flex-shrink-0" />
        <div class="min-w-0 flex-1">
            <div class="mb-1 flex items-center gap-2">
                <span class="truncate font-medium">{{ source.name }}</span>
                <slot name="badges" />
            </div>
            <div class="text-muted-foreground text-xs">
                <a :href="source.url" target="_blank" class="flex items-center gap-1 hover:underline">
                    {{ source.url }}
                    <ExternalLink class="h-3 w-3" />
                </a>
                <div v-if="showCriteria && crawlCriteria" class="mt-1 text-xs font-medium text-blue-600">
                    {{ crawlCriteria }}
                </div>
                <div class="mt-1">
                    <span>{{ t('agents.sources.web.added_at', { date: formatDateTime(source.created_at) }) }}</span>
                </div>
                <slot name="details" />
            </div>
        </div>
    </div>
</template>
