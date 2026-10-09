<script setup lang="ts">
// The "Recent runs" section of the Evals page: one row per run with who
// started it, its live pass rate and status. Clicking a row opens the run;
// deleting and clearing are confirmed by the page through the emitted events.
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Progress } from '@/components/ui/progress';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useDateFormat } from '@/composables/useDateFormat';
import { getInitials } from '@/composables/useInitials';
import { CircleCheck, CircleX, LoaderCircle, Trash2 } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';
import { runStatusVariant, type EvalRun } from './types';

defineProps<{
    runs: EvalRun[];
    canManage: boolean;
}>();

const emit = defineEmits<{
    open: [externalId: string];
    delete: [run: EvalRun];
    clear: [];
}>();

const { t } = useI18n();
const { formatDateTime } = useDateFormat();
</script>

<template>
    <section class="space-y-3">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h2 class="text-base font-semibold">{{ t('agents.evals.index.recent_runs') }}</h2>
                <p class="text-muted-foreground text-sm">{{ t('agents.evals.index.recent_runs_description') }}</p>
            </div>
            <Button v-if="canManage && runs.length > 0" size="sm" variant="outline" @click="emit('clear')">
                <Trash2 class="me-2 h-4 w-4" />
                {{ t('agents.evals.index.clear') }}
            </Button>
        </div>

        <div class="bg-card rounded-lg border">
            <Table class="[&_td]:px-4 [&_td]:py-3 [&_th]:px-4">
                <TableHeader>
                    <TableRow>
                        <TableHead>{{ t('agents.evals.index.table.actor') }}</TableHead>
                        <TableHead>{{ t('agents.evals.index.table.pass_rate') }}</TableHead>
                        <TableHead>{{ t('agents.evals.index.table.passed_failed') }}</TableHead>
                        <TableHead>{{ t('agents.evals.index.table.avg_score') }}</TableHead>
                        <TableHead>{{ t('agents.evals.index.table.status') }}</TableHead>
                        <TableHead class="text-end">{{ t('agents.evals.index.table.when') }}</TableHead>
                        <TableHead v-if="canManage" class="w-0"></TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-for="run in runs" :key="run.id" class="hover:bg-muted/50 cursor-pointer" @click="emit('open', run.external_id)">
                        <TableCell>
                            <div v-if="run.triggered_by_name" class="flex items-center gap-2">
                                <Avatar class="h-7 w-7">
                                    <AvatarImage v-if="run.triggered_by_avatar" :src="run.triggered_by_avatar" :alt="run.triggered_by_name" />
                                    <AvatarFallback class="text-xs">{{ getInitials(run.triggered_by_name) }}</AvatarFallback>
                                </Avatar>
                                <span>{{ run.triggered_by_name }}</span>
                            </div>
                            <span v-else class="text-muted-foreground">—</span>
                        </TableCell>
                        <TableCell>
                            <div v-if="run.pass_rate !== null" class="flex items-center gap-2">
                                <Progress :model-value="run.pass_rate" class="h-1.5 w-16" />
                                <span class="font-medium tabular-nums">{{ run.pass_rate }}%</span>
                            </div>
                            <span v-else class="text-muted-foreground">—</span>
                        </TableCell>
                        <TableCell>
                            <div class="flex items-center gap-3 tabular-nums">
                                <span class="flex items-center gap-1 text-emerald-600 dark:text-emerald-400">
                                    <CircleCheck class="h-3.5 w-3.5" />
                                    {{ run.passed_count }}
                                </span>
                                <span class="flex items-center gap-1" :class="run.failed_count > 0 ? 'text-destructive' : 'text-muted-foreground'">
                                    <CircleX class="h-3.5 w-3.5" />
                                    {{ run.failed_count }}
                                </span>
                            </div>
                        </TableCell>
                        <TableCell class="tabular-nums">
                            {{ run.avg_score !== null ? Math.round(run.avg_score * 100) + '%' : '—' }}
                        </TableCell>
                        <TableCell>
                            <Badge :variant="runStatusVariant(run.status)" class="gap-1.5 capitalize">
                                <LoaderCircle v-if="run.status === 'running'" class="h-3 w-3 animate-spin" />
                                <CircleCheck v-else-if="run.status === 'completed'" class="h-3 w-3" />
                                <CircleX v-else class="h-3 w-3" />
                                {{ t(`agents.evals.statuses.${run.status}`) }}
                            </Badge>
                        </TableCell>
                        <TableCell class="text-muted-foreground text-end whitespace-nowrap">{{ formatDateTime(run.created_at) }}</TableCell>
                        <TableCell v-if="canManage" class="text-end">
                            <Button
                                variant="ghost"
                                size="icon"
                                :aria-label="t('agents.evals.index.delete_run_title')"
                                @click.stop="emit('delete', run)"
                            >
                                <Trash2 class="text-destructive h-4 w-4" />
                            </Button>
                        </TableCell>
                    </TableRow>
                    <TableRow v-if="runs.length === 0">
                        <TableCell :colspan="canManage ? 7 : 6" class="text-muted-foreground py-10 text-center text-sm">
                            {{ t('agents.evals.index.empty_runs') }}
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>
    </section>
</template>
