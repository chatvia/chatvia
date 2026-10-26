<script setup lang="ts">
// One eval run: summary cards (pass rate, pass/fail counts, average score,
// tokens) and the per-case results. While the run is still scoring, Reverb
// events update the cards and append result rows live.
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useDateFormat } from '@/composables/useDateFormat';
import AgentLayout from '@/layouts/AgentLayout.vue';
import { evals } from '@/routes/agents';
import { Head, Link } from '@inertiajs/vue3';
import { useEcho } from '@laravel/echo-vue';
import { ArrowLeft, CheckCircle2, Gauge, Hash, LoaderCircle, Target, XCircle } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import EvalResultDialog from './EvalResultDialog.vue';
import { runStatusVariant, type EvalAgent, type EvalCaseScoredEvent, type EvalResult, type EvalRun, type EvalRunProgressedEvent } from './types';
import { useScorers } from './useScorers';

const { t } = useI18n();

interface Props {
    agent: EvalAgent;
    run: EvalRun;
    results: EvalResult[];
}

const props = defineProps<Props>();
const { formatDateTime } = useDateFormat();
const { scorerIcon, scorerShortLabel } = useScorers();

// Local copies so Reverb events can update the header badge, summary cards, and
// result rows live; Inertia reloads reset them back to the server's truth.
const results = ref<EvalResult[]>([...props.results]);
const run = ref<EvalRun>({ ...props.run });

const applyTally = (passed: number, failed: number) => {
    run.value.passed_count = passed;
    run.value.failed_count = failed;
    const scored = passed + failed;
    run.value.pass_rate = scored > 0 ? Math.round((passed / scored) * 100) : null;
};

// A still-running run hasn't had its counts persisted yet (that happens on
// finalize), so seed the live tally from the results already scored.
if (run.value.status === 'running') {
    applyTally(results.value.filter((r) => r.pass).length, results.value.filter((r) => !r.pass).length);
}

watch(
    () => props.run,
    (next) => {
        run.value = { ...next };
    },
);
watch(
    () => props.results,
    (next) => {
        results.value = [...next];
    },
);

// A scored case appends/updates its result row and advances the summary tally;
// a run-progress event refreshes the header badge + the finalized scores. Both
// listeners share one ref-counted channel and clean up on unmount.
useEcho(`agents.${props.agent.external_id}.evals`, '.EvalCaseScored', (e: EvalCaseScoredEvent) => {
    if (e.run_external_id !== run.value.external_id) return;

    const idx = results.value.findIndex((r) => r.id === e.result.id);
    if (idx === -1) {
        results.value = [...results.value, e.result];
    } else {
        results.value[idx] = e.result;
    }

    applyTally(e.passed_count, e.failed_count);
});

useEcho(`agents.${props.agent.external_id}.evals`, '.EvalRunProgressed', (e: EvalRunProgressedEvent) => {
    if (e.run.external_id !== run.value.external_id) return;
    run.value = e.run;
});

const statusLabel = (status: string) => t(`agents.evals.statuses.${status}`);

// Summary metrics as cards: header (label + icon), big value with an optional
// "of N" secondary, a progress bar, and a note. Pass/fail show a share-of-total
// bar; percentages fill the bar directly; Tokens is note-only (uncapped).
const stats = computed(() => {
    const data = run.value;
    const total = data.passed_count + data.failed_count;

    return [
        {
            key: 'pass_rate',
            label: t('agents.evals.run.stats.pass_rate'),
            icon: Target,
            value: data.pass_rate !== null ? `${data.pass_rate}%` : '—',
            secondary: null as string | null,
            bar: data.pass_rate,
            barClass: (data.pass_rate ?? 0) >= 50 ? 'bg-emerald-500' : 'bg-destructive',
            note:
                total > 0
                    ? t('agents.evals.run.stats.cases_passed', { passed: data.passed_count, total })
                    : t('agents.evals.run.stats.no_cases_scored'),
        },
        {
            key: 'passed',
            label: t('agents.evals.run.stats.passed'),
            icon: CheckCircle2,
            value: data.passed_count.toLocaleString(),
            secondary: total > 0 ? t('agents.evals.run.stats.of_total', { total }) : null,
            bar: total > 0 ? Math.round((data.passed_count / total) * 100) : null,
            barClass: 'bg-emerald-500',
            note: t('agents.evals.run.stats.n_failed', { count: data.failed_count }),
        },
        {
            key: 'failed',
            label: t('agents.evals.run.stats.failed'),
            icon: XCircle,
            value: data.failed_count.toLocaleString(),
            secondary: total > 0 ? t('agents.evals.run.stats.of_total', { total }) : null,
            bar: total > 0 ? Math.round((data.failed_count / total) * 100) : null,
            barClass: 'bg-destructive',
            note: t('agents.evals.run.stats.n_passed', { count: data.passed_count }),
        },
        {
            key: 'avg',
            label: t('agents.evals.run.stats.avg_score'),
            icon: Gauge,
            value: data.avg_score !== null ? `${Math.round(data.avg_score * 100)}%` : '—',
            secondary: null as string | null,
            bar: data.avg_score !== null ? Math.round(data.avg_score * 100) : null,
            barClass: 'bg-primary',
            note: t('agents.evals.run.stats.mean_score'),
        },
        {
            key: 'tokens',
            label: t('agents.evals.run.stats.tokens'),
            icon: Hash,
            value: data.total_tokens !== null ? data.total_tokens.toLocaleString() : '—',
            secondary: null as string | null,
            bar: null,
            barClass: '',
            note: t('agents.evals.run.stats.tokens_note'),
        },
    ];
});

const selected = ref<EvalResult | null>(null);
const isDetailOpen = ref(false);

const openDetail = (result: EvalResult) => {
    selected.value = result;
    isDetailOpen.value = true;
};
</script>

<template>
    <AgentLayout :agent="props.agent" :title="t('agents.evals.run.title')" :description="t('agents.evals.run.description')">
        <Head :title="t('agents.evals.run.head_title', { id: run.id, name: props.agent.name })" />

        <div class="flex justify-center">
            <div class="min-h-[calc(100vh-220px)] w-full max-w-5xl space-y-6">
                <div class="flex items-center gap-3">
                    <Button variant="ghost" size="icon" as-child :aria-label="t('agents.evals.run.back')">
                        <Link :href="evals.url(props.agent.external_id)">
                            <ArrowLeft class="h-4 w-4" />
                        </Link>
                    </Button>
                    <div>
                        <h2 class="flex items-center gap-2 text-lg font-semibold">
                            {{ t('agents.evals.run.run_number', { id: run.id }) }}
                            <Badge :variant="runStatusVariant(run.status)" class="gap-1.5 capitalize">
                                <LoaderCircle v-if="run.status === 'running'" class="h-3 w-3 animate-spin" />
                                <CheckCircle2 v-else-if="run.status === 'completed'" class="h-3 w-3" />
                                <XCircle v-else class="h-3 w-3" />
                                {{ statusLabel(run.status) }}
                            </Badge>
                        </h2>
                        <p class="text-muted-foreground text-sm">
                            {{ t('agents.evals.run.triggered_by', { name: run.triggered_by_name ?? '—' }) }} ·
                            {{ formatDateTime(run.created_at) }}
                        </p>
                    </div>
                </div>

                <!-- Summary stats -->
                <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5">
                    <Card v-for="stat in stats" :key="stat.key">
                        <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle class="text-sm font-medium">{{ stat.label }}</CardTitle>
                            <component :is="stat.icon" class="text-muted-foreground h-4 w-4 shrink-0" />
                        </CardHeader>
                        <CardContent class="space-y-2.5">
                            <div class="flex items-baseline justify-between gap-2">
                                <span class="text-2xl font-bold tracking-tight tabular-nums">{{ stat.value }}</span>
                                <span v-if="stat.secondary" class="text-muted-foreground text-xs whitespace-nowrap">{{ stat.secondary }}</span>
                            </div>
                            <template v-if="stat.bar !== null">
                                <div class="bg-muted h-1.5 w-full overflow-hidden rounded-full">
                                    <div
                                        class="h-full rounded-full transition-all duration-500"
                                        :class="stat.barClass"
                                        :style="{ width: `${stat.bar}%` }"
                                    />
                                </div>
                                <p class="text-muted-foreground text-xs">{{ stat.note }}</p>
                            </template>
                            <p v-else class="text-muted-foreground text-xs leading-relaxed">{{ stat.note }}</p>
                        </CardContent>
                    </Card>
                </div>

                <!-- Per-case results -->
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>{{ t('agents.evals.run.table.case') }}</TableHead>
                            <TableHead>{{ t('agents.evals.run.table.result') }}</TableHead>
                            <TableHead>{{ t('agents.evals.run.table.score') }}</TableHead>
                            <TableHead>{{ t('agents.evals.run.table.scorer') }}</TableHead>
                            <TableHead class="text-end">{{ t('agents.evals.run.table.latency') }}</TableHead>
                            <TableHead class="text-end">{{ t('agents.evals.run.table.tokens') }}</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="result in results" :key="result.id" class="hover:bg-muted/50 cursor-pointer" @click="openDetail(result)">
                            <TableCell class="max-w-md">
                                <div class="truncate font-medium">{{ result.case_name }}</div>
                            </TableCell>
                            <TableCell>
                                <span v-if="result.pass" class="flex items-center gap-1 text-emerald-600 dark:text-emerald-400">
                                    <CheckCircle2 class="h-4 w-4" /> {{ t('agents.evals.run.pass') }}
                                </span>
                                <span v-else class="text-destructive flex items-center gap-1">
                                    <XCircle class="h-4 w-4" /> {{ t('agents.evals.run.fail') }}
                                </span>
                            </TableCell>
                            <TableCell class="tabular-nums">{{ result.score !== null ? Math.round(result.score * 100) + '%' : '—' }}</TableCell>
                            <TableCell>
                                <Badge variant="outline" class="gap-1.5">
                                    <component :is="scorerIcon(result.scorer_type)" class="h-3 w-3" />
                                    {{ scorerShortLabel(result.scorer_type) }}
                                </Badge>
                            </TableCell>
                            <TableCell class="text-muted-foreground text-end tabular-nums">{{
                                result.latency_ms !== null ? t('agents.evals.run.latency_value', { ms: result.latency_ms.toLocaleString() }) : '—'
                            }}</TableCell>
                            <TableCell class="text-muted-foreground text-end tabular-nums">{{
                                result.tokens !== null ? result.tokens.toLocaleString() : '—'
                            }}</TableCell>
                        </TableRow>
                        <TableRow v-if="results.length === 0">
                            <TableCell colspan="6" class="text-muted-foreground py-10 text-center text-sm">
                                {{ run.status === 'running' ? t('agents.evals.run.scoring_cases') : t('agents.evals.run.no_results') }}
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>
        </div>

        <!-- Result detail dialog -->
        <EvalResultDialog v-model:open="isDetailOpen" :selected="selected" />
    </AgentLayout>
</template>
