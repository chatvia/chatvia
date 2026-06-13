<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import axios from 'axios';
import { toast } from 'vue-sonner';
import { useI18n } from 'vue-i18n';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Badge } from '@/components/ui/badge';
import { LoaderCircle, FileText, Search, Link2, Users, Plus, Check } from 'lucide-vue-next';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';

const { t } = useI18n();

interface Props {
    open: boolean;
    agentId: string;
    fileType?: string;
}

interface AvailableFile {
    id: number;
    filename: string;
    filetype: string;
    length: string;
    agents?: Array<{
        id: number;
        external_id: string;
        name: string;
    }>;
}

const props = defineProps<Props>();
const emit = defineEmits<{
    'update:open': [value: boolean];
    'fileAttached': [];
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
        const params: Record<string, string> = {};
        if (props.fileType) {
            params.type = props.fileType;
        }

        const response = await axios.get(route('agents.sources.list', { agent: props.agentId }), { params });
        availableFiles.value = response.data;
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
        await axios.post(
            route('agents.sources.files.link', {
                agent: props.agentId,
                fileId: file.id,
            }),
            {
                file_id: file.id,
            },
        );

        addedFileIds.value.add(file.id);
        toast.success(t('shared.attach_file.toast_success', { filename: file.filename }));
        emit('fileAttached');
    } catch (error: unknown) {
        console.error('Error attaching file:', error);
        const err = error as { response?: { data?: { message?: string } } };
        const errorMessage = err.response?.data?.message || t('shared.attach_file.toast_failed');
        toast.error(errorMessage);
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
                    <Search class="pointer-events-none absolute start-3 top-1/2 z-10 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
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

                <div v-else-if="availableFiles.length === 0" class="py-8 text-center text-muted-foreground">
                    <FileText class="mx-auto mb-2 h-8 w-8 opacity-50" />
                    <p>{{ t('shared.attach_file.empty_title') }}</p>
                    <p class="mt-1 text-sm">{{ t('shared.attach_file.empty_description') }}</p>
                </div>

                <div v-else-if="filteredFiles.length === 0" class="py-8 text-center text-muted-foreground">
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
                            <div class="shrink-0 rounded-lg bg-accent/50 p-2">
                                <FileText class="h-5 w-5 text-foreground" />
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

                                <div class="flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
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
                                                    <span class="cursor-help whitespace-nowrap hover:text-foreground">
                                                        {{ t('shared.attach_file.agents_count', { count: file.agents.length }, { plural: file.agents.length }) }}
                                                    </span>
                                                </TooltipTrigger>
                                                <TooltipContent side="top">
                                                    <div class="space-y-1">
                                                        <p class="mb-1 text-xs font-semibold">{{ t('shared.attach_file.used_in') }}</p>
                                                        <ul class="list-inside list-disc space-y-0.5">
                                                            <li v-for="agent in file.agents" :key="agent.external_id" class="text-xs">
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
                                <div v-else class="flex items-center gap-1.5 whitespace-nowrap text-sm font-medium text-green-600 dark:text-green-500">
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
