/**
 * The add/edit eval case form. The form keeps one flat field per scorer
 * setting so switching scorer in the dialog never loses typed values; on submit
 * only the chosen scorer's settings are packed into `scorer_config`.
 */
import { store as storeCase, update as updateCase } from '@/routes/agents/evals/cases';
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner';
import type { EvalCase, ScorerConfig } from './types';

export function useEvalCaseForm(agentExternalId: string) {
    const { t } = useI18n();

    // The case being edited by its external_id (the route key); null = create.
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
        tool: '',
        tool_arguments: '',
        expected_output: '',
        active: true,
    });

    form.transform((data) => {
        const config: ScorerConfig = {};
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
        } else if (data.scorer_type === 'tool_call') {
            config.tool = data.tool;
            // Optional: only check arguments when the user supplied them. Sent as the
            // raw JSON string — the server validates it and the scorer decodes it.
            if (data.tool_arguments.trim()) config.arguments = data.tool_arguments;
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

    // Server validation keys for scorer config are dotted (e.g. "scorer_config.value")
    // and not part of useForm's flat error type — read them through a cast.
    const configError = (key: string): string | undefined => (form.errors as Record<string, string | undefined>)[`scorer_config.${key}`];

    const startCreate = (): void => {
        editingId.value = null;
        form.reset();
        form.clearErrors();
    };

    const startEdit = (item: EvalCase): void => {
        const config = item.scorer_config;
        const required = config?.required;
        const args = config?.arguments;

        editingId.value = item.external_id;
        form.clearErrors();
        form.name = item.name;
        form.input = item.input;
        form.scorer_type = item.scorer_type;
        form.value = config?.value ?? '';
        form.case_sensitive = Boolean(config?.case_sensitive);
        form.pattern = config?.pattern ?? '';
        form.criteria = config?.criteria ?? '';
        form.required = Array.isArray(required) ? required.join(', ') : '';
        form.tool = config?.tool ?? '';
        form.tool_arguments = typeof args === 'string' ? args : args ? JSON.stringify(args, null, 2) : '';
        form.expected_output = item.expected_output ?? '';
        form.active = item.active;
    };

    /** Creates or updates the case; `onSaved` runs before the success toast. */
    const submit = (onSaved: () => void): void => {
        const opts = {
            preserveScroll: true,
            onSuccess: () => {
                onSaved();
                toast.success(editingId.value ? t('agents.evals.index.toasts.case_updated') : t('agents.evals.index.toasts.case_added'));
            },
            onError: (errors: Record<string, string>) => {
                const first = Object.values(errors)[0];
                if (first) toast.error(first);
            },
        };

        if (editingId.value) {
            form.patch(updateCase.url({ agent: agentExternalId, case: editingId.value }), opts);
        } else {
            form.post(storeCase.url(agentExternalId), opts);
        }
    };

    return { form, editingId, configError, startCreate, startEdit, submit };
}
