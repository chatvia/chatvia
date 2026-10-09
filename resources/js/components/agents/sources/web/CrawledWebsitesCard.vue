<script setup lang="ts">
/**
 * "Ready for training": the crawled websites, with a "select all" bar that
 * shows how many links the picked websites hold and a Train button that is
 * disabled while the pick does not fit in the remaining link quota.
 */
import type { UsageStats } from '@/api/usage';
import CrawledWebsiteRow from '@/components/agents/sources/web/CrawledWebsiteRow.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import type { DiscoveredLinks } from '@/composables/agents/sources/web/useDiscoveredLinks';
import type { WebSource } from '@/composables/agents/sources/web/useWebSources';
import type { WebsiteTraining } from '@/composables/agents/sources/web/useWebsiteTraining';
import { Database, LoaderCircle, NetworkIcon } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';

const props = defineProps<{
    sources: WebSource[];
    training: WebsiteTraining;
    links: DiscoveredLinks;
    linkUsage: UsageStats | null;
}>();

const emit = defineEmits<{
    delete: [source: WebSource];
}>();

const { t } = useI18n();

// The training controller never changes, so its refs can be unwrapped here for the template.
const { selectedCount, selectedTotalLinks, canTrain, isTraining, allSelected, someSelected } = props.training;
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle class="flex items-center gap-2">
                <NetworkIcon class="h-5 w-5" />
                {{ t('agents.sources.web.crawled_title') }}
            </CardTitle>
            <CardDescription>
                {{ t('agents.sources.web.crawled_count', sources.length) }}
            </CardDescription>
        </CardHeader>
        <CardContent>
            <div class="bg-muted/30 mb-4 flex items-center justify-between rounded-lg p-3">
                <div class="flex items-center gap-3">
                    <Checkbox
                        id="select-all-websites"
                        :model-value="allSelected"
                        :indeterminate="someSelected"
                        class="data-[state=checked]:bg-primary data-[state=checked]:border-primary"
                        @update:model-value="training.toggleAll"
                    />
                    <Label for="select-all-websites" class="cursor-pointer text-sm font-medium select-none">
                        {{ t('agents.sources.web.select_all_websites', { count: sources.length }) }}
                    </Label>
                </div>
                <div class="flex items-center gap-3">
                    <span class="text-muted-foreground text-sm">
                        <template v-if="selectedCount > 0">
                            {{ t('agents.sources.web.selected_with_links', { count: selectedCount, links: selectedTotalLinks }) }}
                        </template>
                        <template v-else>{{ t('agents.sources.web.no_websites_selected') }}</template>
                    </span>
                    <TooltipProvider>
                        <Tooltip>
                            <TooltipTrigger as-child>
                                <Button :disabled="!canTrain || isTraining" size="sm" @click="training.trainSelected">
                                    <LoaderCircle v-if="isTraining" class="me-2 h-4 w-4 animate-spin" />
                                    <Database v-else class="me-2 h-4 w-4" />
                                    {{ t('agents.sources.web.train') }}
                                </Button>
                            </TooltipTrigger>
                            <TooltipContent v-if="!canTrain && selectedCount > 0 && linkUsage">
                                <p>
                                    {{
                                        t('agents.sources.web.train_exceeds_quota', { selected: selectedTotalLinks, remaining: linkUsage.remaining })
                                    }}
                                </p>
                            </TooltipContent>
                        </Tooltip>
                    </TooltipProvider>
                </div>
            </div>

            <div class="space-y-2">
                <CrawledWebsiteRow
                    v-for="source in sources"
                    :key="source.id"
                    :source="source"
                    :training="training"
                    :links="links"
                    :link-usage="linkUsage"
                    @delete="emit('delete', $event)"
                />
            </div>
        </CardContent>
    </Card>
</template>
