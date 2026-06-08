<script setup lang="ts">
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import { useDateFormat } from '@/composables/useDateFormat';
import { getInitials } from '@/composables/useInitials';
import { useTeamPermissions } from '@/composables/useTeamPermissions';
import AgentLayout from '@/layouts/AgentLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Braces, Check, FileText, LoaderCircle, MessageSquare, Pencil, Play, Plus, Regex, Scale, Search, Tag, Trash2, X } from 'lucide-vue-next';
import { computed, onUnmounted, ref, watch } from 'vue';
import { toast } from 'vue-sonner';

interface EvalCase {
    id: number;
    external_id: string;
    name: string;
    input: string;
    scorer_type: string;
    scorer_config: Record<string, any> | null;
    expected_output: string | null;
    active: boolean;
    created_at: string;
}

interface EvalRun {
    id: number;
    external_id: string;
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
    cases: EvalCase[];
    runs: EvalRun[];
    scorerTypes: string[];
}

const props = defineProps<Props>();

const { hasPermission } = useTeamPermissions();
const canManage = computed(() => hasPermission('agent.evals.manage'));

const { formatDateTime } = useDateFormat();

const SCORER_META: Record<string, { label: string; help: string }> = {
    contains: { label: 'Contains text', help: 'Passes if the answer contains a substring.' },
    regex: { label: 'Regex match', help: 'Passes if the answer matches a regular expression.' },
    exact: { label: 'Exact match', help: 'Passes only if the answer equals the reference answer exactly.' },
    json_schema: { label: 'JSON shape', help: 'Passes if the answer is valid JSON with the required keys.' },
    llm_judge: { label: 'AI judge', help: 'An open-weight model grades the answer against your criteria.' },
};

const scorerLabel = (type: string) => SCORER_META[type]?.label ?? type;

// Server validation keys for scorer config are dotted (e.g. "scorer_config.value")
// and not part of useForm's flat error type — read them through a cast.
const configError = (key: string): string | undefined => (form.errors as Record<string, string | undefined>)[`scorer_config.${key}`];

const activeCases = computed(() => props.cases.filter((c) => c.active).length);
const hasRunning = computed(() => props.runs.some((r) => r.status === 'running'));
const canRun = computed(() => canManage.value && activeCases.value > 0 && !hasRunning.value);

const runStatusVariant = (status: string): 'default' | 'secondary' | 'destructive' | 'outline' => {
    if (status === 'completed') return 'default';
    if (status === 'failed') return 'destructive';
    return 'secondary';
};

// ---- Case create / edit ----------------------------------------------------

const isCaseDialogOpen = ref(false);
// Holds the case being edited by its external_id (the route key); null = create.
const editingId = ref<string | null>(null);

const form = useForm({
    name: '',
    input: '',
    scorer_type: 'contains',
    value: '',
    case_sensitive: false,
    pattern: '',
    criteria: '',
    required: '',
    expected_output: '',
    active: true,
});

form.transform((data) => {
    const config: Record<string, any> = {};
    if (data.scorer_type === 'contains') {
        config.value = data.value;
        config.case_sensitive = data.case_sensitive;
    } else if (data.scorer_type === 'regex') {
        config.pattern = data.pattern;
    } else if (data.scorer_type === 'llm_judge') {
        config.criteria = data.criteria;
    } else if (data.scorer_type === 'json_schema') {
        config.required = data.required
            .split(',')
            .map((k) => k.trim())
            .filter(Boolean);
    }

    return {
        name: data.name,
        input: data.input,
        scorer_type: data.scorer_type,
        scorer_config: config,
        expected_output: data.expected_output,
        active: data.active,
    };
});

const openCreate = () => {
    editingId.value = null;
    form.reset();
    form.clearErrors();
    isCaseDialogOpen.value = true;
};

const openEdit = (item: EvalCase) => {
    editingId.value = item.external_id;
    form.clearErrors();
    form.name = item.name;
    form.input = item.input;
    form.scorer_type = item.scorer_type;
    form.value = item.scorer_config?.value ?? '';
    form.case_sensitive = Boolean(item.scorer_config?.case_sensitive);
    form.pattern = item.scorer_config?.pattern ?? '';
    form.criteria = item.scorer_config?.criteria ?? '';
    form.required = Array.isArray(item.scorer_config?.required) ? item.scorer_config!.required.join(', ') : '';
    form.expected_output = item.expected_output ?? '';
    form.active = item.active;
    isCaseDialogOpen.value = true;
};

const submitCase = () => {
    const opts = {
        preserveScroll: true,
        onSuccess: () => {
            isCaseDialogOpen.value = false;
            toast.success(editingId.value ? 'Case updated' : 'Case added');
        },
        onError: (errors: Record<string, string>) => {
            const first = Object.values(errors)[0];
            if (first) toast.error(first);
        },
    };

    if (editingId.value) {
        form.patch(route('agents.evals.cases.update', [props.agent.external_id, editingId.value]), opts);
    } else {
        form.post(route('agents.evals.cases.store', props.agent.external_id), opts);
    }
};

// ---- Case delete -----------------------------------------------------------

const deletingCase = ref<EvalCase | null>(null);
const isDeleteOpen = ref(false);

const confirmDelete = (item: EvalCase) => {
    deletingCase.value = item;
    isDeleteOpen.value = true;
};

const deleteCase = () => {
    if (!deletingCase.value) return;
    router.delete(route('agents.evals.cases.destroy', [props.agent.external_id, deletingCase.value.external_id]), {
        preserveScroll: true,
        onSuccess: () => {
            toast.success('Case deleted');
            isDeleteOpen.value = false;
            deletingCase.value = null;
        },
    });
};

// ---- Run suite -------------------------------------------------------------

const runProcessing = ref(false);

const triggerRun = () => {
    runProcessing.value = true;
    router.post(
        route('agents.evals.runs.store', props.agent.external_id),
        {},
        {
            preserveScroll: true,
            onSuccess: () => toast.success('Eval run started'),
            onError: () => toast.error('Could not start the run'),
            onFinish: () => {
                runProcessing.value = false;
            },
        },
    );
};

const openRun = (externalId: string) => router.visit(route('agents.evals.runs.show', [props.agent.external_id, externalId]));

// ---- Run delete / clear ----------------------------------------------------

const deletingRun = ref<EvalRun | null>(null);
const isDeleteRunOpen = ref(false);

const confirmDeleteRun = (run: EvalRun) => {
    deletingRun.value = run;
    isDeleteRunOpen.value = true;
};

const deleteRun = () => {
    if (!deletingRun.value) return;
    router.delete(route('agents.evals.runs.destroy', [props.agent.external_id, deletingRun.value.external_id]), {
        preserveScroll: true,
        onSuccess: () => {
            toast.success('Run deleted');
            isDeleteRunOpen.value = false;
            deletingRun.value = null;
        },
    });
};

const isClearOpen = ref(false);

const clearRuns = () => {
    router.delete(route('agents.evals.runs.clear', props.agent.external_id), {
        preserveScroll: true,
        onSuccess: () => {
            toast.success('Run history cleared');
            isClearOpen.value = false;
        },
    });
};

// While a run is in flight, poll the runs list so status + scores update live.
let pollTimer: number | undefined;
watch(
    hasRunning,
    (running) => {
        if (running && !pollTimer) {
            pollTimer = window.setInterval(() => router.reload({ only: ['runs'] }), 4000);
        } else if (!running && pollTimer) {
            clearInterval(pollTimer);
            pollTimer = undefined;
        }
    },
    { immediate: true },
);
onUnmounted(() => {
    if (pollTimer) clearInterval(pollTimer);
});
</script>

<template>
    <AgentLayout
        :agent="props.agent"
        title="Evals"
        description="Test suites that grade this agent’s real answers — same knowledge, tools, and model as production. Run the suite and track whether changes make the agent better or worse."
    >
        <template #header-actions>
            <Button v-if="canManage" :disabled="!canRun || runProcessing" @click="triggerRun">
                <LoaderCircle v-if="runProcessing || hasRunning" class="mr-2 h-4 w-4 animate-spin" />
                <Play v-else class="mr-2 h-4 w-4" />
                Run suite
            </Button>
        </template>

        <Head :title="`Evals — ${props.agent.name}`" />

        <div class="flex justify-center">
            <div class="min-h-[calc(100vh-220px)] w-full max-w-5xl space-y-8">
                <!-- Cases -->
                <section class="space-y-3">
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <h2 class="text-base font-semibold">Cases</h2>
                            <p class="text-muted-foreground text-sm">{{ props.cases.length }} total · {{ activeCases }} active</p>
                        </div>
                        <Button v-if="canManage" size="sm" variant="outline" @click="openCreate">
                            <Plus class="mr-2 h-4 w-4" />
                            Add case
                        </Button>
                    </div>

                    <div class="bg-card rounded-lg border">
                        <Table class="[&_td]:px-4 [&_td]:py-3 [&_th]:px-4">
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Case</TableHead>
                                    <TableHead>Scorer</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead class="w-0 text-right">Actions</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-for="item in props.cases" :key="item.id" class="hover:bg-muted/50">
                                    <TableCell class="max-w-md">
                                        <div class="font-medium">{{ item.name }}</div>
                                        <div class="text-muted-foreground line-clamp-1 text-sm">{{ item.input }}</div>
                                    </TableCell>
                                    <TableCell>
                                        <Badge variant="outline">{{ scorerLabel(item.scorer_type) }}</Badge>
                                    </TableCell>
                                    <TableCell>
                                        <Badge :variant="item.active ? 'secondary' : 'outline'" :class="item.active ? '' : 'text-muted-foreground'">
                                            {{ item.active ? 'Active' : 'Inactive' }}
                                        </Badge>
                                    </TableCell>
                                    <TableCell class="text-right">
                                        <div v-if="canManage" class="flex items-center justify-end gap-1">
                                            <Button variant="ghost" size="icon" @click="openEdit(item)">
                                                <Pencil class="h-4 w-4" />
                                            </Button>
                                            <Button variant="ghost" size="icon" @click="confirmDelete(item)">
                                                <Trash2 class="text-destructive h-4 w-4" />
                                            </Button>
                                        </div>
                                    </TableCell>
                                </TableRow>
                                <TableRow v-if="props.cases.length === 0">
                                    <TableCell colspan="4" class="text-muted-foreground py-10 text-center text-sm">
                                        No eval cases yet. Add one to start measuring answer quality.
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </div>
                </section>

                <!-- Recent runs -->
                <section class="space-y-3">
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <h2 class="text-base font-semibold">Recent runs</h2>
                            <p class="text-muted-foreground text-sm">Every run is scored against the cases active at that moment.</p>
                        </div>
                        <Button v-if="canManage && props.runs.length > 0" size="sm" variant="outline" @click="isClearOpen = true">
                            <Trash2 class="mr-2 h-4 w-4" />
                            Clear
                        </Button>
                    </div>

                    <div class="bg-card rounded-lg border">
                        <Table class="[&_td]:px-4 [&_td]:py-3 [&_th]:px-4">
                            <TableHeader>
                                <TableRow>
                                    <TableHead>By</TableHead>
                                    <TableHead>Pass rate</TableHead>
                                    <TableHead>Passed / Failed</TableHead>
                                    <TableHead>Avg score</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead class="text-right">When</TableHead>
                                    <TableHead v-if="canManage" class="w-0"></TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-for="run in props.runs" :key="run.id" class="hover:bg-muted/50 cursor-pointer" @click="openRun(run.external_id)">
                                    <TableCell>
                                        <div v-if="run.triggered_by_name" class="flex items-center gap-2">
                                            <Avatar class="h-7 w-7">
                                                <AvatarFallback class="text-xs">{{ getInitials(run.triggered_by_name) }}</AvatarFallback>
                                            </Avatar>
                                            <span>{{ run.triggered_by_name }}</span>
                                        </div>
                                        <span v-else class="text-muted-foreground">—</span>
                                    </TableCell>
                                    <TableCell>
                                        <span v-if="run.pass_rate !== null" class="font-medium">{{ run.pass_rate }}%</span>
                                        <span v-else class="text-muted-foreground">—</span>
                                    </TableCell>
                                    <TableCell>
                                        <span class="text-emerald-600 dark:text-emerald-400">{{ run.passed_count }}</span>
                                        <span class="text-muted-foreground"> / </span>
                                        <span class="text-destructive">{{ run.failed_count }}</span>
                                    </TableCell>
                                    <TableCell>{{ run.avg_score !== null ? Math.round(run.avg_score * 100) + '%' : '—' }}</TableCell>
                                    <TableCell>
                                        <Badge :variant="runStatusVariant(run.status)" class="capitalize">{{ run.status }}</Badge>
                                    </TableCell>
                                    <TableCell class="text-muted-foreground text-right whitespace-nowrap">{{
                                        formatDateTime(run.created_at)
                                    }}</TableCell>
                                    <TableCell v-if="canManage" class="text-right">
                                        <Button variant="ghost" size="icon" @click.stop="confirmDeleteRun(run)">
                                            <Trash2 class="text-destructive h-4 w-4" />
                                        </Button>
                                    </TableCell>
                                </TableRow>
                                <TableRow v-if="props.runs.length === 0">
                                    <TableCell :colspan="canManage ? 7 : 6" class="text-muted-foreground py-10 text-center text-sm">
                                        No runs yet. Click “Run suite” to score the active cases.
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </div>
                </section>
            </div>
        </div>

        <!-- Create / edit case dialog -->
        <Dialog v-model:open="isCaseDialogOpen">
            <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>{{ editingId ? 'Edit case' : 'Add case' }}</DialogTitle>
                    <DialogDescription>The input is sent to the agent exactly as a visitor would ask it.</DialogDescription>
                </DialogHeader>

                <div class="space-y-4">
                    <div class="space-y-1.5">
                        <Label for="case-name">Name</Label>
                        <div class="relative">
                            <Tag class="text-muted-foreground pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2" />
                            <Input id="case-name" v-model="form.name" placeholder="e.g. Refund policy is accurate" class="pl-9" />
                        </div>
                        <p v-if="form.errors.name" class="text-destructive text-xs">{{ form.errors.name }}</p>
                    </div>

                    <div class="space-y-1.5">
                        <Label for="case-input">Input prompt</Label>
                        <div class="relative">
                            <MessageSquare class="text-muted-foreground pointer-events-none absolute top-3 left-3 h-4 w-4" />
                            <Textarea id="case-input" v-model="form.input" rows="3" placeholder="What is your refund policy?" class="pl-9" />
                        </div>
                        <p v-if="form.errors.input" class="text-destructive text-xs">{{ form.errors.input }}</p>
                    </div>

                    <div class="space-y-1.5">
                        <Label>Scorer</Label>
                        <Select v-model="form.scorer_type">
                            <SelectTrigger>
                                <SelectValue placeholder="Pick a scorer" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="type in props.scorerTypes" :key="type" :value="type">
                                    {{ scorerLabel(type) }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <p class="text-muted-foreground text-xs">{{ SCORER_META[form.scorer_type]?.help }}</p>
                    </div>

                    <!-- Scorer-specific config -->
                    <template v-if="form.scorer_type === 'contains'">
                        <div class="space-y-1.5">
                            <Label for="cfg-value">Expected substring</Label>
                            <div class="relative">
                                <Search class="text-muted-foreground pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2" />
                                <Input id="cfg-value" v-model="form.value" placeholder="e.g. 30 days" class="pl-9" />
                            </div>
                            <p v-if="configError('value')" class="text-destructive text-xs">{{ configError('value') }}</p>
                        </div>
                        <div class="flex items-center gap-3">
                            <Switch id="cfg-cs" v-model="form.case_sensitive" />
                            <Label for="cfg-cs" class="cursor-pointer font-normal">Case-sensitive</Label>
                        </div>
                    </template>

                    <div v-else-if="form.scorer_type === 'regex'" class="space-y-1.5">
                        <Label for="cfg-pattern">Pattern</Label>
                        <div class="relative">
                            <Regex class="text-muted-foreground pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2" />
                            <Input id="cfg-pattern" v-model="form.pattern" placeholder="/\\b30 days\\b/i" class="pl-9 font-mono" />
                        </div>
                        <p v-if="configError('pattern')" class="text-destructive text-xs">{{ configError('pattern') }}</p>
                    </div>

                    <div v-else-if="form.scorer_type === 'json_schema'" class="space-y-1.5">
                        <Label for="cfg-required">Required keys</Label>
                        <div class="relative">
                            <Braces class="text-muted-foreground pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2" />
                            <Input id="cfg-required" v-model="form.required" placeholder="comma,separated,keys" class="pl-9" />
                        </div>
                        <p class="text-muted-foreground text-xs">The answer must be valid JSON containing each key.</p>
                    </div>

                    <div v-else-if="form.scorer_type === 'llm_judge'" class="space-y-1.5">
                        <Label for="cfg-criteria">Grading criteria</Label>
                        <div class="relative">
                            <Scale class="text-muted-foreground pointer-events-none absolute top-3 left-3 h-4 w-4" />
                            <Textarea
                                id="cfg-criteria"
                                v-model="form.criteria"
                                rows="3"
                                placeholder="The answer must state that refunds are available within 30 days of purchase."
                                class="pl-9"
                            />
                        </div>
                        <p v-if="configError('criteria')" class="text-destructive text-xs">
                            {{ configError('criteria') }}
                        </p>
                    </div>

                    <div v-if="['exact', 'llm_judge'].includes(form.scorer_type)" class="space-y-1.5">
                        <Label for="cfg-expected">
                            {{ form.scorer_type === 'exact' ? 'Expected answer' : 'Reference answer (optional)' }}
                        </Label>
                        <div class="relative">
                            <FileText class="text-muted-foreground pointer-events-none absolute top-3 left-3 h-4 w-4" />
                            <Textarea id="cfg-expected" v-model="form.expected_output" rows="2" class="pl-9" />
                        </div>
                        <p v-if="form.errors.expected_output" class="text-destructive text-xs">{{ form.errors.expected_output }}</p>
                    </div>

                    <div class="flex items-center gap-3">
                        <Switch id="cfg-active" v-model="form.active" />
                        <Label for="cfg-active" class="cursor-pointer font-normal">Active (included in runs)</Label>
                    </div>
                </div>

                <DialogFooter>
                    <Button variant="outline" :disabled="form.processing" @click="isCaseDialogOpen = false">
                        <X class="mr-2 h-4 w-4" />
                        Cancel
                    </Button>
                    <Button :disabled="form.processing" @click="submitCase">
                        <LoaderCircle v-if="form.processing" class="mr-2 h-4 w-4 animate-spin" />
                        <Check v-else-if="editingId" class="mr-2 h-4 w-4" />
                        <Plus v-else class="mr-2 h-4 w-4" />
                        {{ editingId ? 'Save' : 'Add case' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Delete confirmation -->
        <Dialog v-model:open="isDeleteOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Delete case</DialogTitle>
                    <DialogDescription>
                        Delete “{{ deletingCase?.name }}”? Past run results keep their copy of this case — only future runs are affected.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button variant="outline" @click="isDeleteOpen = false">Cancel</Button>
                    <Button variant="destructive" @click="deleteCase">Delete</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Delete single run -->
        <Dialog v-model:open="isDeleteRunOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Delete run</DialogTitle>
                    <DialogDescription>Delete this run and its per-case results? This can’t be undone. Cases aren’t affected.</DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button variant="outline" @click="isDeleteRunOpen = false">Cancel</Button>
                    <Button variant="destructive" @click="deleteRun">Delete</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Clear all runs -->
        <Dialog v-model:open="isClearOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Clear run history</DialogTitle>
                    <DialogDescription>
                        Delete all {{ props.runs.length }} run{{ props.runs.length === 1 ? '' : 's' }} for this agent and their results? This can’t be
                        undone. Cases aren’t affected.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button variant="outline" @click="isClearOpen = false">Cancel</Button>
                    <Button variant="destructive" @click="clearRuns">Clear all</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AgentLayout>
</template>
