<script setup lang="ts">
import AppContent from '@/dashboard/components/AppContent.vue';
import AppHeader from '@/dashboard/components/AppHeader.vue';
import AppShell from '@/dashboard/components/AppShell.vue';
import type { BreadcrumbItemType } from '@/shared/types';

interface Props {
    breadcrumbs?: BreadcrumbItemType[];
}

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});
</script>

<template>
    <AppShell class="flex-col">
        <AppHeader :breadcrumbs="breadcrumbs" />
        <AppContent>
            <slot />
        </AppContent>
    </AppShell>
</template>
