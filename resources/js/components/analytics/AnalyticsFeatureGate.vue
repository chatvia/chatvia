<script setup lang="ts">
/**
 * Shows an analytics page's content only when it may be shown: the "turned
 * off" screen (with its enable switch) when the owner or the chat log has it
 * off, else the default slot.
 */
import AnalyticsDisabled from '@/components/AnalyticsDisabled.vue';

interface Props {
    enabled: boolean;
    chatlogEnabled: boolean;
    enabling: boolean;
    disabledTitle: string;
    disabledDescription: string;
    blockedReason: string;
}

defineProps<Props>();

const emit = defineEmits<{ enable: [] }>();
</script>

<template>
    <!-- The owner switched it off for this agent, or the chat log is off -->
    <AnalyticsDisabled
        v-if="!chatlogEnabled || !enabled"
        :title="disabledTitle"
        :description="disabledDescription"
        :can-enable="chatlogEnabled"
        :blocked-reason="blockedReason"
        :enabling="enabling"
        @enable="emit('enable')"
    />

    <slot v-else />
</template>
