/**
 * The Model section: the answer temperature. The model itself is managed by
 * the platform and never shown or editable here; owners only tune how
 * precise or creative the answers are.
 */
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { submitSetupForm, type SetupSectionDeps } from './submitSetupForm';

export function useSetupModel({ agentId }: SetupSectionDeps, temperature: number) {
    const { t } = useI18n();

    const form = useForm({ temperature });
    const isSaving = ref(false);

    const save = (): void =>
        submitSetupForm(form, 'patch', route('agents.temperature.update', { agent: agentId() }), {
            saving: isSaving,
            success: t('agents.setup.toasts.saved'),
            error: t('agents.setup.toasts.save_error'),
        });

    return { form, isSaving, save };
}

export type SetupModel = ReturnType<typeof useSetupModel>;
