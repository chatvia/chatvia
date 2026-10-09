<script setup lang="ts">
/**
 * "Attach an existing text" dialog: lists the workspace's trained texts that
 * this agent does not use yet and attaches the picked ones to it.
 */
import { ref, computed, watch } from 'vue';
import { toast } from 'vue-sonner';
import { useI18n } from 'vue-i18n';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Badge } from '@/components/ui/badge';
import { LoaderCircle, FileText, Search, Link2, Users, Plus, Check } from 'lucide-vue-next';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { attachText as attachTextToAgent, fetchAttachableTexts, type AttachableText } from '@/api/attachableSources';
import { errorMessage } from '@/api/http';

const { t } = useI18n();

interface Props {
    open: boolean;
    agentId: string;
}

type AvailableText = AttachableText;

const props = defineProps<Props>();
const emit = defineEmits<{
    'update:open': [value: boolean];
    textAttached: [];
}>();

const isLoading = ref(false);
const availableTexts = ref<AvailableText[]>([]);
const searchQuery = ref('');
const addedTextIds = ref<Set<number>>(new Set());
const attachingTextIds = ref<Set<number>>(new Set());

const filteredTexts = computed(() => {
    if (!searchQuery.value.trim()) {
        return availableTexts.value;
    }

    const query = searchQuery.value.toLowerCase().trim();
    return availableTexts.value.filter((text) => text.excerpt.toLowerCase().includes(query));
});

const isTextAdded = (textId: number) => addedTextIds.value.has(textId);

const isTextAttaching = (textId: number) => attachingTextIds.value.has(textId);

const fetchAvailableTexts = async () => {
    isLoading.value = true;
    try {
        availableTexts.value = await fetchAttachableTexts(props.agentId);
    } catch (error) {
        console.error('Error fetching available texts:', error);
        toast.error(t('shared.attach_text.toast_load_failed'));
    } finally {
        isLoading.value = false;
    }
};

const attachText = async (text: AvailableText) => {
    if (isTextAdded(text.id) || isTextAttaching(text.id)) return;

    attachingTextIds.value.add(text.id);

    try {
        await attachTextToAgent(props.agentId, text.id);

        addedTextIds.value.add(text.id);
        toast.success(t('shared.attach_text.toast_success'));
        emit('textAttached');
    } catch (error: unknown) {
        console.error('Error attaching text:', error);
        toast.error(errorMessage(error, t('shared.attach_text.toast_failed')));
    } finally {
        attachingTextIds.value.delete(text.id);
    }
};

watch(
    () => props.open,
    (isOpen) => {
        if (isOpen) {
            fetchAvailableTexts();
            addedTextIds.value.clear();
            attachingTextIds.value.clear();
        } else {
            searchQuery.value = '';
        }
    },
);
</script>

<template>
    <Dialog :open="open" @update:open="(value) => emit('update:open', value)">
        <DialogContent class="max-h-[85vh] max-w-3xl overflow-hidden">
            <DialogHeader class="pe-6">
                <DialogTitle class="flex items-center gap-2">
                    <Link2 class="h-5 w-5 shrink-0 text-blue-600" />
                    <span class="truncate">{{ t('shared.attach_text.title') }}</span>
                </DialogTitle>
                <DialogDescription class="pe-8">
                    {{ t('shared.attach_text.description') }}
                </DialogDescription>
            </DialogHeader>

            <div class="space-y-4 overflow-hidden">
                <div class="relative w-full">
                    <Search class="text-muted-foreground pointer-events-none absolute start-3 top-1/2 z-10 h-4 w-4 -translate-y-1/2" />
                    <Input
                        v-model="searchQuery"
                        :placeholder="t('shared.attach_text.search_placeholder')"
                        class="w-full ps-9"
                        :disabled="isLoading"
                    />
                </div>

                <div v-if="isLoading" class="flex items-center justify-center py-8">
                    <LoaderCircle class="me-2 h-6 w-6 animate-spin text-blue-600" />
                    <span class="text-muted-foreground">{{ t('shared.attach_text.loading') }}</span>
                </div>

                <div v-else-if="availableTexts.length === 0" class="text-muted-foreground py-8 text-center">
                    <FileText class="mx-auto mb-2 h-8 w-8 opacity-50" />
                    <p>{{ t('shared.attach_text.empty_title') }}</p>
                    <p class="mt-1 text-sm">{{ t('shared.attach_text.empty_description') }}</p>
                </div>

                <div v-else-if="filteredTexts.length === 0" class="text-muted-foreground py-8 text-center">
                    <Search class="mx-auto mb-2 h-8 w-8 opacity-50" />
                    <p>{{ t('shared.attach_text.no_search_results') }}</p>
                </div>

                <ScrollArea v-else class="h-[450px]">
                    <div class="space-y-3 pe-4">
                        <div
                            v-for="text in filteredTexts"
                            :key="text.id"
                            class="flex w-full items-start gap-3 rounded-lg border p-4 transition-colors"
                            :class="{
                                'border-green-500 bg-green-50 dark:bg-green-950/20': isTextAdded(text.id),
                                'border-border': !isTextAdded(text.id),
                            }"
                        >
                            <div class="bg-accent/50 shrink-0 rounded-lg p-2">
                                <FileText class="text-foreground h-5 w-5" />
                            </div>

                            <div class="min-w-0 flex-1 overflow-hidden">
                                <p class="text-muted-foreground mb-2 line-clamp-2 text-sm">
                                    {{ text.excerpt }}
                                </p>

                                <div class="text-muted-foreground flex flex-wrap items-center gap-2 text-xs">
                                    <Badge variant="secondary" class="shrink-0 text-xs font-medium">
                                        {{ t('shared.attach_text.word_count', { count: text.word_count }) }}
                                    </Badge>
                                    <span v-if="text.agents && text.agents.length > 0" class="flex shrink-0 items-center gap-1">
                                        <span>•</span>
                                        <Users class="h-3 w-3" />
                                        <TooltipProvider>
                                            <Tooltip>
                                                <TooltipTrigger as-child>
                                                    <span class="hover:text-foreground cursor-help whitespace-nowrap">
                                                        {{
                                                            t(
                                                                'shared.attach_text.agents_count',
                                                                { count: text.agents.length },
                                                                { plural: text.agents.length },
                                                            )
                                                        }}
                                                    </span>
                                                </TooltipTrigger>
                                                <TooltipContent side="top">
                                                    <div class="space-y-1">
                                                        <p class="mb-1 text-xs font-semibold">{{ t('shared.attach_text.used_in') }}</p>
                                                        <ul class="list-inside list-disc space-y-0.5">
                                                            <li v-for="agent in text.agents" :key="agent.id" class="text-xs">
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

                            <div class="shrink-0">
                                <Button
                                    v-if="!isTextAdded(text.id)"
                                    size="sm"
                                    :disabled="isTextAttaching(text.id)"
                                    class="h-8 whitespace-nowrap"
                                    @click="attachText(text)"
                                >
                                    <LoaderCircle v-if="isTextAttaching(text.id)" class="h-4 w-4 animate-spin" />
                                    <template v-else>
                                        <Plus class="me-1 h-4 w-4" />
                                        {{ t('shared.attach_text.add') }}
                                    </template>
                                </Button>
                                <div
                                    v-else
                                    class="flex items-center gap-1.5 text-sm font-medium whitespace-nowrap text-green-600 dark:text-green-500"
                                >
                                    <Check class="h-4 w-4 shrink-0" />
                                    <span>{{ t('shared.attach_text.added') }}</span>
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
