<script setup lang="ts">
/**
 * "Trained knowledge base": the agent's files that are training, trained or
 * failed, with a button to reuse a file another agent already trained. Rows
 * can be ticked, one by one or all at once, and removed together. More than
 * five rows scroll inside a fixed-height area; further pages load with
 * "Load more" ("select all" covers the rows loaded so far).
 */
import type { SourceFile } from '@/dashboard/agents/api/fileSources';
import LoadMoreButton from '@/dashboard/agents/components/sources/LoadMoreButton.vue';
import TrainedFileRow from '@/dashboard/agents/components/sources/files/TrainedFileRow.vue';
import { Button } from '@/shared/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/shared/ui/card';
import { Checkbox } from '@/shared/ui/checkbox';
import { Label } from '@/shared/ui/label';
import { ScrollArea } from '@/shared/ui/scroll-area';
import { Check, Database, Link as LinkIcon, LoaderCircle, Minus, Trash2 } from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps<{
    files: SourceFile[];
    /** The ticked files, by id. */
    selectedIds: Set<number>;
    allSelected: boolean;
    /** Some rows are ticked, but not all of them. */
    someSelected: boolean;
    /** The ticked files are being removed. */
    isRemoving: boolean;
    /** How many such files the agent has, loaded or not. */
    total: number;
    hasMore: boolean;
    isLoadingMore: boolean;
    /** True only during the very first load, before any list arrived. */
    isLoading: boolean;
    isBusy: boolean;
}>();

const emit = defineEmits<{
    toggle: [file: SourceFile, checked: boolean | 'indeterminate'];
    toggleAll: [checked: boolean | 'indeterminate'];
    removeSelected: [];
    attach: [];
    loadMore: [];
    summary: [file: SourceFile];
    replace: [file: SourceFile];
    remove: [file: SourceFile];
}>();

const { t } = useI18n();

const selectAllState = computed<boolean | 'indeterminate'>(() => (props.allSelected ? true : props.someSelected ? 'indeterminate' : false));
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
                <template v-else>
                    <div class="bg-muted/30 flex items-center justify-between rounded border p-2" :class="{ 'me-4': files.length > 5 }">
                        <div class="flex items-center gap-2">
                            <Checkbox
                                id="select-all-trained-files"
                                :model-value="selectAllState"
                                :disabled="isBusy || isRemoving"
                                class="data-[state=indeterminate]:bg-primary data-[state=indeterminate]:text-primary-foreground data-[state=indeterminate]:border-primary"
                                @update:model-value="emit('toggleAll', $event)"
                            >
                                <Minus v-if="someSelected" class="size-3.5" />
                                <Check v-else class="size-3.5" />
                            </Checkbox>
                            <Label for="select-all-trained-files" class="cursor-pointer text-xs font-medium select-none">
                                {{ t('agents.sources.files.select_all', { count: files.length }) }}
                            </Label>
                        </div>
                        <Button
                            variant="destructive"
                            size="sm"
                            :disabled="selectedIds.size === 0 || isBusy || isRemoving"
                            class="h-7 text-xs"
                            @click="emit('removeSelected')"
                        >
                            <LoaderCircle v-if="isRemoving" class="me-1 h-3 w-3 animate-spin" />
                            <Trash2 v-else class="me-1 h-3 w-3" />
                            {{ t('agents.sources.files.delete_selected', { count: selectedIds.size }) }}
                        </Button>
                    </div>
                    <component :is="files.length > 5 ? ScrollArea : 'div'" :class="{ 'h-[400px] pe-4': files.length > 5 }">
                        <div class="space-y-2">
                            <TrainedFileRow
                                v-for="file in files"
                                :key="file.id"
                                :file="file"
                                :selected="selectedIds.has(file.id)"
                                :is-busy="isBusy || isRemoving"
                                @toggle="emit('toggle', file, $event)"
                                @summary="emit('summary', $event)"
                                @replace="emit('replace', $event)"
                                @remove="emit('remove', $event)"
                            />
                        </div>
                    </component>
                </template>
                <LoadMoreButton v-if="hasMore && !isLoading" :is-loading="isLoadingMore" @click="emit('loadMore')" />
            </div>
        </CardContent>
    </Card>
</template>
