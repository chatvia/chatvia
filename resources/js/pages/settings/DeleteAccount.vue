<script setup lang="ts">
import DeleteUser from '@/components/DeleteUser.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';

interface Props {
    agents: { name: string }[];
    hasSubscription: boolean;
    deletionConfirmed: boolean;
}

defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Delete account',
        href: '/settings/delete-account',
    },
];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Delete account" />

        <SettingsLayout>
            <DeleteUser :agents="agents" :has-subscription="hasSubscription" :confirmed="deletionConfirmed" />
        </SettingsLayout>
    </AppLayout>
</template>
