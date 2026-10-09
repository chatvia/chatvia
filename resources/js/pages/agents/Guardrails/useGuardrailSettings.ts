/**
 * The guardrail settings form for one agent. It lives at page level (not in
 * the settings tab) so unsaved edits survive switching to the Activity tab.
 * The PII guard has no own flag: a non-empty filter list means it is on.
 */
import { useForm } from '@inertiajs/vue3';
import { computed, ref, type Ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner';
import type { GuardSettings, PiiFilterOption } from './types';

interface Options {
    agent: { external_id: string };
    settings: GuardSettings;
    availableFilters: PiiFilterOption[];
    defaultFilters: string[];
}

/** The two simple on/off guards shown under the PII guard. */
export const GUARDS = [
    {
        key: 'injection' as const,
        label: 'agents.guardrails.injection_label',
        help: 'agents.guardrails.injection_help',
    },
    { key: 'toxicity' as const, label: 'agents.guardrails.toxicity_label', help: 'agents.guardrails.toxicity_help' },
];

export function useGuardrailSettings(props: Options, canManage: Ref<boolean>) {
    const { t } = useI18n();

    const form = useForm({
        enabled: props.settings.enabled,
        filters: [...props.settings.filters],
        injection: props.settings.injection,
        toxicity: props.settings.toxicity,
        blocked_topics: [...props.settings.blocked_topics],
        allowed_scope: props.settings.allowed_scope,
        refusal_message: props.settings.refusal_message,
    });

    form.transform((d) => ({
        enabled: d.enabled,
        filters: [...d.filters],
        injection: d.injection,
        toxicity: d.toxicity,
        blocked_topics: d.blocked_topics.map((topic) => topic.trim()).filter(Boolean),
        allowed_scope: d.allowed_scope,
        refusal_message: d.refusal_message,
    }));

    const save = () => {
        form.post(route('agents.guardrails.update', props.agent.external_id), {
            preserveScroll: true,
            onSuccess: () => toast.success(t('agents.guardrails.saved')),
            onError: () => toast.error(t('agents.guardrails.save_error')),
        });
    };

    // Toggle a boolean field — bound to the whole row's click so the label and
    // description area are part of the hit target, not just the switch.
    const toggle = (key: 'enabled' | 'injection' | 'toxicity') => {
        if (canManage.value) form[key] = !form[key];
    };

    // Remember the last selection so toggling PII off then on restores it
    // (falling back to the server defaults on first enable).
    const lastFilters = ref<string[]>(props.settings.filters.length ? [...props.settings.filters] : [...props.defaultFilters]);
    const piiEnabled = computed(() => form.filters.length > 0);

    const togglePii = () => {
        if (!canManage.value) return;
        if (form.filters.length) {
            lastFilters.value = [...form.filters];
            form.filters = [];
        } else {
            form.filters = lastFilters.value.length ? [...lastFilters.value] : [...props.defaultFilters];
        }
    };

    const selectedFilters = computed(() => props.availableFilters.filter((f) => form.filters.includes(f.key)));

    return { form, save, toggle, piiEnabled, togglePii, selectedFilters };
}
