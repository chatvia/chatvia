<script setup lang="ts">
/**
 * One text in the agent's text list: its excerpt, training status, creation
 * date, a progress bar while it trains, and the edit / remove buttons. A text
 * other agents also use reads "Remove from agent" instead of "Delete".
 */
import type { TextSource, TextSourceStatus } from '@/api/textSources';
import SourceActionButton from '@/components/agents/sources/SourceActionButton.vue';
import IngestionProgressBar from '@/components/common/IngestionProgressBar.vue';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { useDateFormat } from '@/composables/useDateFormat';
import { AlertCircle, CheckCircle, Edit3, LoaderCircle, Trash2 } from 'lucide-vue-next';
import { computed, type Component } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps<{
    entry: TextSource;
}>();

const emit = defineEmits<{
    edit: [];
    remove: [];
}>();

const { t } = useI18n();

// Dates follow the user's chosen format (Settings → Date & time), with a 24h time.
const { formatDateTime } = useDateFormat();

const isTraining = computed(() => props.entry.status === 'pending' || props.entry.status === 'ingesting');
const isShared = computed(() => props.entry.agents_count > 1);

const removeLabel = computed(() =>
    isShared.value ? t('agents.sources.common.remove_from_agent') : t('agents.sources.common.delete_from_knowledge_base'),
);

function statusIcon(status: TextSourceStatus): Component {
    switch (status) {
        case 'ingested':
            return CheckCircle;
        case 'failed':
            return AlertCircle;
        default:
            return LoaderCircle;
    }
}

function statusColor(status: TextSourceStatus): string {
    switch (status) {
        case 'ingested':
            return 'text-green-600';
        case 'pending':
        case 'ingesting':
            return 'text-blue-600';
        case 'failed':
            return 'text-red-600';
        default:
            return 'text-gray-600';
    }
}

function statusLabel(status: TextSourceStatus): string {
    switch (status) {
        case 'ingested':
            return t('agents.sources.text.status.trained');
        case 'pending':
            return t('agents.sources.text.status.pending');
        case 'ingesting':
            return t('agents.sources.text.status.training');
        case 'failed':
            return t('agents.sources.text.status.failed');
        default:
            return t('agents.sources.text.status.unknown');
    }
}
</script>

<template>
    <div class="hover:bg-muted/50 rounded-lg border p-4 transition-colors">
        <div class="flex items-start justify-between">
            <div class="min-w-0 flex-1">
                <div class="mb-2 flex items-start gap-2">
                    <p class="text-muted-foreground line-clamp-2 flex-1 text-sm">
                        {{ entry.excerpt }}
                    </p>
                    <TooltipProvider>
                        <Tooltip>
                            <TooltipTrigger as-child>
                                <div class="flex items-center">
                                    <component
                                        :is="statusIcon(entry.status)"
                                        :class="['h-4 w-4 flex-shrink-0', statusColor(entry.status), { 'animate-spin': isTraining }]"
                                    />
                                </div>
                            </TooltipTrigger>
                            <TooltipContent>
                                <p>{{ statusLabel(entry.status) }}</p>
                            </TooltipContent>
                        </Tooltip>
                    </TooltipProvider>
                </div>
                <div class="text-muted-foreground flex items-center gap-4 text-xs">
                    <span>{{ formatDateTime(entry.created_at) }}</span>
                </div>
                <IngestionProgressBar v-if="entry.status === 'ingesting'" :progress="entry.progress ?? 0" />
            </div>
            <div class="ms-4 flex items-center gap-2">
                <SourceActionButton
                    v-if="!isTraining"
                    :label="t('agents.sources.text.edit_tooltip')"
                    :icon="Edit3"
                    variant="outline"
                    class="h-8 w-8 p-0"
                    @click="emit('edit')"
                />
                <SourceActionButton
                    :label="removeLabel"
                    :icon="Trash2"
                    variant="outline"
                    class="h-8 w-8 p-0 text-red-600 hover:bg-red-50 hover:text-red-700"
                    @click="emit('remove')"
                >
                    <template #tooltip>
                        <div class="text-xs">
                            <p v-if="isShared">
                                {{ t('agents.sources.common.remove_from_agent') }}<br />
                                <span class="text-muted-foreground">{{
                                    t('agents.sources.common.used_in_agents', { count: entry.agents_count })
                                }}</span>
                            </p>
                            <p v-else>{{ t('agents.sources.common.delete_from_knowledge_base') }}</p>
                        </div>
                    </template>
                </SourceActionButton>
            </div>
        </div>
    </div>
</template>
