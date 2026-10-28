<script setup lang="ts">
/**
 * "Attach an existing file" dialog: lists the workspace's trained files of one
 * type that this agent does not use yet and attaches the picked ones to it.
 */
import { ref, computed, watch } from 'vue';
import { toast } from 'vue-sonner';
import { useI18n } from 'vue-i18n';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/shared/ui/dialog';
import { Button } from '@/shared/ui/button';
import { Input } from '@/shared/ui/input';
import { ScrollArea } from '@/shared/ui/scroll-area';
import { Badge } from '@/shared/ui/badge';
import { LoaderCircle, FileText, Search, Link2, Users, Plus, Check } from 'lucide-vue-next';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/shared/ui/tooltip';
import { attachFile as attachFileToAgent, fetchAttachableFiles, type AttachableFile } from '@/dashboard/agents/api/attachableSources';
import { errorMessage } from '@/shared/api/http';

const { t } = useI18n();

interface Props {
    open: boolean;
    agentId: string;
    fileType?: string;
}

type AvailableFile = AttachableFile;

const props = defineProps<Props>();
const emit = defineEmits<{
    'update:open': [value: boolean];
    fileAttached: [];
}>();

const isLoading = ref(false);
const availableFiles = ref<AvailableFile[]>([]);
const searchQuery = ref('');
const addedFileIds = ref<Set<number>>(new Set());
const attachingFileIds = ref<Set<number>>(new Set());

const filteredFiles = computed(() => {
    if (!searchQuery.value.trim()) {
        return availableFiles.value;
    }

    const query = searchQuery.value.toLowerCase().trim();
    return availableFiles.value.filter((file) => file.filename.toLowerCase().includes(query));
});

const isFileAdded = (fileId: number) => addedFileIds.value.has(fileId);

const isFileAttaching = (fileId: number) => attachingFileIds.value.has(fileId);

const fetchAvailableFiles = async () => {
    isLoading.value = true;
    try {
        availableFiles.value = await fetchAttachableFiles(props.agentId, props.fileType);
    } catch (error) {
        console.error('Error fetching available files:', error);
        toast.error(t('shared.attach_file.toast_load_failed'));
    } finally {
        isLoading.value = false;
    }
};

const attachFile = async (file: AvailableFile) => {
    if (isFileAdded(file.id) || isFileAttaching(file.id)) return;

    attachingFileIds.value.add(file.id);

    try {
        await attachFileToAgent(props.agentId, file.id);

        addedFileIds.value.add(file.id);
        toast.success(t('shared.attach_file.toast_success', { filename: file.filename }));
        emit('fileAttached');
    } catch (error: unknown) {
        console.error('Error attaching file:', error);
        toast.error(errorMessage(error, t('shared.attach_file.toast_failed')));
    } finally {
        attachingFileIds.value.delete(file.id);
    }
};

const formatFileType = (fileType: string): string => {
    const typeMap: Record<string, string> = {
        pdf: t('shared.attach_file.type_pdf'),
        word: t('shared.attach_file.type_word'),
        audio: t('shared.attach_file.type_audio'),
        video: t('shared.attach_file.type_video'),
    };
    return typeMap[fileType] || fileType.toUpperCase();
};

const formatFileLength = (file: AvailableFile): string => {
    if (!file.length) return '';

    const count = Number(file.length);
    if (file.filetype === 'audio' || file.filetype === 'video') {
        return t('shared.attach_file.minute_length', { count }, { plural: count });
    }

    return t('shared.attach_file.page_length', { count }, { plural: count });
};

watch(
    () => props.open,
    (isOpen) => {
        if (isOpen) {
            fetchAvailableFiles();
            addedFileIds.value.clear();
            attachingFileIds.value.clear();
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
                    <span class="truncate">{{ t('shared.attach_file.title') }}</span>
                </DialogTitle>
                <DialogDescription class="pe-8">
                    {{ t('shared.attach_file.description') }}
                    <span v-if="fileType">{{ t('shared.attach_file.filter_type_suffix', { type: formatFileType(fileType) }) }}</span>
                </DialogDescription>
            </DialogHeader>

            <div class="space-y-4 overflow-hidden">
                <div class="relative w-full">
                    <Search class="text-muted-foreground pointer-events-none absolute start-3 top-1/2 z-10 h-4 w-4 -translate-y-1/2" />
                    <Input
                        v-model="searchQuery"
                        :placeholder="t('shared.attach_file.search_placeholder')"
                        class="w-full ps-9"
                        :disabled="isLoading"
                    />
                </div>

                <div v-if="isLoading" class="flex items-center justify-center py-8">
                    <LoaderCircle class="me-2 h-6 w-6 animate-spin text-blue-600" />
                    <span class="text-muted-foreground">{{ t('shared.attach_file.loading') }}</span>
                </div>

                <div v-else-if="availableFiles.length === 0" class="text-muted-foreground py-8 text-center">
                    <FileText class="mx-auto mb-2 h-8 w-8 opacity-50" />
                    <p>{{ t('shared.attach_file.empty_title') }}</p>
                    <p class="mt-1 text-sm">{{ t('shared.attach_file.empty_description') }}</p>
                </div>

                <div v-else-if="filteredFiles.length === 0" class="text-muted-foreground py-8 text-center">
                    <Search class="mx-auto mb-2 h-8 w-8 opacity-50" />
                    <p>{{ t('shared.attach_file.no_search_results') }}</p>
                </div>

                <ScrollArea v-else class="h-[450px]">
                    <div class="space-y-3 pe-4">
                        <div
                            v-for="file in filteredFiles"
                            :key="file.id"
                            class="flex w-full items-start gap-3 rounded-lg border p-4 transition-colors"
                            :class="{
                                'border-green-500 bg-green-50 dark:bg-green-950/20': isFileAdded(file.id),
                                'border-border': !isFileAdded(file.id),
                            }"
                        >
                            <div class="bg-accent/50 shrink-0 rounded-lg p-2">
                                <FileText class="text-foreground h-5 w-5" />
                            </div>

                            <div class="min-w-0 flex-1 overflow-hidden">
                                <TooltipProvider>
                                    <Tooltip>
                                        <TooltipTrigger as-child>
                                            <h4 class="mb-1 cursor-help truncate text-sm font-semibold">
                                                {{ file.filename }}
                                            </h4>
                                        </TooltipTrigger>
                                        <TooltipContent side="top" class="max-w-sm">
                                            <p class="break-words">{{ file.filename }}</p>
                                        </TooltipContent>
                                    </Tooltip>
                                </TooltipProvider>

                                <div class="text-muted-foreground flex flex-wrap items-center gap-2 text-xs">
                                    <Badge variant="secondary" class="shrink-0 text-xs font-medium">
                                        {{ formatFileType(file.filetype) }}
                                    </Badge>
                                    <span v-if="formatFileLength(file)" class="flex shrink-0 items-center gap-1">
                                        <span>•</span>
                                        {{ formatFileLength(file) }}
                                    </span>
                                    <span v-if="file.agents && file.agents.length > 0" class="flex shrink-0 items-center gap-1">
                                        <span>•</span>
                                        <Users class="h-3 w-3" />
                                        <TooltipProvider>
                                            <Tooltip>
                                                <TooltipTrigger as-child>
                                                    <span class="hover:text-foreground cursor-help whitespace-nowrap">
                                                        {{
                                                            t(
                                                                'shared.attach_file.agents_count',
                                                                { count: file.agents.length },
                                                                { plural: file.agents.length },
                                                            )
                                                        }}
                                                    </span>
                                                </TooltipTrigger>
                                                <TooltipContent side="top">
                                                    <div class="space-y-1">
                                                        <p class="mb-1 text-xs font-semibold">{{ t('shared.attach_file.used_in') }}</p>
                                                        <ul class="list-inside list-disc space-y-0.5">
                                                            <li v-for="agent in file.agents" :key="agent.id" class="text-xs">
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
                                    v-if="!isFileAdded(file.id)"
                                    size="sm"
                                    :disabled="isFileAttaching(file.id)"
                                    class="h-8 whitespace-nowrap"
                                    @click="attachFile(file)"
                                >
                                    <LoaderCircle v-if="isFileAttaching(file.id)" class="h-4 w-4 animate-spin" />
                                    <template v-else>
                                        <Plus class="me-1 h-4 w-4" />
                                        {{ t('shared.attach_file.add') }}
                                    </template>
                                </Button>
                                <div
                                    v-else
                                    class="flex items-center gap-1.5 text-sm font-medium whitespace-nowrap text-green-600 dark:text-green-500"
                                >
                                    <Check class="h-4 w-4 shrink-0" />
                                    <span>{{ t('shared.attach_file.added') }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </ScrollArea>
            </div>
        </DialogContent>
    </Dialog>
</template>
