<script setup lang="ts">
import DeleteUser from '@/components/DeleteUser.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import AccountLayout from '@/layouts/account/Layout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

interface Props {
    agents: { name: string }[];
    hasSubscription: boolean;
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
            <DeleteUser :agents="agents" :has-subscription="hasSubscription" :confirmed="deletionConfirmed" />
        </AccountLayout>
    </AppLayout>
</template>
