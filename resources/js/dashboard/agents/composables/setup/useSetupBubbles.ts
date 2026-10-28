/**
 * The Chat Bubbles section (Widget only): quick-message prompts shown next to
 * the widget launcher. Like starters, each bubble is translated into every
 * enabled language; saving drops blank rows and returns the stored list.
 */
import { saveBubbles, type BubbleItem } from '@/dashboard/agents/api/agentSetup';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner';
import type { SetupSectionDeps } from './submitSetupForm';

export function useSetupBubbles({ agentId, reloadPreview }: SetupSectionDeps, initial: BubbleItem[]) {
    const { t } = useI18n();

    const bubbles = ref<BubbleItem[]>([...initial]);
    const isSaving = ref(false);

    const save = async (): Promise<void> => {
        isSaving.value = true;
        // Blank rows never persist as bubbles.
        const cleaned = bubbles.value
            .map((bubble) => ({ text: bubble.text.trim(), overrides: bubble.overrides ?? {} }))
            .filter((bubble) => bubble.text);
        try {
            const stored = await saveBubbles(agentId(), cleaned);
            bubbles.value = Array.isArray(stored?.bubbles) ? stored.bubbles : cleaned;
            toast.success(t('agents.setup.toasts.bubbles_saved'));
            reloadPreview();
        } catch {
            toast.error(t('agents.setup.toasts.bubbles_error'));
        } finally {
            isSaving.value = false;
        }
    };

    return { bubbles, isSaving, save };
}

export type SetupBubbles = ReturnType<typeof useSetupBubbles>;
