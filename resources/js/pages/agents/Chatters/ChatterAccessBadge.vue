<script setup lang="ts">
// Whether the chatter can use this agent: a dark "Granted" pill or muted "No access" text.
import { useI18n } from 'vue-i18n';

defineProps<{
    hasAccess: boolean;
}>();

const { t } = useI18n();
</script>

<template>
    <span
        v-if="hasAccess"
        class="inline-flex items-center rounded-full bg-neutral-900 px-2 py-0.5 text-xs font-medium text-white dark:bg-neutral-100 dark:text-neutral-900"
    >
        {{ t('agents.chatters.access.granted') }}
    </span>
    <span v-else class="text-muted-foreground text-xs">{{ t('agents.chatters.access.no_access') }}</span>
</template>
