/**
 * The Info section: the chat title, welcome message and composer placeholder,
 * plus their per-language copies. Saving translates the three strings into
 * every supported language (`text_translations`) and then stores the owner's
 * hand-edited languages (`text_overrides`, which win over the AI output).
 */
import { saveAppearanceOverrides, saveAppearanceText, type LocaleMap } from '@/api/agentSetup';
import { validationErrors } from '@/api/http';
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner';
import type { SettingsReader } from './settingValues';
import type { SetupSectionDeps } from './submitSetupForm';

const asLocaleMap = (value: unknown): LocaleMap => (value && typeof value === 'object' ? (value as LocaleMap) : {});

export function useSetupText({ agentId, reloadPreview }: SetupSectionDeps, appearance: SettingsReader) {
    const { t } = useI18n();

    const form = useForm({
        welcome_message: appearance.string('welcome_message'),
        chat_window_title: appearance.string('chat_window_title'),
        composer_placeholder: appearance.string('composer_placeholder'),
        notice: appearance.string('notice'),
    });

    const translations = ref<LocaleMap>((appearance.saved.text_translations as LocaleMap) ?? {});
    const overrides = ref<LocaleMap>((appearance.saved.text_overrides as LocaleMap) ?? {});
    const isSaving = ref(false);

    const save = async (): Promise<void> => {
        isSaving.value = true;
        form.clearErrors();
        try {
            const saved = await saveAppearanceText(agentId(), {
                chat_window_title: form.chat_window_title,
                welcome_message: form.welcome_message,
                composer_placeholder: form.composer_placeholder,
                notice: form.notice,
            });
            translations.value = asLocaleMap(saved?.translations);

            // Then persist the per-language edits made in the flag tabs.
            const stored = await saveAppearanceOverrides(agentId(), overrides.value);
            overrides.value = asLocaleMap(stored?.overrides);

            toast.success(t('agents.setup.toasts.text_saved'));
            reloadPreview();
        } catch (error) {
            const fieldErrors = validationErrors(error);
            if (Object.keys(fieldErrors).length) {
                form.setError(fieldErrors);
            }
            toast.error(t('agents.setup.toasts.text_error'));
        } finally {
            isSaving.value = false;
        }
    };

    return { form, translations, overrides, isSaving, save };
}

export type SetupText = ReturnType<typeof useSetupText>;
