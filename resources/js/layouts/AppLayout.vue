<script setup lang="ts">
import AppLayout from '@/layouts/app/AppHeaderLayout.vue';
import type { BreadcrumbItemType } from '@/types';
import { Toaster } from "@/components/ui/sonner"
import 'vue-sonner/style.css'

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
