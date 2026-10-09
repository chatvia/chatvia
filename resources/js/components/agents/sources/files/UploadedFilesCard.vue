<script setup lang="ts">
/**
 * "Ready to train": files that were uploaded but not trained yet, with their
 * total pages / minutes, a warning when they would go over the quota, and the
 * Clear all and Train buttons.
 */
import type { SourceFile } from '@/api/fileSources';
import SourceActionButton from '@/components/agents/sources/SourceActionButton.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { useFileFormatting } from '@/composables/agents/sources/files/useFileFormatting';
import { CheckCircle, File, LoaderCircle, Play, Trash2 } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';

defineProps<{
    files: SourceFile[];
    totalPages: number;
    totalMinutes: number;
    wouldExceedQuota: boolean;
    isTraining: boolean;
}>();

const emit = defineEmits<{
    clearAll: [];
    train: [];
    remove: [file: SourceFile];
}>();

const { t } = useI18n();
const { formatFileType, formatFileSize, formatFileLength } = useFileFormatting();
</script>

<template>
    <div class="mt-4">
        <Card>
            <CardHeader class="pb-3">
                <div class="flex items-center justify-between">
                    <div>
                        <CardTitle class="text-base">{{ t('agents.sources.files.ready_to_train') }}</CardTitle>
                        <CardDescription>
                            {{ t('agents.sources.files.uploaded_ready', files.length) }}
                            <span v-if="totalPages > 0" class="ms-1"> • {{ t('agents.sources.files.pages_count', totalPages) }} </span>
                            <span v-if="totalMinutes > 0" class="ms-1"> • {{ t('agents.sources.files.minutes_count', totalMinutes) }} </span>
                            <span v-if="wouldExceedQuota" class="text-destructive ms-1 font-medium">
                                • {{ t('agents.sources.files.exceeds_quota') }}
                            </span>
                        </CardDescription>
                    </div>
                    <div class="flex items-center gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            :disabled="isTraining"
                            class="text-destructive hover:text-destructive/90"
                            @click="emit('clearAll')"
                        >
                            <Trash2 class="me-2 h-4 w-4" />
                            {{ t('agents.sources.files.clear_all') }}
                        </Button>
                        <Button size="sm" :disabled="isTraining || files.length === 0 || wouldExceedQuota" @click="emit('train')">
                            <LoaderCircle v-if="isTraining" class="me-2 h-4 w-4 animate-spin" />
                            <Play v-else class="me-2 h-4 w-4" />
                            {{ isTraining ? t('agents.sources.files.training') : t('agents.sources.files.train') }}
                        </Button>
                    </div>
                </div>
            </CardHeader>
            <CardContent>
                <div class="space-y-2">
                    <div v-for="file in files" :key="file.id" class="flex items-center justify-between rounded-lg border p-3">
                        <div class="flex min-w-0 flex-1 items-center gap-3">
                            <File class="text-muted-foreground h-5 w-5 flex-shrink-0" />
                            <div class="min-w-0 flex-1">
                                <div class="mb-1 flex items-center gap-2">
                                    <span class="truncate font-medium">{{ file.name }}</span>
                                    <Badge variant="secondary" class="text-xs">{{ formatFileType(file.mime_type) }}</Badge>
                                    <Badge variant="outline" class="text-xs">
                                        <CheckCircle class="me-1 h-3 w-3" />
                                        {{ t('agents.sources.files.ready_for_training') }}
                                    </Badge>
                                </div>
                                <div class="text-muted-foreground text-xs">
                                    {{ formatFileSize(file.size_in_kb * 1024) }}
                                    <span v-if="formatFileLength(file)" class="ms-2"> • {{ formatFileLength(file) }} </span>
                                </div>
                            </div>
                        </div>
                        <SourceActionButton
                            :icon="Trash2"
                            :label="t('agents.sources.files.remove_file')"
                            :disabled="isTraining"
                            class="text-destructive hover:text-destructive/80 h-8 w-8 flex-shrink-0 p-0"
                            @click="emit('remove', file)"
                        />
                    </div>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
