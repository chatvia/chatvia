/**
 * The Starters section: the conversation starters shown in an empty chat.
 * Each starter carries its AI translations and the owner's manual edits;
 * saving drops blank rows, translates the text and returns the stored list.
 */
import { saveStarters, type StarterItem } from '@/dashboard/agents/api/agentSetup';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner';
import type { SetupSectionDeps } from './submitSetupForm';

export function useSetupStarters({ agentId, reloadPreview }: SetupSectionDeps, initial: StarterItem[]) {
    const { t } = useI18n();

    const starters = ref<StarterItem[]>([...initial]);
    const isSaving = ref(false);

    const save = async (): Promise<void> => {
        isSaving.value = true;
        // Blank or whitespace-only rows never persist as starters.
        const cleaned = starters.value
            .map((starter) => ({ id: starter.id, text: starter.text.trim(), icon: starter.icon, overrides: starter.overrides ?? {} }))
            .filter((starter) => starter.text);
        try {
            const stored = await saveStarters(agentId(), cleaned);
            starters.value = Array.isArray(stored?.starters) ? stored.starters : cleaned;
            toast.success(t('agents.setup.toasts.starters_saved'));
            reloadPreview();
        } catch {
            toast.error(t('agents.setup.toasts.starters_error'));
        } finally {
            isSaving.value = false;
        }
    };

    return { starters, isSaving, save };
}

export type SetupStarters = ReturnType<typeof useSetupStarters>;
