<script setup lang="ts">
/**
 * "Attach an existing website" dialog: lists the workspace's crawled websites
 * that this agent does not use yet and attaches the picked ones to it.
 */
import { ref, computed, watch } from 'vue';
import { toast } from 'vue-sonner';
import { useI18n } from 'vue-i18n';
import { useDateFormat } from '@/composables/useDateFormat';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Badge } from '@/components/ui/badge';
import { LoaderCircle, Globe, Search, Link2, Users, Plus, Check } from 'lucide-vue-next';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { attachWebsite as attachWebsiteToAgent, fetchAttachableWebsites, type AttachableWebsite } from '@/api/attachableSources';
import { errorMessage } from '@/api/http';

const { t } = useI18n();
const { formatDate } = useDateFormat();

interface Props {
    open: boolean;
    agentId: string | number;
}

type AvailableWebsite = AttachableWebsite;

const props = defineProps<Props>();
const emit = defineEmits<{
    'update:open': [value: boolean];
    websiteAttached: [];
}>();

const isLoading = ref(false);
const availableWebsites = ref<AvailableWebsite[]>([]);
const searchQuery = ref('');
const addedWebsiteIds = ref<Set<number>>(new Set());
const attachingWebsiteIds = ref<Set<number>>(new Set());

const filteredWebsites = computed(() => {
    if (!searchQuery.value.trim()) {
        return availableWebsites.value;
    }

    const query = searchQuery.value.toLowerCase().trim();
    return availableWebsites.value.filter((website) => website.url.toLowerCase().includes(query));
});

const footerText = computed(() => {
    const count = addedWebsiteIds.value.size;
    return count > 0 ? t('shared.attach_website.footer_added', { count }, { plural: count }) : t('shared.attach_website.footer_hint');
});

const isWebsiteAdded = (websiteId: number) => addedWebsiteIds.value.has(websiteId);

const isWebsiteAttaching = (websiteId: number) => attachingWebsiteIds.value.has(websiteId);

const fetchAvailableWebsites = async () => {
    isLoading.value = true;
    try {
        availableWebsites.value = await fetchAttachableWebsites(props.agentId);
    } catch (error) {
        console.error('Error fetching available websites:', error);
        toast.error(t('shared.attach_website.toast_load_failed'));
    } finally {
        isLoading.value = false;
    }
};

const attachWebsite = async (website: AvailableWebsite) => {
    if (isWebsiteAdded(website.id) || isWebsiteAttaching(website.id)) return;

    attachingWebsiteIds.value.add(website.id);

    try {
        await attachWebsiteToAgent(props.agentId, website.id);

        addedWebsiteIds.value.add(website.id);
        toast.success(t('shared.attach_website.toast_success'));
        emit('websiteAttached');
    } catch (error: unknown) {
        console.error('Error attaching website:', error);
        toast.error(errorMessage(error, t('shared.attach_website.toast_failed')));
    } finally {
        attachingWebsiteIds.value.delete(website.id);
    }
};

const closeDialog = () => {
    emit('update:open', false);
    searchQuery.value = '';
    addedWebsiteIds.value.clear();
};

const getDomainFromUrl = (url: string) => {
    try {
        return new URL(url).hostname;
    } catch {
        return url;
    }
};

watch(
    () => props.open,
    (isOpen) => {
        if (isOpen) {
            fetchAvailableWebsites();
        } else {
            closeDialog();
        }
    },
);
</script>

<template>
    <Dialog :open="open" @update:open="(val) => emit('update:open', val)">
        <DialogContent class="flex max-h-[80vh] max-w-3xl flex-col">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2">
                    <Link2 class="h-5 w-5" />
                    {{ t('shared.attach_website.title') }}
                </DialogTitle>
                <DialogDescription>
                    {{ t('shared.attach_website.description') }}
                </DialogDescription>
            </DialogHeader>

            <div class="relative">
                <Search class="text-muted-foreground absolute start-3 top-1/2 h-4 w-4 -translate-y-1/2" />
                <Input v-model="searchQuery" type="text" :placeholder="t('shared.attach_website.search_placeholder')" class="ps-9" />
            </div>

            <ScrollArea class="flex-1 pe-4">
                <div v-if="isLoading" class="flex items-center justify-center py-12">
                    <LoaderCircle class="text-primary h-8 w-8 animate-spin" />
                </div>

                <div v-else-if="availableWebsites.length === 0" class="flex flex-col items-center justify-center py-12 text-center">
                    <Globe class="text-muted-foreground mb-4 h-12 w-12" />
                    <p class="text-muted-foreground text-sm">
                        {{ t('shared.attach_website.empty_title') }}
                    </p>
                    <p class="text-muted-foreground mt-1 text-xs">
                        {{ t('shared.attach_website.empty_description') }}
                    </p>
                </div>

                <div v-else-if="filteredWebsites.length === 0" class="flex flex-col items-center justify-center py-12 text-center">
                    <Search class="text-muted-foreground mb-4 h-12 w-12" />
                    <p class="text-muted-foreground text-sm">
                        {{ t('shared.attach_website.no_search_results') }}
                    </p>
                </div>

                <div v-else class="space-y-3">
                    <div
                        v-for="website in filteredWebsites"
                        :key="website.id"
                        class="hover:bg-accent/50 rounded-lg border p-4 transition-colors"
                        :class="{ 'bg-accent/30': isWebsiteAdded(website.id) }"
                    >
                        <div class="flex items-start justify-between gap-4">
                            <div class="min-w-0 flex-1">
                                <div class="mb-1 flex items-center gap-2">
                                    <Globe class="text-muted-foreground h-4 w-4 shrink-0" />
                                    <span class="truncate font-medium">{{ getDomainFromUrl(website.url) }}</span>
                                </div>
                                <a :href="website.url" target="_blank" class="text-muted-foreground block truncate text-xs hover:underline">
                                    {{ website.url }}
                                </a>
                                <div class="text-muted-foreground mt-2 flex items-center gap-3 text-xs">
                                    <span>{{ t('shared.attach_website.pages_count', { count: website.urls_count }) }}</span>
                                    <span>•</span>
                                    <span>{{ formatDate(website.created_at) }}</span>
                                </div>

                                <div v-if="website.agents && website.agents.length > 0" class="mt-2 flex items-center gap-2">
                                    <TooltipProvider>
                                        <Tooltip>
                                            <TooltipTrigger as-child>
                                                <div class="text-muted-foreground flex items-center gap-1 text-xs">
                                                    <Users class="h-3 w-3" />
                                                    <span>{{ website.agents.length }}</span>
                                                </div>
                                            </TooltipTrigger>
                                            <TooltipContent>
                                                <div class="text-xs">
                                                    <p class="mb-1 font-medium">{{ t('shared.attach_website.used_in_agents') }}</p>
                                                    <ul class="space-y-0.5">
                                                        <li v-for="agent in website.agents" :key="agent.id">
                                                            {{ agent.name }}
                                                        </li>
                                                    </ul>
                                                </div>
                                            </TooltipContent>
                                        </Tooltip>
                                    </TooltipProvider>
                                </div>
                            </div>

                            <Button
                                v-if="!isWebsiteAdded(website.id)"
                                size="sm"
                                :disabled="isWebsiteAttaching(website.id)"
                                class="shrink-0"
                                @click="attachWebsite(website)"
                            >
                                <LoaderCircle v-if="isWebsiteAttaching(website.id)" class="me-2 h-4 w-4 animate-spin" />
                                <Plus v-else class="me-2 h-4 w-4" />
                                {{ isWebsiteAttaching(website.id) ? t('shared.attach_website.adding') : t('shared.attach_website.add') }}
                            </Button>

                            <Badge v-else variant="secondary" class="shrink-0">
                                <Check class="me-1 h-3 w-3" />
                                {{ t('shared.attach_website.added') }}
                            </Badge>
                        </div>
                    </div>
                </div>
            </ScrollArea>

            <div class="flex items-center justify-between border-t pt-4">
                <p class="text-muted-foreground text-sm">
                    {{ footerText }}
                </p>
                <Button variant="outline" @click="closeDialog">
                    {{ t('common.close') }}
                </Button>
            </div>
        </DialogContent>
    </Dialog>
</template>
