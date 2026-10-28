<script setup lang="ts">
/**
 * "Trained knowledge base": the websites the agent has learned, with a button
 * to link a website another agent already trained. More than five rows
 * scroll inside a fixed-height area.
 */
import TrainedWebsiteRow from '@/dashboard/agents/components/sources/web/TrainedWebsiteRow.vue';
import { Button } from '@/shared/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/shared/ui/card';
import { ScrollArea } from '@/shared/ui/scroll-area';
import type { DiscoveredLinks } from '@/dashboard/agents/composables/sources/web/useDiscoveredLinks';
import type { WebsiteRefreshInterval } from '@/dashboard/agents/api/webSources';
import type { WebSource } from '@/dashboard/agents/composables/sources/web/useWebSources';
import { Database, Link2, LoaderCircle } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';

defineProps<{
    sources: WebSource[];
    links: DiscoveredLinks;
    /** True only during the very first load, before any list arrived. */
    isLoading: boolean;
}>();

const emit = defineEmits<{
    attach: [];
    viewLinks: [source: WebSource];
    delete: [source: WebSource];
    setRefresh: [source: WebSource, interval: WebsiteRefreshInterval];
}>();

const { t } = useI18n();
</script>

<template>
    <Card>
        <CardHeader>
            <div class="flex items-center justify-between">
                <div>
                    <CardTitle class="flex items-center gap-2">
                        <Database class="h-5 w-5" />
                        {{ t('agents.sources.web.trained_title') }}
                    </CardTitle>
                    <CardDescription>
                        {{ t('agents.sources.web.trained_description', sources.length) }}
                    </CardDescription>
                </div>
                <Button variant="outline" size="sm" class="flex items-center gap-2" @click="emit('attach')">
                    <Link2 class="h-4 w-4" />
                    {{ t('agents.sources.web.link_website') }}
                </Button>
            </div>
        </CardHeader>
        <CardContent>
            <div class="space-y-4">
                <div v-if="isLoading" class="text-muted-foreground p-4 text-center">
                    <LoaderCircle class="mx-auto mb-2 h-6 w-6 animate-spin" />
                    {{ t('agents.sources.web.loading_sources') }}
                </div>
                <div v-else-if="sources.length === 0" class="text-muted-foreground p-4 text-center">
                    {{ t('agents.sources.web.no_websites') }}
                </div>
                <component :is="sources.length > 5 ? ScrollArea : 'div'" v-else :class="{ 'h-[400px] pe-4': sources.length > 5 }">
                    <div class="space-y-2">
                        <TrainedWebsiteRow
                            v-for="source in sources"
                            :key="source.id"
                            :source="source"
                            :links="links"
                            @view-links="emit('viewLinks', $event)"
                            @delete="emit('delete', $event)"
                            @set-refresh="(website, interval) => emit('setRefresh', website, interval)"
                        />
                    </div>
                </component>
            </div>
        </CardContent>
    </Card>
</template>
