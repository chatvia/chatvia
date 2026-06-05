<script setup lang="ts">
import DataExport from '@/components/DataExport.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';

interface ActiveExport {
    id: number;
    status: 'pending' | 'processing' | 'ready' | 'failed' | 'expired';
    file_size: number | null;
    expires_at: string | null;
    error_message: string | null;
    created_at: string | null;
    download_url: string | null;
}

interface Props {
    activeExport: ActiveExport | null;
}

defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Download my data',
        href: '/settings/data-export',
    },
];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Download my data" />

        <SettingsLayout>
            <DataExport :export="activeExport" />
        </SettingsLayout>
    </AppLayout>
</template>
