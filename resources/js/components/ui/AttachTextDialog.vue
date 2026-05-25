<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import axios from 'axios';
import { toast } from 'vue-sonner';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Badge } from '@/components/ui/badge';
import { LoaderCircle, FileText, Search, CheckCircle, Link2, Users, Plus, Check } from 'lucide-vue-next';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';

interface Props {
    open: boolean;
    agentId: string; // Accept external_id (string) or id (number)
}

interface AvailableText {
    id: number;
    excerpt: string;
    word_count: number;
    agents?: Array<{
        id: number;
        external_id: string;
        name: string;
    }>;
}

const props = defineProps<Props>();
const emit = defineEmits<{
    'update:open': [value: boolean];
    'textAttached': [];
}>();

// State
const isLoading = ref(false);
const availableTexts = ref<AvailableText[]>([]);
const searchQuery = ref('');
const addedTextIds = ref<Set<number>>(new Set());
const attachingTextIds = ref<Set<number>>(new Set());

// Computed
const filteredTexts = computed(() => {
    if (!searchQuery.value.trim()) {
        return availableTexts.value;
    }

    const query = searchQuery.value.toLowerCase().trim();
    return availableTexts.value.filter(text =>
        text.excerpt.toLowerCase().includes(query)
    );
});

const isTextAdded = (textId: number) => {
    return addedTextIds.value.has(textId);
};

const isTextAttaching = (textId: number) => {
    return attachingTextIds.value.has(textId);
};

// Methods
const fetchAvailableTexts = async () => {
    isLoading.value = true;
    try {
        const response = await axios.get(route('agents.sources.texts.list', { agent: props.agentId }));
        availableTexts.value = response.data;
    } catch (error: any) {
        console.error('Error fetching available texts:', error);
        toast.error('Failed to load available texts.');
    } finally {
        isLoading.value = false;
    }
};

const attachText = async (text: AvailableText) => {
    if (isTextAdded(text.id) || isTextAttaching(text.id)) return;

    attachingTextIds.value.add(text.id);

    try {
        await axios.post(route('agents.sources.texts.link', {
            agent: props.agentId,
            textId: text.id
        }), {
            text_id: text.id
        });

        addedTextIds.value.add(text.id);
        toast.success('Text added to agent successfully!');
        emit('textAttached');
    } catch (error: any) {
        console.error('Error attaching text:', error);
        const errorMessage = error.response?.data?.message || 'Failed to add text to agent.';
        toast.error(errorMessage);
    } finally {
        attachingTextIds.value.delete(text.id);
    }
};

const closeDialog = () => {
    emit('update:open', false);
    searchQuery.value = '';
};

// Watch for dialog open state
watch(() => props.open, (isOpen) => {
    if (isOpen) {
        fetchAvailableTexts();
        addedTextIds.value.clear();
        attachingTextIds.value.clear();
    } else {
        searchQuery.value = '';
    }
});
</script>

<template>
    <Dialog :open="open" @update:open="(value) => emit('update:open', value)">
        <DialogContent class="max-w-3xl max-h-[85vh] overflow-hidden">
            <DialogHeader class="pr-6">
                <DialogTitle class="flex items-center gap-2">
                    <Link2 class="h-5 w-5 text-blue-600 flex-shrink-0" />
                    <span class="truncate">Choose from Already Trained Texts</span>
                </DialogTitle>
                <DialogDescription class="pr-8">
                    Select a text from your library that has already been trained to add to this agent.
                </DialogDescription>
            </DialogHeader>

            <div class="space-y-4 overflow-hidden">
                <!-- Search -->
                <div class="relative w-full">
                    <Search class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground pointer-events-none z-10" />
                    <Input
                        v-model="searchQuery"
                        placeholder="Search texts..."
                        class="pl-9 w-full"
                        :disabled="isLoading"
                    />
                </div>

                <!-- Texts List -->
                <div v-if="isLoading" class="flex items-center justify-center py-8">
                    <LoaderCircle class="h-6 w-6 animate-spin text-blue-600 mr-2" />
                    <span class="text-muted-foreground">Loading texts...</span>
                </div>

                <div v-else-if="availableTexts.length === 0" class="text-center py-8 text-muted-foreground">
                    <FileText class="h-8 w-8 mx-auto mb-2 opacity-50" />
                    <p>No trained texts available.</p>
                    <p class="text-sm mt-1">Texts must be fully trained before they can be shared across agents.</p>
                </div>

                <div v-else-if="filteredTexts.length === 0" class="text-center py-8 text-muted-foreground">
                    <Search class="h-8 w-8 mx-auto mb-2 opacity-50" />
                    <p>No texts match your search.</p>
                </div>

                <ScrollArea v-else class="h-[450px]">
                    <div class="space-y-3 pr-4">
                        <div
                            v-for="text in filteredTexts"
                            :key="text.id"
                            class="w-full flex items-start gap-3 border rounded-lg p-4 transition-colors"
                            :class="{
                                'border-green-500 bg-green-50 dark:bg-green-950/20': isTextAdded(text.id),
                                'border-border': !isTextAdded(text.id)
                            }"
                        >
                            <div class="p-2 rounded-lg bg-accent/50 flex-shrink-0">
                                <FileText class="h-5 w-5 text-foreground" />
                            </div>

                            <div class="min-w-0 flex-1 overflow-hidden">
                                <p class="text-sm text-muted-foreground line-clamp-2 mb-2">
                                    {{ text.excerpt }}
                                </p>

                                <div class="flex items-center gap-2 text-xs text-muted-foreground flex-wrap">
                                    <Badge variant="secondary" class="text-xs font-medium flex-shrink-0">
                                        {{ text.word_count }} words
                                    </Badge>
                                    <span v-if="text.agents && text.agents.length > 0" class="flex items-center gap-1 flex-shrink-0">
                                        <span>•</span>
                                        <Users class="h-3 w-3" />
                                        <TooltipProvider>
                                            <Tooltip>
                                                <TooltipTrigger as-child>
                                                    <span class="hover:text-foreground cursor-help whitespace-nowrap">
                                                        {{ text.agents.length }} agent{{ text.agents.length !== 1 ? 's' : '' }}
                                                    </span>
                                                </TooltipTrigger>
                                                <TooltipContent side="top">
                                                    <div class="space-y-1">
                                                        <p class="font-semibold text-xs mb-1">Used in:</p>
                                                        <ul class="list-disc list-inside space-y-0.5">
                                                            <li v-for="agent in text.agents" :key="agent.external_id" class="text-xs">
                                                                {{ agent.name }}
                                                            </li>
                                                        </ul>
                                                    </div>
                                                </TooltipContent>
                                            </Tooltip>
                                        </TooltipProvider>
                                    </span>
                                </div>
                            </div>

                            <div class="flex-shrink-0">
                                <Button
                                    v-if="!isTextAdded(text.id)"
                                    size="sm"
                                    @click="attachText(text)"
                                    :disabled="isTextAttaching(text.id)"
                                    class="h-8 whitespace-nowrap"
                                >
                                    <LoaderCircle v-if="isTextAttaching(text.id)" class="h-4 w-4 animate-spin" />
                                    <template v-else>
                                        <Plus class="h-4 w-4 mr-1" />
                                        Add
                                    </template>
                                </Button>
                                <div v-else class="flex items-center gap-1.5 text-green-600 dark:text-green-500 font-medium text-sm whitespace-nowrap">
                                    <Check class="h-4 w-4 flex-shrink-0" />
                                    <span>Added</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </ScrollArea>
            </div>
        </DialogContent>
    </Dialog>
</template>

<style scoped>
.line-clamp-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
</style>

