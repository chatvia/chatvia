<script setup lang="ts">
// "Add cases from knowledge" picker. Mining questions from the knowledge base
// is costly, so the server only sends them on demand: each time the dialog
// opens it clears the selection and partially reloads `knowledgeQuestions`.
import { Badge } from '@/shared/ui/badge';
import { Button } from '@/shared/ui/button';
import { Checkbox } from '@/shared/ui/checkbox';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/shared/ui/dialog';
import { Input } from '@/shared/ui/input';
import { ScrollArea } from '@/shared/ui/scroll-area';
import { fromKnowledge } from '@/routes/agents/evals/cases';
import { router } from '@inertiajs/vue3';
import { LoaderCircle, Plus, Search, X } from 'lucide-vue-next';
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

watch(open, (isOpen) => {
    if (!isOpen) return;
    selectedKeys.value = [];
    search.value = '';
    loading.value = true;
    router.reload({ only: ['knowledgeQuestions'], onFinish: () => (loading.value = false) });
});

const filteredQuestions = computed(() => {
    const all = props.questions ?? [];
    const term = search.value.trim().toLowerCase();
    if (!term) return all;
    return all.filter((q) => q.question.toLowerCase().includes(term) || q.source.toLowerCase().includes(term));
});

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
    const items = (props.questions ?? [])
        .filter((q) => selectedKeys.value.includes(q.key))
        .map((q) => ({ type: q.type, id: q.id, question: q.question }));
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

            <div class="relative">
                <Search class="text-muted-foreground absolute start-3 top-1/2 h-4 w-4 -translate-y-1/2" />
                <Input v-model="search" :placeholder="t('agents.evals.index.knowledge_dialog.search_placeholder')" class="ps-9" />
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
                        <Badge variant="outline" class="shrink-0">{{ q.source }}</Badge>
                        <Badge v-if="q.already_added" variant="secondary" class="shrink-0">{{
                            t('agents.evals.index.knowledge_dialog.added_badge')
                        }}</Badge>
                    </div>

                    <p v-if="!filteredQuestions.length" class="text-muted-foreground py-8 text-center text-sm">
                        {{
                            (props.questions ?? []).length
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
