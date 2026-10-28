<script setup lang="ts">
import DataExport from '@/dashboard/account/components/DataExport.vue';
import AppLayout from '@/dashboard/layouts/AppLayout.vue';
import AccountLayout from '@/dashboard/account/layouts/Layout.vue';
import { type BreadcrumbItem } from '@/shared/types';
import { Head } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

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
        title: t('account.data_export.title'),
        href: '/my/account/data-export',
    },
];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="t('account.data_export.title')" />

        <AccountLayout>
            <DataExport :export="activeExport" />
        </AccountLayout>
    </AppLayout>
</template>
