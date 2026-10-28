<script setup lang="ts">
import DeleteUser from '@/dashboard/account/components/DeleteUser.vue';
import AppLayout from '@/dashboard/layouts/AppLayout.vue';
import AccountLayout from '@/dashboard/account/layouts/Layout.vue';
import { type BreadcrumbItem } from '@/shared/types';
import { Head } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

interface Props {
    agents: { name: string }[];
    deletionConfirmed: boolean;
}

defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: t('account.delete.title'),
        href: '/my/account/delete-account',
    },
];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="t('account.delete.title')" />

        <AccountLayout>
            <DeleteUser :agents="agents" :confirmed="deletionConfirmed" />
        </AccountLayout>
    </AppLayout>
</template>
