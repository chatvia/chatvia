<script setup lang="ts">
// The pill that shows a chatter's account state: deactivated (red, only when
// the caller passes it), pending invitation (amber) or active (green). Labels
// come from the caller because each screen has its own translations.
withDefaults(
    defineProps<{
        status: string;
        pendingLabel: string;
        activeLabel: string;
        deactivated?: boolean;
        deactivatedLabel?: string;
    }>(),
    { deactivated: false, deactivatedLabel: '' },
);
</script>

<template>
    <span
        v-if="deactivated"
        class="inline-flex items-center rounded-full border border-red-200 bg-red-50 px-2 py-0.5 text-xs font-medium text-red-900 dark:border-red-900/50 dark:bg-red-950/30 dark:text-red-100"
    >
        {{ deactivatedLabel }}
    </span>
    <span
        v-else-if="status === 'pending'"
        class="inline-flex items-center rounded-full border border-amber-200 bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-900 dark:border-amber-900/50 dark:bg-amber-950/30 dark:text-amber-100"
    >
        {{ pendingLabel }}
    </span>
    <span
        v-else
        class="inline-flex items-center rounded-full border border-green-200 bg-green-50 px-2 py-0.5 text-xs font-medium text-green-900 dark:border-green-900/50 dark:bg-green-950/30 dark:text-green-100"
    >
        {{ activeLabel }}
    </span>
</template>
