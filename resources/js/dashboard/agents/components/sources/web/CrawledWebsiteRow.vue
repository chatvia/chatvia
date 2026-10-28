<script setup lang="ts">
/**
 * One crawled website waiting for training. It has a checkbox to pick it for
 * training and a delete button. When pages were found it also shows their
 * count and expands to the discovered-links panel.
 */
import DiscoveredLinksPanel from '@/dashboard/agents/components/sources/web/DiscoveredLinksPanel.vue';
import WebsiteInfo from '@/dashboard/agents/components/sources/web/WebsiteInfo.vue';
import WebsiteStatusIcon from '@/dashboard/agents/components/sources/web/WebsiteStatusIcon.vue';
import SourceActionButton from '@/dashboard/agents/components/sources/SourceActionButton.vue';
import { Badge } from '@/shared/ui/badge';
import { Button } from '@/shared/ui/button';
import { Checkbox } from '@/shared/ui/checkbox';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/shared/ui/collapsible';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/shared/ui/tooltip';
import type { DiscoveredLinks } from '@/dashboard/agents/composables/sources/web/useDiscoveredLinks';
import type { WebSource } from '@/dashboard/agents/composables/sources/web/useWebSources';
import type { WebsiteTraining } from '@/dashboard/agents/composables/sources/web/useWebsiteTraining';
import { useWebsiteStatusLabel } from '@/dashboard/agents/composables/sources/web/useWebsiteStatusLabel';
import { ChevronDown, ChevronUp, Trash2 } from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps<{
    source: WebSource;
    training: WebsiteTraining;
    links: DiscoveredLinks;
}>();

const emit = defineEmits<{
    delete: [source: WebSource];
}>();

const { t } = useI18n();
const statusLabel = useWebsiteStatusLabel();

const hasLinks = computed(() => props.source.urls_count > 0);
const isSelected = computed(() => props.training.isSelected(props.source.id));
</script>

<template>
    <Collapsible v-if="hasLinks" v-slot="{ open }" class="rounded-lg border" @update:open="(isOpen) => links.loadOnExpand(source.id, isOpen)">
        <div class="flex items-center justify-between p-3">
            <WebsiteInfo :source="source" show-criteria @click.stop>
                <template #leading>
                    <Checkbox
                        :id="`website-${source.id}`"
                        :model-value="isSelected"
                        class="data-[state=checked]:bg-primary data-[state=checked]:border-primary flex-shrink-0"
                        @update:model-value="(checked) => training.toggle(source.id, checked)"
                    />
                </template>
                <template #badges>
                    <TooltipProvider>
                        <Tooltip>
                            <TooltipTrigger as-child>
                                <Badge variant="secondary" class="text-xs">
                                    {{ t('agents.sources.web.links_badge', { count: source.urls_count }) }}
                                </Badge>
                            </TooltipTrigger>
                            <TooltipContent>
                                <p>{{ statusLabel(source.status) }}</p>
                            </TooltipContent>
                        </Tooltip>
                    </TooltipProvider>
                </template>
            </WebsiteInfo>

            <div class="flex items-center gap-1">
                <CollapsibleTrigger as-child>
                    <Button
                        size="sm"
                        variant="ghost"
                        :aria-label="open ? t('agents.sources.web.collapse_links') : t('agents.sources.web.expand_links')"
                        class="text-muted-foreground hover:text-foreground h-8 w-8 flex-shrink-0 p-0"
                    >
                        <ChevronUp v-if="open" class="h-4 w-4" />
                        <ChevronDown v-else class="h-4 w-4" />
                    </Button>
                </CollapsibleTrigger>
                <SourceActionButton
                    :icon="Trash2"
                    :label="t('agents.sources.web.delete_source')"
                    class="text-destructive hover:text-destructive/80 h-8 w-8 flex-shrink-0 p-0"
                    @click="emit('delete', source)"
                />
            </div>
        </div>

        <CollapsibleContent>
            <DiscoveredLinksPanel :source="source" :links="links" />
        </CollapsibleContent>
    </Collapsible>

    <div v-else class="rounded-lg border p-3">
        <div class="flex items-center justify-between">
            <WebsiteInfo :source="source" show-criteria>
                <template #leading>
                    <Checkbox
                        :id="`website-simple-${source.id}`"
                        :model-value="isSelected"
                        class="data-[state=checked]:bg-primary data-[state=checked]:border-primary flex-shrink-0"
                        @update:model-value="(checked) => training.toggle(source.id, checked)"
                    />
                </template>
                <template #badges>
                    <WebsiteStatusIcon :status="source.status" :tooltip="statusLabel(source.status)" />
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
</template>
