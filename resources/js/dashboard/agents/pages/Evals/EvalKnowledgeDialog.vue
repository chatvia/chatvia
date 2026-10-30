<script setup lang="ts">
// "Add cases from knowledge" picker. Mining questions from the knowledge base
// is costly, so the server only sends them on demand: each time the dialog
// opens it clears the selection and partially reloads `knowledgeQuestions`.
// "Generate with AI" asks the server for a few more questions written from
// a random sample of the agent's chunks; they join the top of the list,
// ticked, and are added through the same route as the mined ones.
import { generateKnowledgeQuestions } from '@/dashboard/agents/api/agentEvals';
import { errorMessage } from '@/shared/api/http';
import { Badge } from '@/shared/ui/badge';
import { Button } from '@/shared/ui/button';
import { Checkbox } from '@/shared/ui/checkbox';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/shared/ui/dialog';
import { Input } from '@/shared/ui/input';
import { ScrollArea } from '@/shared/ui/scroll-area';
import { fromKnowledge } from '@/routes/agents/evals/cases';
import { router } from '@inertiajs/vue3';
import { LoaderCircle, Plus, Search, Sparkles, X } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner';
import type { KnowledgeQuestion } from './types';

const props = defineProps<{
    agentExternalId: string;
    // Absent until the dialog's partial reload has returned.
    questions?: KnowledgeQuestion[];
}>();

const open = defineModel<boolean>('open', { required: true });

const { t } = useI18n();

const loading = ref(false);
const search = ref('');
const selectedKeys = ref<string[]>([]);
const submitting = ref(false);
// Questions the AI wrote in this opening, newest first.
const generated = ref<KnowledgeQuestion[]>([]);
const generating = ref(false);

const GENERATE_COUNT = 5;

watch(open, (isOpen) => {
    if (!isOpen) return;
    selectedKeys.value = [];
    generated.value = [];
    search.value = '';
    loading.value = true;
    router.reload({ only: ['knowledgeQuestions'], onFinish: () => (loading.value = false) });
});

const allQuestions = computed(() => [...generated.value, ...(props.questions ?? [])]);

const filteredQuestions = computed(() => {
    const all = allQuestions.value;
    const term = search.value.trim().toLowerCase();
    if (!term) return all;
    return all.filter((q) => q.question.toLowerCase().includes(term) || q.source.toLowerCase().includes(term));
});

const isGenerated = (q: KnowledgeQuestion) => q.key.startsWith('ai:');

// Asks for a few AI-written questions. Ones already in the list (mined or
// from an earlier click) are skipped; the new ones are ticked at once.
const generate = async () => {
    generating.value = true;
    try {
        const { questions } = await generateKnowledgeQuestions(props.agentExternalId, GENERATE_COUNT);
        const listed = new Set(allQuestions.value.map((q) => q.question.trim().toLowerCase()));
        const fresh = questions.filter((q) => !listed.has(q.question.trim().toLowerCase()));

        if (!fresh.length) {
            toast.info(t('agents.evals.index.knowledge_dialog.generate_empty'));
            return;
        }

        generated.value = [...fresh, ...generated.value];
        selectedKeys.value = [...fresh.filter((q) => !q.already_added).map((q) => q.key), ...selectedKeys.value];
    } catch (error) {
        toast.error(errorMessage(error, t('agents.evals.index.knowledge_dialog.generate_failed')));
    } finally {
        generating.value = false;
    }
};

const isSelected = (q: KnowledgeQuestion) => selectedKeys.value.includes(q.key);

const toggle = (q: KnowledgeQuestion) => {
    if (q.already_added) return;
    selectedKeys.value = isSelected(q) ? selectedKeys.value.filter((k) => k !== q.key) : [...selectedKeys.value, q.key];
};

const addLabel = computed(() => {
    if (!selectedKeys.value.length) {
        return t('agents.evals.index.knowledge_dialog.add_cases');
    }

    return t('agents.evals.index.knowledge_dialog.add_count', { count: selectedKeys.value.length }, selectedKeys.value.length);
});

const submit = () => {
    const items = allQuestions.value.filter((q) => selectedKeys.value.includes(q.key)).map((q) => ({ type: q.type, id: q.id, question: q.question }));
    if (!items.length) return;

    submitting.value = true;
    router.post(
        fromKnowledge.url(props.agentExternalId),
        { items },
        {
            preserveScroll: true,
            onSuccess: () => {
                open.value = false;
                toast.success(t('agents.evals.index.toasts.cases_added', { count: items.length }, items.length));
            },
            onError: (errors: Record<string, string>) => {
                const first = Object.values(errors)[0];
                if (first) toast.error(first);
            },
            onFinish: () => {
                submitting.value = false;
            },
        },
    );
};
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>{{ t('agents.evals.index.knowledge_dialog.title') }}</DialogTitle>
                <DialogDescription>{{ t('agents.evals.index.knowledge_dialog.description') }}</DialogDescription>
            </DialogHeader>

            <div class="flex items-center gap-2">
                <div class="relative flex-1">
                    <Search class="text-muted-foreground absolute start-3 top-1/2 h-4 w-4 -translate-y-1/2" />
                    <Input v-model="search" :placeholder="t('agents.evals.index.knowledge_dialog.search_placeholder')" class="ps-9" />
                </div>
                <Button type="button" variant="outline" :disabled="generating || loading || submitting" @click="generate">
                    <LoaderCircle v-if="generating" class="me-2 h-4 w-4 animate-spin" />
                    <Sparkles v-else class="me-2 h-4 w-4" />
                    {{ generating ? t('agents.evals.index.knowledge_dialog.generating') : t('agents.evals.index.knowledge_dialog.generate') }}
                </Button>
            </div>

            <ScrollArea class="-mx-1 h-72 px-1">
                <div v-if="loading" class="flex h-full items-center justify-center py-8">
                    <LoaderCircle class="text-muted-foreground h-5 w-5 animate-spin" />
                </div>
                <div v-else class="space-y-1">
                    <div
                        v-for="q in filteredQuestions"
                        :key="q.key"
                        role="button"
                        :aria-disabled="q.already_added"
                        class="hover:bg-muted/50 flex w-full cursor-pointer items-start gap-3 rounded-md border p-3 text-start text-sm transition-colors"
                        :class="[
                            isSelected(q) ? 'border-primary bg-primary/5' : 'border-transparent',
                            q.already_added ? 'cursor-not-allowed opacity-60 hover:bg-transparent' : '',
                        ]"
                        @click="toggle(q)"
                    >
                        <Checkbox :model-value="q.already_added || isSelected(q)" :disabled="q.already_added" class="pointer-events-none mt-0.5" />
                        <span class="flex-1">{{ q.question }}</span>
                        <Badge v-if="isGenerated(q)" variant="secondary" class="shrink-0">
                            <Sparkles class="me-1 h-3 w-3" />
                            {{ t('agents.evals.index.knowledge_dialog.ai_badge') }}
                        </Badge>
                        <Badge variant="outline" class="shrink-0">{{ q.source }}</Badge>
                        <Badge v-if="q.already_added" variant="secondary" class="shrink-0">{{
                            t('agents.evals.index.knowledge_dialog.added_badge')
                        }}</Badge>
                    </div>

                    <p v-if="!filteredQuestions.length" class="text-muted-foreground py-8 text-center text-sm">
                        {{
                            allQuestions.length
                                ? t('agents.evals.index.knowledge_dialog.no_search_results')
                                : t('agents.evals.index.knowledge_dialog.no_questions')
                        }}
                    </p>
                </div>
            </ScrollArea>

            <DialogFooter>
                <Button type="button" variant="outline" :disabled="submitting" @click="open = false">
                    <X class="me-2 h-4 w-4" />
                    {{ t('common.cancel') }}
                </Button>
                <Button type="button" :disabled="!selectedKeys.length || submitting" @click="submit">
                    <LoaderCircle v-if="submitting" class="me-2 h-4 w-4 animate-spin" />
                    <Plus v-else class="me-2 h-4 w-4" />
                    {{ addLabel }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
