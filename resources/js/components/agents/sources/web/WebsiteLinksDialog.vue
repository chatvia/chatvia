<script setup lang="ts">
/**
 * The "all links" dialog of a training or trained website: search, select and
 * bulk-delete links, retrain or delete a single link, and page through them.
 * All state and actions come from the `dialog` controller (useWebsiteLinksDialog).
 */
import WebsiteLinkRow from '@/components/agents/sources/web/WebsiteLinkRow.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { ScrollArea } from '@/components/ui/scroll-area';
import type { WebsiteLinksDialog } from '@/composables/agents/sources/web/useWebsiteLinksDialog';
import { ChevronRight, Globe, LoaderCircle, Trash2 } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';

const props = defineProps<{
    dialog: WebsiteLinksDialog;
}>();

const { t } = useI18n();

// The dialog controller never changes, so its refs can be unwrapped here for the template.
const { isOpen, website, links, isLoading, pagination, searchQuery, selectedIds, justTrained, allSelected, someSelected } = props.dialog;

const onOpenChange = (open: boolean) => {
    if (!open) props.dialog.close();
};

const onSearch = (event: Event) => props.dialog.search((event.target as HTMLInputElement).value);
</script>

<template>
    <Dialog :open="isOpen" @update:open="onOpenChange">
        <DialogContent class="flex h-[80vh] max-w-4xl flex-col">
            <DialogHeader>
                <DialogTitle>{{ t('agents.sources.web.dialog_title') }}</DialogTitle>
                <DialogDescription>
                    <div class="flex flex-wrap items-center gap-2">
                        <span v-if="website">{{ t('agents.sources.web.dialog_manage_links', { name: website.name }) }}</span>
                        <span v-if="pagination.total > 0" class="text-foreground font-medium">
                            • {{ t('agents.sources.web.dialog_links_total', pagination.total) }}
                        </span>
                    </div>
                </DialogDescription>
            </DialogHeader>

            <div class="mb-4 space-y-3">
                <Input :value="searchQuery" :placeholder="t('agents.sources.web.search_links_placeholder')" class="h-9" @input="onSearch" />
                <p v-if="searchQuery.trim()" class="text-xs text-blue-600">{{ t('agents.sources.web.showing_filtered') }}</p>

                <div v-if="links.length > 0" class="bg-muted/30 flex items-center justify-between rounded border p-2">
                    <div class="flex items-center gap-2">
                        <Checkbox
                            id="select-all-dialog-links"
                            :model-value="allSelected"
                            :indeterminate="someSelected"
                            class="data-[state=checked]:bg-primary data-[state=checked]:border-primary"
                            @update:model-value="dialog.toggleAll"
                        />
                        <Label for="select-all-dialog-links" class="cursor-pointer text-xs font-medium select-none">
                            {{ t('agents.sources.web.select_all_on_page', { count: links.length }) }}
                        </Label>
                    </div>
                    <Button variant="destructive" size="sm" :disabled="selectedIds.size === 0" class="h-7 text-xs" @click="dialog.deleteSelected">
                        <Trash2 class="me-1 h-3 w-3" />
                        {{ t('agents.sources.web.delete_selected_count', { count: selectedIds.size }) }}
                    </Button>
                </div>
            </div>

            <div class="flex-1 overflow-hidden">
                <div v-if="isLoading && links.length === 0" class="flex items-center justify-center py-12">
                    <LoaderCircle class="text-muted-foreground h-8 w-8 animate-spin" />
                    <span class="text-muted-foreground ms-2 text-sm">{{ t('agents.sources.web.loading_links') }}</span>
                </div>

                <ScrollArea v-else-if="links.length > 0" class="h-full pe-4">
                    <div class="space-y-2">
                        <WebsiteLinkRow
                            v-for="link in links"
                            :key="link.id"
                            :link="link"
                            :selected="selectedIds.has(link.id)"
                            :just-trained="justTrained.has(link.id)"
                            @toggle="(checked) => dialog.toggleLink(link.id, checked)"
                            @retrain="dialog.retrainLink(link.id)"
                            @delete="dialog.deleteLink(link.id)"
                        />
                    </div>
                </ScrollArea>

                <div v-else class="flex flex-col items-center justify-center py-12">
                    <Globe class="text-muted-foreground mb-3 h-12 w-12" />
                    <p class="text-muted-foreground text-sm">
                        {{ searchQuery.trim() ? t('agents.sources.web.no_links_match') : t('agents.sources.web.no_links_found') }}
                    </p>
                </div>
            </div>

            <div v-if="links.length > 0 || pagination.total > 0" class="mt-4 flex flex-shrink-0 items-center justify-between border-t pt-4">
                <Button variant="outline" size="sm" :disabled="!pagination.hasPrevPage || isLoading" class="h-9" @click="dialog.prevPage">
                    <ChevronRight class="me-1 h-4 w-4 rotate-180" />
                    {{ t('agents.sources.web.previous') }}
                </Button>
                <span class="text-muted-foreground text-sm">
                    {{ t('agents.sources.web.page_of', { page: pagination.currentPage, total: pagination.lastPage }) }}
                </span>
                <Button variant="outline" size="sm" :disabled="!pagination.hasNextPage || isLoading" class="h-9" @click="dialog.nextPage">
                    {{ t('agents.sources.web.next') }}
                    <ChevronRight class="ms-1 h-4 w-4" />
                </Button>
            </div>
        </DialogContent>
    </Dialog>
</template>
