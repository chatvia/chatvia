<script setup lang="ts">
/** "Crawling": websites whose pages are still being discovered. They can only be deleted. */
import SourceActionButton from '@/dashboard/agents/components/sources/SourceActionButton.vue';
import WebsiteInfo from '@/dashboard/agents/components/sources/web/WebsiteInfo.vue';
import WebsiteStatusIcon from '@/dashboard/agents/components/sources/web/WebsiteStatusIcon.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/shared/ui/card';
import type { WebSource } from '@/dashboard/agents/composables/sources/web/useWebSources';
import { NetworkIcon, Trash2 } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';

defineProps<{
    sources: WebSource[];
}>();

const emit = defineEmits<{
    delete: [source: WebSource];
}>();

const { t } = useI18n();

const statusTooltip = (source: WebSource): string => {
    if (source.status === 'crawling') return t('agents.sources.web.tooltip_crawling');
    if (source.status === 'pending') return t('agents.sources.web.tooltip_ready_crawl');
    return t('agents.sources.web.tooltip_processing');
};
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle class="flex items-center gap-2">
                <NetworkIcon class="h-5 w-5" />
                {{ t('agents.sources.web.crawling_title') }}
            </CardTitle>
            <CardDescription>
                {{ t('agents.sources.web.crawling_count', sources.length) }}
            </CardDescription>
        </CardHeader>
        <CardContent>
            <div class="space-y-2">
                <div v-for="source in sources" :key="source.id" class="flex items-center justify-between rounded-lg border p-3">
                    <WebsiteInfo :source="source" show-criteria>
                        <template #badges>
                            <WebsiteStatusIcon :status="source.status" :tooltip="statusTooltip(source)" :spin="source.status === 'crawling'" />
                        </template>
                    </WebsiteInfo>
                    <div class="flex items-center gap-1">
                        <SourceActionButton
                            :icon="Trash2"
                            :label="t('agents.sources.web.delete_source')"
                            class="text-destructive hover:text-destructive/80 h-8 w-8 flex-shrink-0 p-0"
                            @click="emit('delete', source)"
                        />
                    </div>
                </div>
            </div>
        </CardContent>
    </Card>
</template>
