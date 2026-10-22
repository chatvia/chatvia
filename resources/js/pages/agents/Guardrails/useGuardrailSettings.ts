/**
 * The guardrail settings form for one agent. It lives at page level (not in
 * the settings tab) so unsaved edits survive switching to the Activity tab.
 * The PII guard has no own flag: a non-empty filter list means it is on.
 */
import { update as updateGuardrails } from '@/routes/agents/guardrails';
import { useForm } from '@inertiajs/vue3';
import { computed, ref, type Ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner';
import type { AnswerCheck, GuardSettings, PiiFilterOption } from './types';

interface Options {
    agent: { external_id: string };
    settings: GuardSettings;
    availableFilters: PiiFilterOption[];
    defaultFilters: string[];
}

/** The two classifier guards shown under the PII guard, each with a mode and a sensitivity. */
export const GUARDS = [
    {
        key: 'injection' as const,
        mode: 'injection_mode' as const,
        sensitivity: 'injection_sensitivity' as const,
        label: 'agents.guardrails.injection_label',
        help: 'agents.guardrails.injection_help',
    },
    {
        key: 'toxicity' as const,
        mode: 'toxicity_mode' as const,
        sensitivity: 'toxicity_sensitivity' as const,
        label: 'agents.guardrails.toxicity_label',
        help: 'agents.guardrails.toxicity_help',
    },
];

/**
 * A deep copy of the form's fields, so the form never shares arrays with the
 * props. Only the fields the form edits are kept; the stored translations stay
 * on the server, which makes them on save.
 */
function copy(settings: GuardSettings): GuardSettings {
    return {
        enabled: settings.enabled,
        filters: [...settings.filters],
        pii_actions: { ...settings.pii_actions },
        custom_patterns: settings.custom_patterns.map(({ name, pattern, action }) => ({ name, pattern, action })),
        allowlist: [...settings.allowlist],
        injection: settings.injection,
        injection_mode: settings.injection_mode,
        injection_sensitivity: settings.injection_sensitivity,
        toxicity: settings.toxicity,
        toxicity_mode: settings.toxicity_mode,
        toxicity_sensitivity: settings.toxicity_sensitivity,
        blocked_topics: settings.blocked_topics.map(({ name, description, examples, action, reply }) => ({
            name,
            description,
            examples: [...examples],
            action,
            reply,
        })),
        allowed_scope: settings.allowed_scope,
        topics_mode: settings.topics_mode,
        refusal_message: settings.refusal_message,
        check_answers: settings.check_answers,
        check_before_showing: settings.check_before_showing,
        alert_owner: settings.alert_owner,
    };
}

/** The form data as the server expects it: trimmed, blanks dropped. */
export function payload(d: GuardSettings): GuardSettings {
    return {
        ...copy(d),
        allowlist: d.allowlist.map((value) => value.trim()).filter(Boolean),
        blocked_topics: d.blocked_topics
            .map((topic) => ({
                ...topic,
                name: topic.name.trim(),
                description: topic.description?.trim() || null,
                examples: topic.examples.map((example) => example.trim()).filter(Boolean),
                reply: topic.reply?.trim() || null,
            }))
            .filter((topic) => topic.name !== ''),
        custom_patterns: d.custom_patterns
            .map((pattern) => ({ ...pattern, name: pattern.name.trim() }))
            .filter((pattern) => pattern.name !== '' && pattern.pattern !== ''),
    };
}

export function useGuardrailSettings(props: Options, canManage: Ref<boolean>) {
    const { t } = useI18n();

    const form = useForm<GuardSettings>(copy(props.settings));

    form.transform((d) => payload(d));

    const save = () => {
        form.post(updateGuardrails.url(props.agent.external_id), {
            preserveScroll: true,
            onSuccess: () => toast.success(t('agents.guardrails.saved')),
            onError: () => toast.error(t('agents.guardrails.save_error')),
        });
    };

    // Toggle a boolean field — bound to the whole row's click so the label and
    // description area are part of the hit target, not just the switch.
    const toggle = (key: 'enabled' | 'injection' | 'toxicity' | 'alert_owner') => {
        if (canManage.value) form[key] = !form[key];
    };

    // One choice on the page, two settings on the server: whether answers
    // are checked at all, and whether they wait for the check.
    const answerCheck = computed<AnswerCheck>({
        get: () => (!form.check_answers ? 'off' : form.check_before_showing ? 'before_showing' : 'while_showing'),
        set: (value) => {
            form.check_answers = value !== 'off';
            form.check_before_showing = value === 'before_showing';
        },
    });

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

    return { form, save, toggle, answerCheck, piiEnabled, togglePii, selectedFilters };
}
