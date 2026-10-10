/**
 * On/off state of one per-agent analytics feature (tone, sentiment, topics).
 * The owner must have switched it on for the agent; `enable()` flips the
 * switch from the disabled screen so the page can swap straight to its charts
 * without a reload.
 */
import { enableAnalyticsFeature, type AnalyticsFeature } from '@/api/analytics';
import { computed, ref, toValue, type MaybeRefOrGetter } from 'vue';

interface AnalyticsFeatureOptions {
    agentId: MaybeRefOrGetter<string>;
    feature: AnalyticsFeature;
    /** The per-agent switch as the server rendered it. */
    enabled: boolean;
    /** The chat-log master switch: without stored messages there is nothing to analyse. */
    chatlogEnabled: MaybeRefOrGetter<boolean>;
    /** Runs once the feature was switched on (typically: load the data). */
    onEnabled: () => void;
}

export function useAnalyticsFeature(options: AnalyticsFeatureOptions) {
    // Local mirror of the per-agent switch, so enabling it shows the charts at once.
    const isEnabled = ref(options.enabled);
    const enabling = ref(false);

    /** True when the page should load and show its data. */
    const isActive = computed(() => toValue(options.chatlogEnabled) && isEnabled.value);

    const enable = async () => {
        if (!toValue(options.chatlogEnabled)) return;
        enabling.value = true;
        try {
            await enableAnalyticsFeature(toValue(options.agentId), options.feature);
            isEnabled.value = true;
            options.onEnabled();
        } catch (err) {
            console.error(`Could not enable ${options.feature} analysis:`, err);
        } finally {
            enabling.value = false;
        }
    };

    return { isEnabled, enabling, isActive, enable };
}
