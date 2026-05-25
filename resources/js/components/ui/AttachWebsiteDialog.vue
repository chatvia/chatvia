<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import axios from 'axios';
import { toast } from 'vue-sonner';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Badge } from '@/components/ui/badge';
import { LoaderCircle, Globe, Search, CheckCircle, Link2, Users, Plus, Check } from 'lucide-vue-next';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';

interface Props {
    open: boolean;
    agentId: string | number; // Accept external_id (string) or id (number)
}

interface AvailableWebsite {
    id: number;
    url: string;
    urls_count: number;
    created_at: string;
    agents?: Array<{
        id: number;
        external_id: string;
        name: string;
    }>;
}

const props = defineProps<Props>();
const emit = defineEmits<{
    'update:open': [value: boolean];
    'websiteAttached': [];
}>();

// State
const isLoading = ref(false);
const availableWebsites = ref<AvailableWebsite[]>([]);
const searchQuery = ref('');
const addedWebsiteIds = ref<Set<number>>(new Set());
const attachingWebsiteIds = ref<Set<number>>(new Set());

// Computed
const filteredWebsites = computed(() => {
    if (!searchQuery.value.trim()) {
        return availableWebsites.value;
    }

    const query = searchQuery.value.toLowerCase().trim();
    return availableWebsites.value.filter(website =>
        website.url.toLowerCase().includes(query)
    );
});

const isWebsiteAdded = (websiteId: number) => {
    return addedWebsiteIds.value.has(websiteId);
};

const isWebsiteAttaching = (websiteId: number) => {
    return attachingWebsiteIds.value.has(websiteId);
};

// Methods
const fetchAvailableWebsites = async () => {
    isLoading.value = true;
    try {
        const response = await axios.get(route('agents.sources.trained-websites.index', { agent: props.agentId }));
        availableWebsites.value = response.data;
    } catch (error: any) {
        console.error('Error fetching available websites:', error);
        toast.error('Failed to load available websites.');
    } finally {
        isLoading.value = false;
    }
};

const attachWebsite = async (website: AvailableWebsite) => {
    if (isWebsiteAdded(website.id) || isWebsiteAttaching(website.id)) return;

    attachingWebsiteIds.value.add(website.id);

    try {
        await axios.post(route('agents.sources.trained-websites.link', {
            agent: props.agentId,
            websiteId: website.id
        }), {
            website_id: website.id
        });

        addedWebsiteIds.value.add(website.id);
        toast.success('Website added to agent successfully!');
        emit('websiteAttached');
    } catch (error: any) {
        console.error('Error attaching website:', error);
        const errorMessage = error.response?.data?.message || 'Failed to add website to agent.';
        toast.error(errorMessage);
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

const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
    });
};

// Watch for dialog open/close
watch(() => props.open, (isOpen) => {
    if (isOpen) {
        fetchAvailableWebsites();
    } else {
        closeDialog();
    }
});
</script>

<template>
    <Dialog :open="open" @update:open="(val) => emit('update:open', val)">
        <DialogContent class="max-w-3xl max-h-[80vh] flex flex-col">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2">
                    <Link2 class="h-5 w-5" />
                    Attach Trained Website
                </DialogTitle>
                <DialogDescription>
                    Select a website that has already been trained to attach to this agent.
                </DialogDescription>
            </DialogHeader>

            <!-- Search Bar -->
            <div class="relative">
                <Search class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                <Input
                    v-model="searchQuery"
                    type="text"
                    placeholder="Search websites by URL..."
                    class="pl-9"
                />
            </div>

            <!-- Websites List -->
            <ScrollArea class="flex-1 pr-4">
                <div v-if="isLoading" class="flex items-center justify-center py-12">
                    <LoaderCircle class="h-8 w-8 animate-spin text-primary" />
                </div>

                <div v-else-if="availableWebsites.length === 0" class="flex flex-col items-center justify-center py-12 text-center">
                    <Globe class="h-12 w-12 text-muted-foreground mb-4" />
                    <p class="text-sm text-muted-foreground">
                        No trained websites available to attach.
                    </p>
                    <p class="text-xs text-muted-foreground mt-1">
                        Train a website first, then you can attach it to multiple agents.
                    </p>
                </div>

                <div v-else-if="filteredWebsites.length === 0" class="flex flex-col items-center justify-center py-12 text-center">
                    <Search class="h-12 w-12 text-muted-foreground mb-4" />
                    <p class="text-sm text-muted-foreground">
                        No websites match your search.
                    </p>
                </div>

                <div v-else class="space-y-3">
                    <div
                        v-for="website in filteredWebsites"
                        :key="website.id"
                        class="border rounded-lg p-4 hover:bg-accent/50 transition-colors"
                        :class="{ 'bg-accent/30': isWebsiteAdded(website.id) }"
                    >
                        <div class="flex items-start justify-between gap-4">
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 mb-1">
                                    <Globe class="h-4 w-4 text-muted-foreground flex-shrink-0" />
                                    <span class="font-medium truncate">{{ getDomainFromUrl(website.url) }}</span>
                                </div>
                                <a
                                    :href="website.url"
                                    target="_blank"
                                    class="text-xs text-muted-foreground hover:underline block truncate"
                                >
                                    {{ website.url }}
                                </a>
                                <div class="flex items-center gap-3 mt-2 text-xs text-muted-foreground">
                                    <span>{{ website.urls_count }} pages</span>
                                    <span>•</span>
                                    <span>{{ formatDate(website.created_at) }}</span>
                                </div>

                                <!-- Show agents using this website -->
                                <div v-if="website.agents && website.agents.length > 0" class="flex items-center gap-2 mt-2">
                                    <TooltipProvider>
                                        <Tooltip>
                                            <TooltipTrigger as-child>
                                                <div class="flex items-center gap-1 text-xs text-muted-foreground">
                                                    <Users class="h-3 w-3" />
                                                    <span>{{ website.agents.length }}</span>
                                                </div>
                                            </TooltipTrigger>
                                            <TooltipContent>
                                                <div class="text-xs">
                                                    <p class="font-medium mb-1">Used in agents:</p>
                                                    <ul class="space-y-0.5">
                                                        <li v-for="agent in website.agents" :key="agent.external_id">
                                                            {{ agent.name }}
                                                        </li>
                                                    </ul>
                                                </div>
                                            </TooltipContent>
                                        </Tooltip>
                                    </TooltipProvider>
                                </div>
                            </div>

                            <!-- Attach Button -->
                            <Button
                                v-if="!isWebsiteAdded(website.id)"
                                size="sm"
                                @click="attachWebsite(website)"
                                :disabled="isWebsiteAttaching(website.id)"
                                class="flex-shrink-0"
                            >
                                <LoaderCircle v-if="isWebsiteAttaching(website.id)" class="h-4 w-4 animate-spin mr-2" />
                                <Plus v-else class="h-4 w-4 mr-2" />
                                {{ isWebsiteAttaching(website.id) ? 'Adding...' : 'Add' }}
                            </Button>

                            <!-- Added Badge -->
                            <Badge v-else variant="secondary" class="flex-shrink-0">
                                <Check class="h-3 w-3 mr-1" />
                                Added
                            </Badge>
                        </div>
                    </div>
                </div>
            </ScrollArea>

            <!-- Footer -->
            <div class="flex items-center justify-between pt-4 border-t">
                <p class="text-sm text-muted-foreground">
                    {{ addedWebsiteIds.size > 0 ? `${addedWebsiteIds.size} website${addedWebsiteIds.size !== 1 ? 's' : ''} added` : 'Select websites to add to this agent' }}
                </p>
                <Button variant="outline" @click="closeDialog">
                    Close
                </Button>
            </div>
        </DialogContent>
    </Dialog>
</template>

