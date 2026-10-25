<script setup lang="ts">
/**
 * "Trained knowledge base": the agent's files that are training, trained or
 * failed, with a button to reuse a file another agent already trained. More
 * than five rows scroll inside a fixed-height area; further pages load with
 * "Load more".
 */
import type { SourceFile } from '@/api/fileSources';
import LoadMoreButton from '@/components/agents/sources/LoadMoreButton.vue';
import TrainedFileRow from '@/components/agents/sources/files/TrainedFileRow.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Database, Link as LinkIcon, LoaderCircle } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';

defineProps<{
    files: SourceFile[];
    /** How many such files the agent has, loaded or not. */
    total: number;
    hasMore: boolean;
    isLoadingMore: boolean;
    /** True only during the very first load, before any list arrived. */
    isLoading: boolean;
    isBusy: boolean;
}>();

const emit = defineEmits<{
    attach: [];
    loadMore: [];
    summary: [file: SourceFile];
    replace: [file: SourceFile];
    remove: [file: SourceFile];
}>();

const { t } = useI18n();
</script>

<template>
    <Card>
        <CardHeader>
            <div class="flex items-center justify-between">
                <div>
                    <CardTitle class="flex items-center gap-2">
                        <Database class="h-5 w-5" />
                        {{ t('agents.sources.files.trained_title') }}
                    </CardTitle>
                    <CardDescription>
                        {{ t('agents.sources.files.trained_description', total) }}
                    </CardDescription>
                </div>
                <Button variant="outline" size="sm" :disabled="isBusy" @click="emit('attach')">
                    <LinkIcon class="me-2 h-4 w-4" />
                    {{ t('agents.sources.files.choose_trained') }}
                </Button>
            </div>
        </CardHeader>
        <CardContent>
            <div class="space-y-4">
                <div v-if="isLoading" class="text-muted-foreground p-4 text-center">
                    <LoaderCircle class="mx-auto mb-2 h-6 w-6 animate-spin" />
                    {{ t('agents.sources.files.loading') }}
                </div>
                <div v-else-if="files.length === 0" class="text-muted-foreground p-4 text-center">
                    {{ t('agents.sources.files.empty') }}
                </div>
                <component :is="files.length > 5 ? ScrollArea : 'div'" v-else :class="{ 'h-[400px] pe-4': files.length > 5 }">
                    <div class="space-y-2">
                        <TrainedFileRow
                            v-for="file in files"
                            :key="file.id"
                            :file="file"
                            :is-busy="isBusy"
                            @summary="emit('summary', $event)"
                            @replace="emit('replace', $event)"
                            @remove="emit('remove', $event)"
                        />
                    </div>
                </component>
                <LoadMoreButton v-if="hasMore && !isLoading" :is-loading="isLoadingMore" @click="emit('loadMore')" />
            </div>
        </CardContent>
    </Card>
</template>
