<script setup lang="ts">
import { Toaster } from '@/shared/ui/sonner';
import AppLayout from '@/dashboard/layouts/AppHeaderLayout.vue';
import type { BreadcrumbItemType } from '@/shared/types';
import 'vue-sonner/style.css';

interface Props {
    breadcrumbs?: BreadcrumbItemType[];
    title?: string;
}

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
    title: '',
});
</script>

<template>
    <Toaster />
    <AppLayout :breadcrumbs="breadcrumbs">
        <slot />
    </AppLayout>
</template>
