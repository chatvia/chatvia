/**
 * The Model section: the model the agent answers with (one an admin made
 * available in the admin panel, Settings → AI models), its answer
 * temperature, how hard it reasons (only while the admin lets agents
 * reason), and the other models chatters may pick from (only while the
 * admin lets agents offer a choice). The other generation settings are sent
 * back unchanged.
 */
import { update as updateLlm } from '@/routes/agents/llm';
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { submitSetupForm, type SetupSectionDeps } from './submitSetupForm';

export type LlmSettings = { temperature: number; top_k: number; top_p: number; presence_penalty: number; frequency_penalty: number };

export type ReasoningEffort = Domain.Llm.Enums.ReasoningEffort;

/** Whether chatters may pick the model, and which models besides the agent's own (catalog ids). */
export type ModelChoice = { enabled: boolean; models: number[] };

export function useSetupModel(
    { agentId }: SetupSectionDeps,
    model: number | null,
    settings: LlmSettings,
    reasoningEffort: string | null,
    modelChoice: ModelChoice,
) {
    const { t } = useI18n();

    // Numbers, whatever the stored settings hold, so the slider and toFixed() work.
    const form = useForm({
        model_id: model,
        temperature: Number(settings.temperature),
        top_k: Number(settings.top_k),
        top_p: Number(settings.top_p),
        presence_penalty: Number(settings.presence_penalty),
        frequency_penalty: Number(settings.frequency_penalty),
        reasoning_effort: (reasoningEffort ?? 'off') as ReasoningEffort,
        model_choice_enabled: modelChoice.enabled,
        offered_model_ids: [...modelChoice.models],
    });
    const isSaving = ref(false);

    const save = (): void =>
        submitSetupForm(form, 'patch', updateLlm.url(agentId()), {
            saving: isSaving,
            success: t('agents.setup.toasts.saved'),
            error: t('agents.setup.toasts.save_error'),
        });

    return { form, isSaving, save };
}

export type SetupModel = ReturnType<typeof useSetupModel>;
