<script setup lang="ts">
/**
 * The list view of the agent's Forms page: every form with its type, field
 * count and submission count, plus "New form", "Edit" and delete. Delete asks
 * for confirmation first, because it also removes the form's submissions:
 * the dialog says how many leads go with it.
 */
import type { FormDefinition } from '@/components/agents/forms/formDefinition';
import DeleteConfirmationDialog from '@/components/common/DeleteConfirmationDialog.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { useFormTypeLabel } from '@/composables/agents/forms/useFormTypeLabel';
import { index as submissionsIndex } from '@/routes/agents/forms/submissions';
import { Link } from '@inertiajs/vue3';
import { FileText, Plus, Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';

defineProps<{
    agentId: string;
    forms: FormDefinition[];
}>();

const emit = defineEmits<{
    create: [];
    edit: [form: FormDefinition];
    delete: [form: FormDefinition];
}>();

const { t } = useI18n();
const { formTypeLabel } = useFormTypeLabel();

const pendingDelete = ref<FormDefinition | null>(null);

// Only a form with leads gets the longer warning; otherwise the dialog keeps its default text.
const deleteDescription = computed((): string | undefined => {
    const count = pendingDelete.value?.submissions_count ?? 0;

    return count > 0 ? t('agents.forms.delete_with_leads', { count }, count) : undefined;
});

function confirmDelete(): void {
    if (pendingDelete.value) emit('delete', pendingDelete.value);
    pendingDelete.value = null;
}
</script>

<template>
    <Card>
        <CardHeader>
            <div class="flex items-start justify-between gap-4">
                <div>
                    <CardTitle class="flex items-center gap-2"><FileText class="h-5 w-5" /> {{ t('agents.forms.title') }}</CardTitle>
                    <CardDescription>{{ t('agents.forms.description') }}</CardDescription>
                </div>
                <Button @click="emit('create')"><Plus class="me-1 h-4 w-4" /> {{ t('agents.forms.new') }}</Button>
            </div>
        </CardHeader>
        <CardContent>
            <div v-if="forms.length === 0" class="text-muted-foreground rounded-md border py-12 text-center text-sm">
                {{ t('agents.forms.empty') }}
            </div>
            <div v-else class="divide-y rounded-lg border">
                <div v-for="form in forms" :key="form.external_id ?? form.slug ?? form.name" class="flex items-center gap-4 p-4">
                    <div class="min-w-0 flex-1">
                        <p class="font-medium">{{ form.name }}</p>
                        <p class="text-muted-foreground text-xs">
                            {{ formTypeLabel(form.form_type) }} · {{ form.schema.length }} {{ t('agents.forms.fields') }}
                            <span v-if="!form.is_active"> · {{ t('agents.forms.inactive') }}</span>
                        </p>
                    </div>
                    <Link v-if="form.external_id" :href="submissionsIndex.url({ agent: agentId, form: form.external_id })">
                        <Button variant="ghost" size="sm">{{ t('agents.forms.submissions') }} ({{ form.submissions_count }})</Button>
                    </Link>
                    <Button variant="outline" size="sm" @click="emit('edit', form)">{{ t('agents.forms.edit') }}</Button>
                    <Button variant="ghost" size="icon" :aria-label="t('common.delete')" @click="pendingDelete = form"
                        ><Trash2 class="h-4 w-4"
                    /></Button>
                </div>
            </div>
        </CardContent>
    </Card>

    <DeleteConfirmationDialog
        :open="pendingDelete !== null"
        :description="deleteDescription"
        @confirm="confirmDelete"
        @cancel="pendingDelete = null"
    />
</template>
