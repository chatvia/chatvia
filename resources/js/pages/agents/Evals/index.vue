<script setup lang="ts">
// Eval suite page for one agent: the list of test cases, the run history with
// live progress, the safety net (automatic runs, source coverage), and the
// dialogs to add, edit and delete them. Viewing needs
// `agent.evals.view`; changing anything needs `agent.evals.manage`.
import {
    AlertDialog,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Button } from '@/components/ui/button';
import { TooltipProvider } from '@/components/ui/tooltip';
import { useTeamPermissions } from '@/composables/useTeamPermissions';
import AgentLayout from '@/layouts/AgentLayout.vue';
import { destroy as destroyCase } from '@/routes/agents/evals/cases';
import { clear as clearRunHistory, destroy as destroyRun, show as showRun, store as storeRun } from '@/routes/agents/evals/runs';
import { Head, router } from '@inertiajs/vue3';
import { LoaderCircle, Play } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner';
import EvalCaseDialog from './EvalCaseDialog.vue';
import EvalCasesSection from './EvalCasesSection.vue';
import EvalKnowledgeDialog from './EvalKnowledgeDialog.vue';
import EvalRunsSection from './EvalRunsSection.vue';
import EvalSafetyNetSection from './EvalSafetyNetSection.vue';
import type { EvalAgent, EvalCase, EvalRun, KnowledgeQuestion } from './types';
import { useLiveEvalRuns } from './useLiveEvalRuns';

interface Props {
    agent: EvalAgent;
    cases: EvalCase[];
    runs: EvalRun[];
    scorerTypes: string[];
    // Which knowledge sources the last finished run drew on; null before the first.
    coverage: Domain.Evaluation.Data.Resources.EvalSourceCoverageData | null;
    autoRun: boolean;
    // Optional prop — absent until the "From knowledge" dialog triggers a partial reload.
    knowledgeQuestions?: KnowledgeQuestion[];
}

const props = defineProps<Props>();

const { t } = useI18n();
const { hasPermission } = useTeamPermissions();

const canManage = computed(() => hasPermission('agent.evals.manage'));

const { runs, hasRunning } = useLiveEvalRuns(props.agent.external_id, () => props.runs);

const activeCases = computed(() => props.cases.filter((c) => c.active).length);
const canRun = computed(() => canManage.value && activeCases.value > 0 && !hasRunning.value);

// ---- Case dialogs ----------------------------------------------------------

const isCaseDialogOpen = ref(false);
const editingCase = ref<EvalCase | null>(null);
const isKnowledgeOpen = ref(false);

const openCreate = () => {
    editingCase.value = null;
    isCaseDialogOpen.value = true;
};

const openEdit = (item: EvalCase) => {
    editingCase.value = item;
    isCaseDialogOpen.value = true;
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
    router.delete(destroyCase.url({ agent: props.agent.external_id, case: deletingCase.value.external_id }), {
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
        storeRun.url(props.agent.external_id),
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

const openRun = (externalId: string) => router.visit(showRun.url({ agent: props.agent.external_id, run: externalId }));

// ---- Run delete / clear ----------------------------------------------------

const deletingRun = ref<EvalRun | null>(null);
const isDeleteRunOpen = ref(false);

const confirmDeleteRun = (run: EvalRun) => {
    deletingRun.value = run;
    isDeleteRunOpen.value = true;
};

const deleteRun = () => {
    if (!deletingRun.value) return;
    router.delete(destroyRun.url({ agent: props.agent.external_id, run: deletingRun.value.external_id }), {
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
    router.delete(clearRunHistory.url(props.agent.external_id), {
        preserveScroll: true,
        onSuccess: () => {
            toast.success(t('agents.evals.index.toasts.history_cleared'));
            isClearOpen.value = false;
        },
    });
};
</script>

<template>
    <AgentLayout :agent="props.agent" :title="t('agents.evals.index.title')" :description="t('agents.evals.index.description')">
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
                    <EvalCasesSection
                        :cases="props.cases"
                        :active-count="activeCases"
                        :can-manage="canManage"
                        @add="openCreate"
                        @from-knowledge="isKnowledgeOpen = true"
                        @edit="openEdit"
                        @delete="confirmDelete"
                    />

                    <EvalRunsSection :runs="runs" :can-manage="canManage" @open="openRun" @delete="confirmDeleteRun" @clear="isClearOpen = true" />

                    <EvalSafetyNetSection
                        :agent-external-id="props.agent.external_id"
                        :auto-run="props.autoRun"
                        :coverage="props.coverage"
                        :can-manage="canManage"
                        @from-knowledge="isKnowledgeOpen = true"
                    />
                </div>
            </div>
        </TooltipProvider>

        <EvalCaseDialog
            v-model:open="isCaseDialogOpen"
            :agent-external-id="props.agent.external_id"
            :scorer-types="props.scorerTypes"
            :editing="editingCase"
        />

        <EvalKnowledgeDialog v-model:open="isKnowledgeOpen" :agent-external-id="props.agent.external_id" :questions="props.knowledgeQuestions" />

        <!-- Delete confirmation -->
        <AlertDialog v-model:open="isDeleteOpen">
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>{{ t('agents.evals.index.delete_case_title') }}</AlertDialogTitle>
                    <AlertDialogDescription>
                        {{ t('agents.evals.index.delete_case_description', { name: deletingCase?.name ?? '' }) }}
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel>{{ t('common.cancel') }}</AlertDialogCancel>
                    <Button variant="destructive" @click="deleteCase">{{ t('agents.leads.delete') }}</Button>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>

        <!-- Delete single run -->
        <AlertDialog v-model:open="isDeleteRunOpen">
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>{{ t('agents.evals.index.delete_run_title') }}</AlertDialogTitle>
                    <AlertDialogDescription>{{ t('agents.evals.index.delete_run_description') }}</AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel>{{ t('common.cancel') }}</AlertDialogCancel>
                    <Button variant="destructive" @click="deleteRun">{{ t('agents.leads.delete') }}</Button>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>

        <!-- Clear all runs -->
        <AlertDialog v-model:open="isClearOpen">
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>{{ t('agents.evals.index.clear_runs_title') }}</AlertDialogTitle>
                    <AlertDialogDescription>
                        {{ t('agents.evals.index.clear_runs_description', { count: runs.length }, runs.length) }}
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel>{{ t('common.cancel') }}</AlertDialogCancel>
                    <Button variant="destructive" @click="clearRuns">{{ t('agents.evals.index.clear_all') }}</Button>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    </AgentLayout>
</template>
