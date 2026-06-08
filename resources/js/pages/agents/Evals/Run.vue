<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useDateFormat } from '@/composables/useDateFormat';
import AgentLayout from '@/layouts/AgentLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, CheckCircle2, Gauge, Hash, Target, XCircle } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface EvalResult {
    id: number;
    case_id: number | null;
    case_name: string;
    scorer_type: string;
    pass: boolean;
    score: number | null;
    actual_output: string | null;
    actual_output_truncated: boolean;
    sources_used: string[];
    tools_called: string[];
    judge_rationale: string | null;
    latency_ms: number | null;
    tokens: number | null;
    trace_id: string | null;
    created_at: string | null;
}

interface EvalRun {
    id: number;
    status: string;
    total_cases: number;
    passed_count: number;
    failed_count: number;
    avg_score: number | null;
    pass_rate: number | null;
    total_tokens: number | null;
    model_snapshot: string | null;
    triggered_by_name: string | null;
    started_at: string | null;
    finished_at: string | null;
    created_at: string;
}

interface Props {
    agent: { external_id: string; name: string };
    run: EvalRun;
    results: EvalResult[];
}

const props = defineProps<Props>();
const { formatDateTime } = useDateFormat();

const SCORER_LABELS: Record<string, string> = {
    contains: 'Contains text',
    regex: 'Regex match',
    exact: 'Exact match',
    json_schema: 'JSON shape',
    llm_judge: 'AI judge',
};
const scorerLabel = (type: string) => SCORER_LABELS[type] ?? type;

const runStatusVariant = (status: string): 'default' | 'secondary' | 'destructive' | 'outline' => {
    if (status === 'completed') return 'default';
    if (status === 'failed') return 'destructive';
    return 'secondary';
};

// Summary metrics in the Usage.vue card shape: header (label + icon), big value
// with an optional "of N" secondary, a progress bar, and a note. Pass/fail show
// a share-of-total bar; percentages fill the bar directly; Tokens is note-only
// (uncapped) like the billing Tokens card.
const stats = computed(() => {
    const run = props.run;
    const total = run.passed_count + run.failed_count;

    return [
        {
            key: 'pass_rate',
            label: 'Pass rate',
            icon: Target,
            value: run.pass_rate !== null ? `${run.pass_rate}%` : '—',
            secondary: null as string | null,
            bar: run.pass_rate,
            barClass: (run.pass_rate ?? 0) >= 50 ? 'bg-emerald-500' : 'bg-destructive',
            note: total > 0 ? `${run.passed_count} of ${total} cases passed` : 'No cases scored',
        },
        {
            key: 'passed',
            label: 'Passed',
            icon: CheckCircle2,
            value: run.passed_count.toLocaleString(),
            secondary: total > 0 ? `of ${total}` : null,
            bar: total > 0 ? Math.round((run.passed_count / total) * 100) : null,
            barClass: 'bg-emerald-500',
            note: `${run.failed_count} failed`,
        },
        {
            key: 'failed',
            label: 'Failed',
            icon: XCircle,
            value: run.failed_count.toLocaleString(),
            secondary: total > 0 ? `of ${total}` : null,
            bar: total > 0 ? Math.round((run.failed_count / total) * 100) : null,
            barClass: 'bg-destructive',
            note: `${run.passed_count} passed`,
        },
        {
            key: 'avg',
            label: 'Avg score',
            icon: Gauge,
            value: run.avg_score !== null ? `${Math.round(run.avg_score * 100)}%` : '—',
            secondary: null as string | null,
            bar: run.avg_score !== null ? Math.round(run.avg_score * 100) : null,
            barClass: 'bg-primary',
            note: 'Mean score across cases',
        },
        {
            key: 'tokens',
            label: 'Tokens',
            icon: Hash,
            value: run.total_tokens !== null ? run.total_tokens.toLocaleString() : '—',
            secondary: null as string | null,
            bar: null,
            barClass: '',
            note: 'Consumed by this run — for insight only.',
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
    <AgentLayout :agent="props.agent" title="Eval run">
        <Head :title="`Eval run #${props.run.id} — ${props.agent.name}`" />

        <div class="flex justify-center">
            <div class="min-h-[calc(100vh-220px)] w-full max-w-5xl space-y-6">
                <div class="flex items-center gap-3">
                    <Button variant="ghost" size="icon" as-child>
                        <Link :href="route('agents.evals', props.agent.external_id)">
                            <ArrowLeft class="h-4 w-4" />
                        </Link>
                    </Button>
                    <div>
                        <h2 class="flex items-center gap-2 text-lg font-semibold">
                            Run #{{ props.run.id }}
                            <Badge :variant="runStatusVariant(props.run.status)" class="capitalize">{{ props.run.status }}</Badge>
                        </h2>
                        <p class="text-muted-foreground text-sm">
                            by {{ props.run.triggered_by_name ?? '—' }} · {{ formatDateTime(props.run.created_at) }}
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
                <div class="bg-card rounded-lg border">
                    <Table class="[&_td]:px-4 [&_td]:py-3 [&_th]:px-4">
                        <TableHeader>
                            <TableRow>
                                <TableHead>Case</TableHead>
                                <TableHead>Result</TableHead>
                                <TableHead>Score</TableHead>
                                <TableHead>Scorer</TableHead>
                                <TableHead class="text-right">Latency</TableHead>
                                <TableHead class="text-right">Tokens</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow
                                v-for="result in props.results"
                                :key="result.id"
                                class="hover:bg-muted/50 cursor-pointer"
                                @click="openDetail(result)"
                            >
                                <TableCell class="font-medium">{{ result.case_name }}</TableCell>
                                <TableCell>
                                    <span v-if="result.pass" class="flex items-center gap-1 text-emerald-600 dark:text-emerald-400">
                                        <CheckCircle2 class="h-4 w-4" /> Pass
                                    </span>
                                    <span v-else class="text-destructive flex items-center gap-1"> <XCircle class="h-4 w-4" /> Fail </span>
                                </TableCell>
                                <TableCell>{{ result.score !== null ? Math.round(result.score * 100) + '%' : '—' }}</TableCell>
                                <TableCell>
                                    <Badge variant="outline">{{ scorerLabel(result.scorer_type) }}</Badge>
                                </TableCell>
                                <TableCell class="text-muted-foreground text-right">{{
                                    result.latency_ms !== null ? `${result.latency_ms} ms` : '—'
                                }}</TableCell>
                                <TableCell class="text-muted-foreground text-right">{{ result.tokens ?? '—' }}</TableCell>
                            </TableRow>
                            <TableRow v-if="props.results.length === 0">
                                <TableCell colspan="6" class="text-muted-foreground py-10 text-center text-sm">
                                    {{ props.run.status === 'running' ? 'Scoring cases…' : 'No results recorded.' }}
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </div>
            </div>
        </div>

        <!-- Result detail dialog -->
        <Dialog v-model:open="isDetailOpen">
            <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2">
                        {{ selected?.case_name }}
                        <Badge v-if="selected" :variant="selected.pass ? 'default' : 'destructive'">
                            {{ selected.pass ? 'Pass' : 'Fail' }}
                        </Badge>
                    </DialogTitle>
                    <DialogDescription>
                        {{ selected ? scorerLabel(selected.scorer_type) : '' }}
                        <template v-if="selected?.score !== null && selected?.score !== undefined"
                            >· score {{ Math.round(selected.score * 100) }}%</template
                        >
                    </DialogDescription>
                </DialogHeader>

                <div v-if="selected" class="space-y-4 text-sm">
                    <div v-if="selected.judge_rationale">
                        <p class="text-muted-foreground mb-1 text-xs font-medium uppercase">Rationale</p>
                        <p class="bg-muted/40 rounded-md p-3">{{ selected.judge_rationale }}</p>
                    </div>

                    <div>
                        <p class="text-muted-foreground mb-1 text-xs font-medium uppercase">Agent answer</p>
                        <p class="bg-muted/40 max-h-64 overflow-y-auto rounded-md p-3 break-words whitespace-pre-wrap">
                            {{ selected.actual_output || '(empty)' }}
                        </p>
                        <p v-if="selected.actual_output_truncated" class="text-muted-foreground mt-1 text-xs">Truncated for storage.</p>
                    </div>

                    <div v-if="selected.tools_called.length">
                        <p class="text-muted-foreground mb-1 text-xs font-medium uppercase">Tools used</p>
                        <div class="flex flex-wrap gap-1.5">
                            <Badge v-for="tool in selected.tools_called" :key="tool" variant="secondary">{{ tool }}</Badge>
                        </div>
                    </div>

                    <div v-if="selected.sources_used.length">
                        <p class="text-muted-foreground mb-1 text-xs font-medium uppercase">Sources retrieved</p>
                        <ul class="text-muted-foreground list-inside list-disc space-y-0.5">
                            <li v-for="(source, i) in selected.sources_used" :key="i">{{ source }}</li>
                        </ul>
                    </div>

                    <div class="text-muted-foreground flex gap-4 text-xs">
                        <span v-if="selected.latency_ms !== null">{{ selected.latency_ms }}ms</span>
                        <span v-if="selected.tokens !== null">{{ selected.tokens }} tokens</span>
                        <span v-if="selected.trace_id">trace {{ selected.trace_id }}</span>
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    </AgentLayout>
</template>
