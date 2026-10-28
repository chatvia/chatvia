/**
 * The Languages section: which UI languages the agent supports. The list
 * drives the hosted-chat language switcher, the flag tabs of the Info,
 * Starters and Bubbles editors, and which languages get auto-translated.
 * A stored empty list means "all", so selecting every language saves `[]`
 * and any language the platform adds later is included automatically.
 */
import { update as updateSettings } from '@/routes/agents/settings';
import { useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { asList, type SettingsReader } from './settingValues';
import { submitSetupForm, type SetupSectionDeps } from './submitSetupForm';

export function useSetupLanguages(
    { agentId, reloadPreview }: SetupSectionDeps,
    appearance: SettingsReader,
    availableLanguages: () => Record<string, string>,
) {
    const { t } = useI18n();

    const allCodes = Object.keys(availableLanguages()).filter((code) => code !== 'auto');
    const savedCodes = asList<string>(appearance.saved.enabled_languages, []);

    const form = useForm({
        enabled_languages: savedCodes.length ? savedCodes : [...allCodes],
    });
    const isSaving = ref(false);

    const toggle = (code: string, on: boolean): void => {
        const enabled = new Set(form.enabled_languages);
        if (on) enabled.add(code);
        else enabled.delete(code);
        // Keep the platform order so the stored list is deterministic.
        form.enabled_languages = allCodes.filter((c) => enabled.has(c));
    };
    const selectAll = (): void => {
        form.enabled_languages = [...allCodes];
    };
    const clear = (): void => {
        form.enabled_languages = [];
    };

    // An agent with no UI language has nothing to render.
    const canSave = computed(() => form.enabled_languages.length > 0);

    const save = (): void => {
        if (!canSave.value) return;
        const isAll = form.enabled_languages.length === allCodes.length;
        submitSetupForm(
            form.transform((data) => ({ enabled_languages: isAll ? [] : data.enabled_languages })),
            'post',
            updateSettings.url({ agent: agentId(), group: 'appearance' }),
            {
                saving: isSaving,
                success: t('agents.setup.toasts.languages_saved'),
                error: t('agents.setup.toasts.languages_error'),
                onSuccess: reloadPreview,
            },
        );
    };

    // code → name of the languages the editors show flag tabs for: the enabled
    // ones, or all when none are picked (live, before saving).
    const enabledLanguageMap = computed<Record<string, string>>(() => {
        const available = availableLanguages();
        const codes = form.enabled_languages;
        if (!codes.length) return available;
        const enabled: Record<string, string> = {};
        for (const code of codes) if (available[code]) enabled[code] = available[code];
        return enabled;
    });

    return { allCodes, form, isSaving, canSave, toggle, selectAll, clear, save, enabledLanguageMap };
}

export type SetupLanguages = ReturnType<typeof useSetupLanguages>;
