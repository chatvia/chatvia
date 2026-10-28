<script setup lang="ts">
/** Shows the AI analysis of a trained file: key topics, style, audience, complexity, reading time, formatting and a sensitive-data warning. */
import type { FileSummary } from '@/dashboard/agents/api/fileSources';
import { Badge } from '@/shared/ui/badge';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/shared/ui/dialog';
import { AlertCircle, Info, LoaderCircle } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';

defineProps<{
    fileName: string | undefined;
    summary: FileSummary | null;
    isLoading: boolean;
}>();

const open = defineModel<boolean>('open', { required: true });

const { t } = useI18n();
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="max-h-[80vh] max-w-2xl overflow-y-auto">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2">
                    <Info class="h-5 w-5 text-blue-600" />
                    {{ t('agents.sources.files.summary_title', { name: fileName }) }}
                </DialogTitle>
                <DialogDescription>{{ t('agents.sources.files.summary_subtitle') }}</DialogDescription>
            </DialogHeader>

            <div v-if="isLoading" class="flex items-center justify-center py-8">
                <LoaderCircle class="h-8 w-8 animate-spin text-blue-600" />
                <span class="text-muted-foreground ms-2">{{ t('agents.sources.files.summary_loading') }}</span>
            </div>

            <div v-else-if="summary" class="space-y-6">
                <div v-if="summary.key_topics && summary.key_topics.length > 0">
                    <h3 class="text-foreground mb-2 text-sm font-semibold">{{ t('agents.sources.files.summary_key_topics') }}</h3>
                    <div class="flex flex-wrap gap-2">
                        <Badge v-for="topic in summary.key_topics" :key="topic" variant="secondary" class="text-xs">{{ topic }}</Badge>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div v-if="summary.writing_style">
                        <h3 class="text-foreground mb-1 text-sm font-semibold">{{ t('agents.sources.files.summary_writing_style') }}</h3>
                        <p class="text-muted-foreground text-sm capitalize">{{ summary.writing_style }}</p>
                    </div>
                    <div v-if="summary.target_audience">
                        <h3 class="text-foreground mb-1 text-sm font-semibold">{{ t('agents.sources.files.summary_target_audience') }}</h3>
                        <p class="text-muted-foreground text-sm capitalize">{{ summary.target_audience }}</p>
                    </div>
                    <div v-if="summary.complexity_level">
                        <h3 class="text-foreground mb-1 text-sm font-semibold">{{ t('agents.sources.files.summary_complexity') }}</h3>
                        <p class="text-muted-foreground text-sm capitalize">{{ summary.complexity_level }}</p>
                    </div>
                    <div v-if="summary.estimated_reading_time_minutes">
                        <h3 class="text-foreground mb-1 text-sm font-semibold">{{ t('agents.sources.files.summary_reading_time') }}</h3>
                        <p class="text-muted-foreground text-sm">
                            {{ t('agents.sources.files.minutes_count', summary.estimated_reading_time_minutes) }}
                        </p>
                    </div>
                </div>

                <div v-if="summary.formatting_elements && summary.formatting_elements.length > 0">
                    <h3 class="text-foreground mb-2 text-sm font-semibold">{{ t('agents.sources.files.summary_formatting') }}</h3>
                    <div class="flex flex-wrap gap-2">
                        <Badge v-for="element in summary.formatting_elements" :key="element" variant="outline" class="text-xs">
                            {{ element.replace(/_/g, ' ') }}
                        </Badge>
                    </div>
                </div>

                <div v-if="summary.contains_sensitive_info" class="rounded-lg border border-yellow-200 bg-yellow-50 p-4">
                    <div class="flex items-center gap-2">
                        <AlertCircle class="h-4 w-4 text-yellow-600" />
                        <h3 class="text-sm font-semibold text-yellow-800">{{ t('agents.sources.files.summary_sensitive_title') }}</h3>
                    </div>
                    <p class="mt-1 text-sm text-yellow-700">{{ t('agents.sources.files.summary_sensitive_description') }}</p>
                </div>
            </div>

            <div v-else class="text-muted-foreground py-8 text-center">
                <Info class="mx-auto mb-2 h-8 w-8 opacity-50" />
                <p>{{ t('agents.sources.files.summary_empty') }}</p>
            </div>
        </DialogContent>
    </Dialog>
</template>
