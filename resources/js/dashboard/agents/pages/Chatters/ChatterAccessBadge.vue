<script setup lang="ts">
// Whether the chatter can use this agent: a "Granted" pill in the primary colour or muted "No access" text.
import { useI18n } from 'vue-i18n';

defineProps<{
    hasAccess: boolean;
}>();

const { t } = useI18n();
</script>

<template>
    <span v-if="hasAccess" class="bg-primary text-primary-foreground inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium">
        {{ t('agents.chatters.access.granted') }}
    </span>
    <span v-else class="text-muted-foreground text-xs">{{ t('agents.chatters.access.no_access') }}</span>
</template>
