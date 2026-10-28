<script setup lang="ts">
/**
 * The "turned off" screen of an analytics page (sentiment, tone, topics): a
 * big switch and an enable button that emit `enable`, or — when the chat log
 * is off and nothing could be analysed — the reason it can't be enabled.
 */
import { Button } from '@/shared/ui/button';
import { Switch } from '@/shared/ui/switch';
import { Zap } from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

interface Props {
    // The feature name, e.g. "Sentiment analysis".
    title: string;
    description?: string;
    // True while the enable request is in flight.
    enabling?: boolean;
    // When false the feature can't be enabled from here (the chat-log master
    // switch is off). The toggle is shown disabled and we explain why via
    // `blockedReason` instead of offering an enable button.
    canEnable?: boolean;
    blockedReason?: string;
}

const props = withDefaults(defineProps<Props>(), {
    description: '',
    enabling: false,
    canEnable: true,
    blockedReason: '',
});

const emit = defineEmits<{ enable: [] }>();

const { t } = useI18n();

const enableAriaLabel = computed(() => t('shared.enable_feature', { title: props.title }));
const heading = computed(() => (props.canEnable ? t('shared.feature_turned_off', { title: props.title }) : t('shared.chat_log_off')));

const onToggle = () => {
    if (props.canEnable && !props.enabling) {
        emit('enable');
    }
};
</script>

<template>
    <div class="flex min-h-[calc(100vh-20rem)] items-center justify-center">
        <div class="w-full max-w-md space-y-8 text-center">
            <!-- The big toggle — the hero affordance. Flipping it on enables the
                 feature for this agent; we mirror the in-flight state so it slides
                 to "on" while saving. Disabled when the chat log is off. -->
            <div class="flex justify-center py-3">
                <Switch
                    :model-value="enabling"
                    :disabled="enabling || !canEnable"
                    :aria-label="enableAriaLabel"
                    class="scale-[2.4]"
                    @update:model-value="onToggle"
                />
            </div>

            <div class="space-y-3">
                <h2 class="text-foreground text-xl font-semibold">
                    {{ heading }}
                </h2>
                <p class="text-muted-foreground">{{ canEnable ? description : blockedReason }}</p>
            </div>

            <Button
                v-if="canEnable"
                size="lg"
                :disabled="enabling"
                class="gap-2 shadow-lg transition-all duration-300 hover:shadow-xl"
                @click="emit('enable')"
            >
                <Zap class="h-5 w-5" />
                {{ enabling ? t('shared.enabling') : t('shared.enable_now') }}
            </Button>
        </div>
    </div>
</template>
