<script setup lang="ts">
/**
 * The expandable list of pages discovered on a website: search, "select all
 * on this page", one row per link with a delete button, bulk delete of the
 * ticked links, and previous/next paging. All state and actions come from the
 * `links` controller (useDiscoveredLinks), keyed by the website id.
 */
import { Button } from '@/shared/ui/button';
import { Checkbox } from '@/shared/ui/checkbox';
import { Input } from '@/shared/ui/input';
import { Label } from '@/shared/ui/label';
import { ScrollArea } from '@/shared/ui/scroll-area';
import type { DiscoveredLinks } from '@/dashboard/agents/composables/sources/web/useDiscoveredLinks';
import type { WebSource } from '@/dashboard/agents/composables/sources/web/useWebSources';
import { ChevronRight, ExternalLink, LoaderCircle, Trash2 } from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps<{
    source: WebSource;
    links: DiscoveredLinks;
}>();

const { t } = useI18n();

const websiteId = computed(() => props.source.id);
const pageLinks = computed(() => props.source.discovered_links ?? []);
const pagination = computed(() => props.links.paginationFor(websiteId.value));
const searchQuery = computed(() => props.links.searchQueryFor(websiteId.value));
const selected = computed(() => props.links.selectedFor(websiteId.value));
const pageSelection = computed(() => props.links.pageSelectionState(websiteId.value, pageLinks.value));
const isLoading = computed(() => !props.source.discovered_links || !!pagination.value?.isLoading);

const onSearch = (event: Event) => props.links.search(websiteId.value, (event.target as HTMLInputElement).value);
</script>

<template>
    <div class="bg-muted/20 border-t p-3">
        <div class="mb-3 flex items-center justify-between">
            <div class="text-muted-foreground text-sm font-medium">
                {{ t('agents.sources.web.discovered_links') }}
                <span v-if="searchQuery.trim()" class="ms-1 text-xs text-blue-600">{{ t('agents.sources.web.filtered') }}</span>
            </div>
            <Button variant="destructive" size="sm" :disabled="selected.size === 0" class="h-7 text-xs" @click="links.deleteSelected(websiteId)">
                <Trash2 class="me-1 h-3 w-3" />
                {{ t('agents.sources.web.delete_selected_links') }}
            </Button>
        </div>

        <div v-if="source.discovered_links" class="mb-3">
            <Input :value="searchQuery" :placeholder="t('agents.sources.web.search_links_placeholder')" class="h-8 text-xs" @input="onSearch" />
        </div>

        <div v-if="source.discovered_links" class="bg-background mb-3 flex items-center gap-2 rounded border p-2">
            <Checkbox
                :id="`select-all-${websiteId}`"
                :model-value="pageSelection.allSelected"
                :indeterminate="pageSelection.someSelected"
                class="data-[state=checked]:bg-primary data-[state=checked]:border-primary"
                @update:model-value="(checked) => links.toggleAllOnPage(websiteId, pageLinks, checked)"
            />
            <Label :for="`select-all-${websiteId}`" class="cursor-pointer text-xs font-medium select-none">
                {{ t('agents.sources.web.select_all_on_page', { count: pageLinks.length }) }}
            </Label>
        </div>

        <ScrollArea class="h-72 w-full rounded-md border">
            <div class="p-4">
                <div v-if="isLoading" class="flex items-center justify-center py-8">
                    <LoaderCircle class="text-muted-foreground h-6 w-6 animate-spin" />
                    <span class="text-muted-foreground ms-2 text-sm">{{ t('agents.sources.web.loading_links') }}</span>
                </div>

                <div v-else class="space-y-2">
                    <div
                        v-for="link in pageLinks"
                        :key="link.url"
                        class="bg-background hover:bg-muted/50 group flex items-start gap-2 rounded border p-2 text-xs transition-colors"
                    >
                        <Checkbox
                            :id="`link-${websiteId}-${link.id}`"
                            :model-value="selected.has(link.id)"
                            class="data-[state=checked]:bg-primary data-[state=checked]:border-primary mt-0.5"
                            @update:model-value="(checked) => links.toggleLink(websiteId, link.id, checked)"
                        />
                        <ExternalLink class="text-muted-foreground mt-0.5 h-3 w-3 flex-shrink-0" />
                        <div class="min-w-0 flex-1">
                            <a
                                :href="link.url"
                                target="_blank"
                                class="text-foreground hover:text-primary block truncate text-sm hover:underline"
                                @click.stop
                            >
                                {{ link.url }}
                            </a>
                        </div>
                        <Button
                            variant="ghost"
                            size="sm"
                            :aria-label="t('agents.sources.web.delete_this_link')"
                            class="text-destructive hover:text-destructive/80 h-6 w-6 p-0 opacity-0 transition-opacity group-hover:opacity-100"
                            @click="links.deleteLink(websiteId, link.id)"
                        >
                            <Trash2 class="h-3 w-3" />
                        </Button>
                    </div>

                    <div v-if="pageLinks.length === 0" class="text-muted-foreground py-4 text-center text-xs">
                        {{ t('agents.sources.web.no_links_match') }}
                    </div>
                </div>
            </div>
        </ScrollArea>

        <div v-if="pagination" class="mt-3 flex items-center justify-between px-2">
            <Button
                variant="outline"
                size="sm"
                :disabled="!pagination.hasPrevPage || pagination.isLoading"
                class="h-8"
                @click="links.prevPage(websiteId)"
            >
                <ChevronRight class="me-1 h-4 w-4 rotate-180" />
                {{ t('agents.sources.web.previous') }}
            </Button>
            <span class="text-muted-foreground text-xs">
                {{ t('agents.sources.web.page', { page: pagination.currentPage || 1 }) }}
            </span>
            <Button
                variant="outline"
                size="sm"
                :disabled="!pagination.hasNextPage || pagination.isLoading"
                class="h-8"
                @click="links.nextPage(websiteId)"
            >
                {{ t('agents.sources.web.next') }}
                <ChevronRight class="ms-1 h-4 w-4" />
            </Button>
        </div>
    </div>
</template>
