<script setup lang="ts">
/**
 * Shows an analytics page's content only when it may be shown: a locked card
 * when the plan lacks the feature, the "turned off" screen (with its enable
 * switch) when the owner or the chat log has it off, else the default slot.
 */
import AnalyticsDisabled from '@/components/AnalyticsDisabled.vue';
import LockedFeature from '@/components/LockedFeature.vue';

interface Props {
    hasAccess: boolean;
    enabled: boolean;
    chatlogEnabled: boolean;
    enabling: boolean;
    lockedDescription: string;
    disabledTitle: string;
    disabledDescription: string;
    blockedReason: string;
}

defineProps<Props>();

const emit = defineEmits<{ enable: [] }>();
</script>

<template>
    <LockedFeature v-if="!hasAccess" :description="lockedDescription" />

    <!-- Plan allows it, but the owner switched it off for this agent -->
    <AnalyticsDisabled
        v-else-if="!chatlogEnabled || !enabled"
        :title="disabledTitle"
        :description="disabledDescription"
        :can-enable="chatlogEnabled"
        :blocked-reason="blockedReason"
        :enabling="enabling"
        @enable="emit('enable')"
    />

    <slot v-else />
</template>
