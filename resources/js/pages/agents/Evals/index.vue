<script setup lang="ts">
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Progress } from '@/components/ui/progress';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { useDateFormat } from '@/composables/useDateFormat';
import { getInitials } from '@/composables/useInitials';
import { useTeamPermissions } from '@/composables/useTeamPermissions';
import AgentLayout from '@/layouts/AgentLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    Braces,
    Check,
    CircleCheck,
    CircleX,
    Equal,
    FileText,
    LoaderCircle,
    MessageSquare,
    Pencil,
    Play,
    Plus,
    Regex,
    Scale,
    Search,
    Sparkles,
    Tag,
    Trash2,
    X,
} from 'lucide-vue-next';
import { computed, onUnmounted, ref, watch, type Component } from 'vue';
import { toast } from 'vue-sonner';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

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

interface KnowledgeQuestion {
    key: string;
    type: 'chunk' | 'qa';
    id: number;
    question: string;
    source: string;
    already_added: boolean;
}

interface Props {
    agent: { external_id: string; name: string };
    cases: EvalCase[];
    runs: EvalRun[];
    scorerTypes: string[];
    // Optional prop — absent until the "From knowledge" dialog triggers a partial reload.
    knowledgeQuestions?: KnowledgeQuestion[];
}

const props = defineProps<Props>();

const { hasPermission } = useTeamPermissions();
const canManage = computed(() => hasPermission('agent.evals.manage'));

const { formatDateTime } = useDateFormat();

const SCORER_ICONS: Record<string, Component> = {
    contains: Search,
    regex: Regex,
    exact: Equal,
    json_schema: Braces,
    llm_judge: Scale,
};

const scorerLabel = (type: string) => {
    const key = `agents.evals.index.scorers.${type}.label`;
    return t(key) !== key ? t(key) : type;
};

const scorerHelp = (type: string) => {
    const key = `agents.evals.index.scorers.${type}.help`;
    return t(key) !== key ? t(key) : '';
};

const scorerIcon = (type: string) => SCORER_ICONS[type];

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
            toast.success(editingId.value ? t('agents.evals.index.toasts.case_updated') : t('agents.evals.index.toasts.case_added'));
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

// ---- Bulk add from knowledge ------------------------------------------------

const isKnowledgeOpen = ref(false);
const knowledgeLoading = ref(false);
const knowledgeSearch = ref('');
const selectedKeys = ref<string[]>([]);
const knowledgeSubmitting = ref(false);

const openFromKnowledge = () => {
    selectedKeys.value = [];
    knowledgeSearch.value = '';
    isKnowledgeOpen.value = true;
    knowledgeLoading.value = true;
    router.reload({ only: ['knowledgeQuestions'], onFinish: () => (knowledgeLoading.value = false) });
};

const filteredQuestions = computed(() => {
    const all = props.knowledgeQuestions ?? [];
    const term = knowledgeSearch.value.trim().toLowerCase();
    if (!term) return all;
    return all.filter((q) => q.question.toLowerCase().includes(term) || q.source.toLowerCase().includes(term));
});

const isQuestionSelected = (q: KnowledgeQuestion) => selectedKeys.value.includes(q.key);

const toggleQuestion = (q: KnowledgeQuestion) => {
    if (q.already_added) return;
    selectedKeys.value = isQuestionSelected(q) ? selectedKeys.value.filter((k) => k !== q.key) : [...selectedKeys.value, q.key];
};

const submitKnowledge = () => {
    const items = (props.knowledgeQuestions ?? [])
        .filter((q) => selectedKeys.value.includes(q.key))
        .map((q) => ({ type: q.type, id: q.id, question: q.question }));
    if (!items.length) return;

    knowledgeSubmitting.value = true;
    router.post(
        route('agents.evals.cases.from-knowledge', props.agent.external_id),
        { items },
        {
            preserveScroll: true,
            onSuccess: () => {
                isKnowledgeOpen.value = false;
                toast.success(t('agents.evals.index.toasts.cases_added', { count: items.length }, items.length));
            },
            onError: (errors: Record<string, string>) => {
                const first = Object.values(errors)[0];
                if (first) toast.error(first);
            },
            onFinish: () => {
                knowledgeSubmitting.value = false;
            },
        },
    );
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
            toast.success(t('agents.evals.index.toasts.case_deleted'));
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
            onSuccess: () => toast.success(t('agents.evals.index.toasts.run_started')),
            onError: (errors: Record<string, string>) => {
                const first = Object.values(errors)[0];
                toast.error(first || t('agents.evals.index.toasts.run_start_error'));
            },
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
            toast.success(t('agents.evals.index.toasts.run_deleted'));
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
            toast.success(t('agents.evals.index.toasts.history_cleared'));
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

const knowledgeAddLabel = computed(() => {
    if (!selectedKeys.value.length) {
        return t('agents.evals.index.knowledge_dialog.add_cases');
    }

    return t('agents.evals.index.knowledge_dialog.add_count', { count: selectedKeys.value.length }, selectedKeys.value.length);
});
</script>

<template>
    <AgentLayout
        :agent="props.agent"
        :title="t('agents.evals.index.title')"
        :description="t('agents.evals.index.description')"
    >
        <template #header-actions>
            <Button v-if="canManage" :disabled="!canRun || runProcessing" @click="triggerRun">
                <LoaderCircle v-if="runProcessing || hasRunning" class="me-2 h-4 w-4 animate-spin" />
                <Play v-else class="me-2 h-4 w-4" />
                {{ t('agents.evals.index.run_suite') }}
            </Button>
        </template>

        <Head :title="t('agents.evals.index.head_title', { name: props.agent.name })" />

        <TooltipProvider :delay-duration="200">
            <div class="flex justify-center">
                <div class="min-h-[calc(100vh-220px)] w-full max-w-5xl space-y-8">
                    <!-- Cases -->
                    <section class="space-y-3">
                        <div class="flex items-center justify-between gap-4">
                            <div>
                                <h2 class="text-base font-semibold">{{ t('agents.evals.index.cases_heading') }}</h2>
                                <p class="text-muted-foreground text-sm">
                                    {{ t('agents.evals.index.cases_summary', { total: props.cases.length, active: activeCases }) }}
                                </p>
                            </div>
                            <div v-if="canManage" class="flex items-center gap-2">
                                <Button size="sm" variant="outline" @click="openFromKnowledge">
                                    <Sparkles class="me-2 h-4 w-4" />
                                    {{ t('agents.evals.index.from_knowledge') }}
                                </Button>
                                <Button size="sm" variant="outline" @click="openCreate">
                                    <Plus class="me-2 h-4 w-4" />
                                    {{ t('agents.evals.index.add_case') }}
                                </Button>
                            </div>
                        </div>

                        <div class="bg-card rounded-lg border">
                            <Table class="[&_td]:px-4 [&_td]:py-3 [&_th]:px-4">
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>{{ t('agents.evals.index.table.case') }}</TableHead>
                                        <TableHead>{{ t('agents.evals.index.table.scorer') }}</TableHead>
                                        <TableHead>{{ t('agents.evals.index.table.status') }}</TableHead>
                                        <TableHead class="w-0 text-right">{{ t('agents.evals.index.table.actions') }}</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    <TableRow v-for="item in props.cases" :key="item.id" class="hover:bg-muted/50">
                                        <TableCell class="max-w-md">
                                            <Tooltip>
                                                <TooltipTrigger as-child>
                                                    <div class="truncate font-medium">{{ item.name }}</div>
                                                </TooltipTrigger>
                                                <TooltipContent class="max-w-sm">
                                                    <p class="whitespace-normal">{{ item.name }}</p>
                                                </TooltipContent>
                                            </Tooltip>
                                            <!-- Knowledge-created cases reuse the question as the name — don't print it twice. -->
                                            <Tooltip v-if="item.input !== item.name">
                                                <TooltipTrigger as-child>
                                                    <div class="text-muted-foreground truncate text-sm">{{ item.input }}</div>
                                                </TooltipTrigger>
                                                <TooltipContent class="max-w-sm">
                                                    <p class="whitespace-normal">{{ item.input }}</p>
                                                </TooltipContent>
                                            </Tooltip>
                                        </TableCell>
                                        <TableCell>
                                            <Badge variant="outline" class="gap-1.5">
                                                <component :is="scorerIcon(item.scorer_type)" class="h-3 w-3" />
                                                {{ scorerLabel(item.scorer_type) }}
                                            </Badge>
                                        </TableCell>
                                        <TableCell>
                                            <Badge
                                                :variant="item.active ? 'secondary' : 'outline'"
                                                class="gap-1.5"
                                                :class="item.active ? '' : 'text-muted-foreground'"
                                            >
                                                <span
                                                    class="h-1.5 w-1.5 rounded-full"
                                                    :class="item.active ? 'bg-emerald-500' : 'bg-muted-foreground/40'"
                                                />
                                                {{ item.active ? t('agents.evals.index.status_active') : t('agents.evals.index.status_inactive') }}
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
                                            {{ t('agents.evals.index.empty_cases') }}
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
                                <h2 class="text-base font-semibold">{{ t('agents.evals.index.recent_runs') }}</h2>
                                <p class="text-muted-foreground text-sm">{{ t('agents.evals.index.recent_runs_description') }}</p>
                            </div>
                            <Button v-if="canManage && props.runs.length > 0" size="sm" variant="outline" @click="isClearOpen = true">
                                <Trash2 class="me-2 h-4 w-4" />
                                {{ t('agents.evals.index.clear') }}
                            </Button>
                        </div>

                        <div class="bg-card rounded-lg border">
                            <Table class="[&_td]:px-4 [&_td]:py-3 [&_th]:px-4">
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>{{ t('agents.evals.index.table.by') }}</TableHead>
                                        <TableHead>{{ t('agents.evals.index.table.pass_rate') }}</TableHead>
                                        <TableHead>{{ t('agents.evals.index.table.passed_failed') }}</TableHead>
                                        <TableHead>{{ t('agents.evals.index.table.avg_score') }}</TableHead>
                                        <TableHead>{{ t('agents.evals.index.table.status') }}</TableHead>
                                        <TableHead class="text-right">{{ t('agents.evals.index.table.when') }}</TableHead>
                                        <TableHead v-if="canManage" class="w-0"></TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    <TableRow
                                        v-for="run in props.runs"
                                        :key="run.id"
                                        class="hover:bg-muted/50 cursor-pointer"
                                        @click="openRun(run.external_id)"
                                    >
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
                                                <span
                                                    class="flex items-center gap-1"
                                                    :class="run.failed_count > 0 ? 'text-destructive' : 'text-muted-foreground'"
                                                >
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
                                                {{ run.status }}
                                            </Badge>
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
                                            {{ t('agents.evals.index.empty_runs') }}
                                        </TableCell>
                                    </TableRow>
                                </TableBody>
                            </Table>
                        </div>
                    </section>
                </div>
            </div>
        </TooltipProvider>

        <!-- Create / edit case dialog -->
        <Dialog v-model:open="isCaseDialogOpen">
            <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>{{ editingId ? t('agents.evals.index.case_dialog.edit_title') : t('agents.evals.index.case_dialog.add_title') }}</DialogTitle>
                    <DialogDescription>{{ t('agents.evals.index.case_dialog.description') }}</DialogDescription>
                </DialogHeader>

                <div class="space-y-4">
                    <div class="space-y-1.5">
                        <Label for="case-name">{{ t('agents.evals.index.case_dialog.name_label') }}</Label>
                        <div class="relative">
                            <Tag class="text-muted-foreground pointer-events-none absolute top-1/2 start-3 h-4 w-4 -translate-y-1/2" />
                            <Input
                                id="case-name"
                                v-model="form.name"
                                :placeholder="t('agents.evals.index.case_dialog.name_placeholder')"
                                class="ps-9"
                            />
                        </div>
                        <p v-if="form.errors.name" class="text-destructive text-xs">{{ form.errors.name }}</p>
                    </div>

                    <div class="space-y-1.5">
                        <Label for="case-input">{{ t('agents.evals.index.case_dialog.input_label') }}</Label>
                        <div class="relative">
                            <MessageSquare class="text-muted-foreground pointer-events-none absolute top-3 start-3 h-4 w-4" />
                            <Textarea
                                id="case-input"
                                v-model="form.input"
                                rows="3"
                                :placeholder="t('agents.evals.index.case_dialog.input_placeholder')"
                                class="ps-9"
                            />
                        </div>
                        <p v-if="form.errors.input" class="text-destructive text-xs">{{ form.errors.input }}</p>
                    </div>

                    <div class="space-y-1.5">
                        <Label>{{ t('agents.evals.index.case_dialog.scorer_label') }}</Label>
                        <Select v-model="form.scorer_type">
                            <SelectTrigger>
                                <SelectValue :placeholder="t('agents.evals.index.case_dialog.scorer_placeholder')" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="type in props.scorerTypes" :key="type" :value="type">
                                    <span class="flex items-center gap-2">
                                        <component :is="scorerIcon(type)" class="text-muted-foreground h-3.5 w-3.5" />
                                        {{ scorerLabel(type) }}
                                    </span>
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <p class="text-muted-foreground text-xs">{{ scorerHelp(form.scorer_type) }}</p>
                    </div>

                    <!-- Scorer-specific config -->
                    <template v-if="form.scorer_type === 'contains'">
                        <div class="space-y-1.5">
                            <Label for="cfg-value">{{ t('agents.evals.index.case_dialog.expected_substring') }}</Label>
                            <div class="relative">
                                <Search class="text-muted-foreground pointer-events-none absolute top-1/2 start-3 h-4 w-4 -translate-y-1/2" />
                                <Input
                                    id="cfg-value"
                                    v-model="form.value"
                                    :placeholder="t('agents.evals.index.case_dialog.expected_substring_placeholder')"
                                    class="ps-9"
                                />
                            </div>
                            <p v-if="configError('value')" class="text-destructive text-xs">{{ configError('value') }}</p>
                        </div>
                        <div class="flex items-center gap-3">
                            <Switch id="cfg-cs" v-model="form.case_sensitive" />
                            <Label for="cfg-cs" class="cursor-pointer font-normal">{{ t('agents.evals.index.case_dialog.case_sensitive') }}</Label>
                        </div>
                    </template>

                    <div v-else-if="form.scorer_type === 'regex'" class="space-y-1.5">
                        <Label for="cfg-pattern">{{ t('agents.evals.index.case_dialog.pattern_label') }}</Label>
                        <div class="relative">
                            <Regex class="text-muted-foreground pointer-events-none absolute top-1/2 start-3 h-4 w-4 -translate-y-1/2" />
                            <Input
                                id="cfg-pattern"
                                v-model="form.pattern"
                                :placeholder="t('agents.evals.index.case_dialog.pattern_placeholder')"
                                class="ps-9 font-mono"
                            />
                        </div>
                        <p v-if="configError('pattern')" class="text-destructive text-xs">{{ configError('pattern') }}</p>
                    </div>

                    <div v-else-if="form.scorer_type === 'json_schema'" class="space-y-1.5">
                        <Label for="cfg-required">{{ t('agents.evals.index.case_dialog.required_keys') }}</Label>
                        <div class="relative">
                            <Braces class="text-muted-foreground pointer-events-none absolute top-1/2 start-3 h-4 w-4 -translate-y-1/2" />
                            <Input
                                id="cfg-required"
                                v-model="form.required"
                                :placeholder="t('agents.evals.index.case_dialog.required_keys_placeholder')"
                                class="ps-9"
                            />
                        </div>
                        <p class="text-muted-foreground text-xs">{{ t('agents.evals.index.case_dialog.required_keys_hint') }}</p>
                    </div>

                    <div v-else-if="form.scorer_type === 'llm_judge'" class="space-y-1.5">
                        <Label for="cfg-criteria">{{ t('agents.evals.index.case_dialog.grading_criteria') }}</Label>
                        <div class="relative">
                            <Scale class="text-muted-foreground pointer-events-none absolute top-3 start-3 h-4 w-4" />
                            <Textarea
                                id="cfg-criteria"
                                v-model="form.criteria"
                                rows="3"
                                :placeholder="t('agents.evals.index.case_dialog.grading_criteria_placeholder')"
                                class="ps-9"
                            />
                        </div>
                        <p v-if="configError('criteria')" class="text-destructive text-xs">
                            {{ configError('criteria') }}
                        </p>
                    </div>

                    <div v-if="['exact', 'llm_judge'].includes(form.scorer_type)" class="space-y-1.5">
                        <Label for="cfg-expected">
                            {{
                                form.scorer_type === 'exact'
                                    ? t('agents.evals.index.case_dialog.expected_answer')
                                    : t('agents.evals.index.case_dialog.reference_answer')
                            }}
                        </Label>
                        <div class="relative">
                            <FileText class="text-muted-foreground pointer-events-none absolute top-3 left-3 h-4 w-4" />
                            <Textarea id="cfg-expected" v-model="form.expected_output" rows="2" class="pl-9" />
                        </div>
                        <p v-if="form.errors.expected_output" class="text-destructive text-xs">{{ form.errors.expected_output }}</p>
                    </div>

                    <div class="flex items-center gap-3">
                        <Switch id="cfg-active" v-model="form.active" />
                        <Label for="cfg-active" class="cursor-pointer font-normal">{{ t('agents.evals.index.case_dialog.active_label') }}</Label>
                    </div>
                </div>

                <DialogFooter>
                    <Button variant="outline" :disabled="form.processing" @click="isCaseDialogOpen = false">
                        <X class="me-2 h-4 w-4" />
                        {{ t('common.cancel') }}
                    </Button>
                    <Button :disabled="form.processing" @click="submitCase">
                        <LoaderCircle v-if="form.processing" class="me-2 h-4 w-4 animate-spin" />
                        <Check v-else-if="editingId" class="me-2 h-4 w-4" />
                        <Plus v-else class="me-2 h-4 w-4" />
                        {{ editingId ? t('agents.evals.index.case_dialog.save') : t('agents.evals.index.add_case') }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Bulk add from knowledge -->
        <Dialog v-model:open="isKnowledgeOpen">
            <DialogContent class="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>{{ t('agents.evals.index.knowledge_dialog.title') }}</DialogTitle>
                    <DialogDescription>{{ t('agents.evals.index.knowledge_dialog.description') }}</DialogDescription>
                </DialogHeader>

                <div class="relative">
                    <Search class="text-muted-foreground absolute top-1/2 start-3 h-4 w-4 -translate-y-1/2" />
                    <Input v-model="knowledgeSearch" :placeholder="t('agents.evals.index.knowledge_dialog.search_placeholder')" class="ps-9" />
                </div>

                <ScrollArea class="-mx-1 h-72 px-1">
                    <div v-if="knowledgeLoading" class="flex h-full items-center justify-center py-8">
                        <LoaderCircle class="text-muted-foreground h-5 w-5 animate-spin" />
                    </div>
                    <div v-else class="space-y-1">
                        <div
                            v-for="q in filteredQuestions"
                            :key="q.key"
                            role="button"
                            :aria-disabled="q.already_added"
                            class="hover:bg-muted/50 flex w-full cursor-pointer items-start gap-3 rounded-md border p-3 text-left text-sm transition-colors"
                            :class="[
                                isQuestionSelected(q) ? 'border-primary bg-primary/5' : 'border-transparent',
                                q.already_added ? 'cursor-not-allowed opacity-60 hover:bg-transparent' : '',
                            ]"
                            @click="toggleQuestion(q)"
                        >
                            <Checkbox
                                :model-value="q.already_added || isQuestionSelected(q)"
                                :disabled="q.already_added"
                                class="pointer-events-none mt-0.5"
                            />
                            <span class="flex-1">{{ q.question }}</span>
                            <Badge variant="outline" class="shrink-0">{{ q.source }}</Badge>
                            <Badge v-if="q.already_added" variant="secondary" class="shrink-0">{{ t('agents.evals.index.knowledge_dialog.added_badge') }}</Badge>
                        </div>

                        <p v-if="!filteredQuestions.length" class="text-muted-foreground py-8 text-center text-sm">
                            {{
                                (props.knowledgeQuestions ?? []).length
                                    ? t('agents.evals.index.knowledge_dialog.no_search_results')
                                    : t('agents.evals.index.knowledge_dialog.no_questions')
                            }}
                        </p>
                    </div>
                </ScrollArea>

                <DialogFooter>
                    <Button type="button" variant="outline" :disabled="knowledgeSubmitting" @click="isKnowledgeOpen = false">
                        <X class="me-2 h-4 w-4" />
                        {{ t('common.cancel') }}
                    </Button>
                    <Button type="button" :disabled="!selectedKeys.length || knowledgeSubmitting" @click="submitKnowledge">
                        <LoaderCircle v-if="knowledgeSubmitting" class="me-2 h-4 w-4 animate-spin" />
                        <Plus v-else class="me-2 h-4 w-4" />
                        {{ knowledgeAddLabel }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Delete confirmation -->
        <Dialog v-model:open="isDeleteOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{{ t('agents.evals.index.delete_case_title') }}</DialogTitle>
                    <DialogDescription>
                        {{ t('agents.evals.index.delete_case_description', { name: deletingCase?.name ?? '' }) }}
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button variant="outline" @click="isDeleteOpen = false">{{ t('common.cancel') }}</Button>
                    <Button variant="destructive" @click="deleteCase">{{ t('agents.leads.delete') }}</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Delete single run -->
        <Dialog v-model:open="isDeleteRunOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{{ t('agents.evals.index.delete_run_title') }}</DialogTitle>
                    <DialogDescription>{{ t('agents.evals.index.delete_run_description') }}</DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button variant="outline" @click="isDeleteRunOpen = false">{{ t('common.cancel') }}</Button>
                    <Button variant="destructive" @click="deleteRun">{{ t('agents.leads.delete') }}</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Clear all runs -->
        <Dialog v-model:open="isClearOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{{ t('agents.evals.index.clear_runs_title') }}</DialogTitle>
                    <DialogDescription>
                        {{ t('agents.evals.index.clear_runs_description', { count: props.runs.length }, props.runs.length) }}
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button variant="outline" @click="isClearOpen = false">{{ t('common.cancel') }}</Button>
                    <Button variant="destructive" @click="clearRuns">{{ t('agents.evals.index.clear_all') }}</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AgentLayout>
</template>
