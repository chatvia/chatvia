/**
 * State for the agent's Forms page: which form is open in the editor (a draft
 * copy, or null for the list), saving it (store or update), deleting one, and
 * the AI rewrite of the draft's "when to show it" note.
 */
import { rewriteFormDescription } from '@/api/agentForms';
import { blankForm, editableCopy, type FormDefinition, savePayload } from '@/components/agents/forms/formDefinition';
import { router } from '@inertiajs/vue3';
import { type MaybeRefOrGetter, ref, toValue } from 'vue';
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner';

export function useAgentForms(agentId: MaybeRefOrGetter<string>) {
    const { t } = useI18n();

    const editing = ref<FormDefinition | null>(null);
    const saving = ref(false);
    const rewriting = ref(false);

    function startNew(): void {
        editing.value = blankForm();
    }

    function edit(form: FormDefinition): void {
        editing.value = editableCopy(form);
    }

    function cancel(): void {
        editing.value = null;
    }

    // Ask the LLM to turn the rough "when to show" note into a precise,
    // intent-based trigger. The suggestion only fills the textarea; it is
    // saved with the form.
    async function rewriteDescription(): Promise<void> {
        if (!editing.value || rewriting.value) return;
        rewriting.value = true;
        try {
            editing.value.agent_instructions = await rewriteFormDescription(toValue(agentId), {
                agent_instructions: editing.value.agent_instructions,
                name: editing.value.name,
                form_type: editing.value.form_type,
            });
            toast.success(t('agents.forms.rewrite_done'));
        } catch {
            toast.error(t('agents.forms.rewrite_failed'));
        } finally {
            rewriting.value = false;
        }
    }

    function save(): void {
        const form = editing.value;
        if (!form) return;
        if (!form.name.trim()) {
            toast.error(t('agents.forms.name_required'));
            return;
        }

        saving.value = true;
        const options = {
            preserveScroll: true,
            onSuccess: () => {
                editing.value = null;
                toast.success(t('agents.forms.saved'));
            },
            onError: () => toast.error(t('agents.forms.save_failed')),
            onFinish: () => (saving.value = false),
        };

        if (form.external_id) {
            router.put(route('agents.forms.update', { agent: toValue(agentId), form: form.external_id }), savePayload(form), options);
        } else {
            router.post(route('agents.forms.store', { agent: toValue(agentId) }), savePayload(form), options);
        }
    }

    function destroy(form: FormDefinition): void {
        if (!form.external_id) return;
        router.delete(route('agents.forms.destroy', { agent: toValue(agentId), form: form.external_id }), {
            preserveScroll: true,
            onSuccess: () => toast.success(t('agents.forms.deleted')),
        });
    }

    return { editing, saving, rewriting, startNew, edit, cancel, save, destroy, rewriteDescription };
}
