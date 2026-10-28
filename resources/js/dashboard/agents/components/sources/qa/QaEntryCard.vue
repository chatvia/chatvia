<script setup lang="ts">
/**
 * One Q&A entry in the list: its title and questions, its status, an actions
 * menu (edit / delete), a short preview of the answer and when it was created.
 */
import type { QaEntry, QaEntryStatus } from '@/dashboard/agents/api/qaSources';
import { Button } from '@/shared/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/shared/ui/card';
import { DropdownMenu, DropdownMenuContent, DropdownMenuGroup, DropdownMenuItem, DropdownMenuTrigger } from '@/shared/ui/dropdown-menu';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/shared/ui/tooltip';
import { useDateFormat } from '@/dashboard/composables/useDateFormat';
import { AlertCircle, CheckCircle, ChevronDown, Edit3, LoaderCircle, Trash2 } from 'lucide-vue-next';
import { computed, type Component } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps<{
    entry: QaEntry;
}>();

const emit = defineEmits<{
    edit: [];
    delete: [];
}>();

const { t } = useI18n();

// Dates follow the user's chosen format (Settings → Date & time).
const { formatDate } = useDateFormat();

function statusIcon(status: QaEntryStatus): Component {
    switch (status) {
        case 'ingested':
            return CheckCircle;
        case 'failed':
            return AlertCircle;
        default:
            return LoaderCircle;
    }
}

function statusColor(status: QaEntryStatus): string {
    switch (status) {
        case 'ingested':
            return 'text-green-500';
        case 'ingesting':
            return 'text-blue-500';
        case 'failed':
            return 'text-red-500';
        default:
            return 'text-gray-500';
    }
}

const statusText = computed(() => {
    switch (props.entry.status) {
        case 'ingested':
            return t('agents.sources.qa.status.ingested');
        case 'ingesting':
            return t('agents.sources.qa.status.ingesting');
        case 'failed':
            return t('agents.sources.qa.status.failed');
        default:
            return t('agents.sources.qa.status.pending');
    }
});
</script>

<template>
    <Card>
        <CardHeader>
            <div class="flex items-start justify-between">
                <div class="flex-1">
                    <CardTitle class="text-lg">{{ entry.title }}</CardTitle>
                    <div class="mt-2 space-y-1">
                        <p v-for="(question, index) in entry.questions" :key="index" class="text-muted-foreground text-sm">
                            {{ t('agents.sources.qa.question_prefix', { number: index + 1 }) }} {{ question.question }}
                        </p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <TooltipProvider>
                        <Tooltip>
                            <TooltipTrigger :aria-label="statusText">
                                <component
                                    :is="statusIcon(entry.status)"
                                    :class="['h-5 w-5', statusColor(entry.status), { 'animate-spin': entry.status === 'ingesting' }]"
                                />
                            </TooltipTrigger>
                            <TooltipContent>
                                {{ statusText }}
                            </TooltipContent>
                        </Tooltip>
                    </TooltipProvider>

                    <DropdownMenu>
                        <DropdownMenuTrigger as-child>
                            <Button variant="ghost" size="sm" :aria-label="t('agents.sources.qa.actions')">
                                <ChevronDown class="h-4 w-4" />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                            <DropdownMenuGroup>
                                <DropdownMenuItem @click="emit('edit')">
                                    <Edit3 class="me-2 h-4 w-4" />
                                    {{ t('agents.sources.qa.edit_action') }}
                                </DropdownMenuItem>
                                <DropdownMenuItem variant="destructive" @click="emit('delete')">
                                    <Trash2 class="me-2 h-4 w-4" />
                                    {{ t('agents.sources.common.delete') }}
                                </DropdownMenuItem>
                            </DropdownMenuGroup>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>
            </div>
        </CardHeader>
        <CardContent>
            <div class="text-muted-foreground text-sm">
                <p class="line-clamp-3">{{ entry.answer }}</p>
            </div>

            <div class="text-muted-foreground mt-4 flex items-center gap-4 text-xs">
                <span>{{ t('agents.sources.qa.created_at', { date: formatDate(entry.created_at) }) }}</span>
            </div>
        </CardContent>
    </Card>
</template>
