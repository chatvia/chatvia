<script setup lang="ts">
/**
 * Shows an analytics page's content only when it may be shown: the license
 * lock when the install has no key, the "turned off" screen (with its enable
 * switch) when the owner or the chat log has it off, else the default slot.
 */
import AnalyticsDisabled from '@/dashboard/agents/components/AnalyticsDisabled.vue';
import LicenseNotice from '@/dashboard/components/license/LicenseNotice.vue';
import { useLicense } from '@/dashboard/composables/useLicense';

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

const { licensed } = useLicense();
</script>

<template>
    <LicenseNotice v-if="!licensed" />

    <!-- The owner switched it off for this agent, or the chat log is off -->
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
