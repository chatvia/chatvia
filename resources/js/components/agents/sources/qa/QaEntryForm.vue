<script setup lang="ts">
/**
 * The create / edit form for a Q&A entry: a title, one or more questions and
 * the answer. With an `entry` it edits that entry, otherwise it creates one.
 * Emits `saved` after the server accepted it and `cancel` when the user backs
 * out. The parent mounts a fresh form for each open, so no reset is needed.
 */
import type { QaEntry, QaQuestion } from '@/api/qaSources';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useForm } from '@inertiajs/vue3';
import { LoaderCircle, Plus, Save, Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner';

const props = withDefaults(
    defineProps<{
        agentId: string;
        /** The entry being edited; null for a new entry. */
        entry?: QaEntry | null;
    }>(),
    { entry: null },
);

const emit = defineEmits<{
    saved: [];
    cancel: [];
}>();

const { t } = useI18n();

const blankQuestion = (): QaQuestion => ({ id: crypto.randomUUID(), question: '' });

const form = useForm({
    title: props.entry?.title ?? '',
    questions: props.entry ? props.entry.questions : [blankQuestion()],
    answer: props.entry?.answer ?? '',
});
const isSubmitting = ref(false);

const canSubmit = computed(() => !isSubmitting.value && !!form.title && !!form.answer && form.questions.some((q) => q.question));

function addQuestion(): void {
    form.questions.push(blankQuestion());
}

function removeQuestion(questionId: string): void {
    if (form.questions.length > 1) {
        form.questions = form.questions.filter((q) => q.id !== questionId);
    }
}

function submit(): void {
    isSubmitting.value = true;

    const onFinish = () => {
        isSubmitting.value = false;
    };

    if (props.entry) {
        form.patch(route('agents.sources.qa.update', { agent: props.agentId, id: props.entry.id }), {
            preserveScroll: true,
            onSuccess: () => {
                toast.success(t('agents.sources.qa.updated'));
                emit('saved');
            },
            onError: () => {
                toast.error(t('agents.sources.qa.update_failed'));
            },
            onFinish,
        });
    } else {
        form.post(route('agents.sources.qa.store', { agent: props.agentId }), {
            preserveScroll: true,
            onSuccess: () => {
                toast.success(t('agents.sources.qa.created'));
                emit('saved');
            },
            onError: () => {
                toast.error(t('agents.sources.qa.create_failed'));
            },
            onFinish,
        });
    }
}
</script>

<template>
    <Card>
        <CardHeader>
            <div class="flex items-center justify-between">
                <CardTitle>{{ entry ? t('agents.sources.qa.edit') : t('agents.sources.qa.add') }}</CardTitle>
                <Button variant="ghost" size="sm" @click="emit('cancel')">{{ t('agents.sources.common.cancel') }}</Button>
            </div>
        </CardHeader>
        <CardContent class="space-y-4">
            <div class="space-y-2">
                <Label for="title">{{ t('agents.sources.qa.title_label') }}</Label>
                <Input id="title" v-model="form.title" :placeholder="t('agents.sources.qa.title_placeholder')" :disabled="isSubmitting" />
                <InputError :message="form.errors.title" />
            </div>

            <div class="space-y-2">
                <Label>{{ t('agents.sources.qa.questions_label') }}</Label>
                <div v-for="question in form.questions" :key="question.id" class="flex gap-2">
                    <Input
                        v-model="question.question"
                        :placeholder="t('agents.sources.qa.question_placeholder')"
                        :disabled="isSubmitting"
                        class="flex-1"
                    />
                    <Button
                        v-if="form.questions.length > 1"
                        variant="ghost"
                        size="sm"
                        :disabled="isSubmitting"
                        :aria-label="t('agents.sources.qa.remove_question')"
                        @click="removeQuestion(question.id)"
                    >
                        <Trash2 class="h-4 w-4" />
                    </Button>
                </div>
                <Button variant="outline" size="sm" :disabled="isSubmitting" @click="addQuestion">
                    <Plus class="me-2 h-4 w-4" />
                    {{ t('agents.sources.qa.add_question') }}
                </Button>
                <InputError :message="form.errors.questions" />
            </div>

            <div class="space-y-2">
                <Label for="answer">{{ t('agents.sources.qa.answer_label') }}</Label>
                <Textarea
                    id="answer"
                    v-model="form.answer"
                    :placeholder="t('agents.sources.qa.answer_placeholder')"
                    rows="8"
                    :disabled="isSubmitting"
                />
                <div class="text-muted-foreground text-sm">{{ t('agents.sources.qa.char_count', { count: form.answer.length }) }}</div>
                <InputError :message="form.errors.answer" />
            </div>

            <div class="flex justify-end">
                <Button :disabled="!canSubmit" @click="submit">
                    <LoaderCircle v-if="isSubmitting" class="me-2 h-4 w-4 animate-spin" />
                    <Save v-else class="me-2 h-4 w-4" />
                    {{ entry ? t('agents.sources.qa.update') : t('agents.sources.qa.add') }}
                </Button>
            </div>
        </CardContent>
    </Card>
</template>
