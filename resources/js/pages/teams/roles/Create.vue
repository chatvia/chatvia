<script setup lang="ts">
// "Create role" page: an empty role form that is POSTed to the team.
import { Head, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

import AppLayout from '@/layouts/AppLayout.vue';
import { createRole } from '@/routes/teams';
import { type BreadcrumbItem } from '@/types';
import RoleForm from './components/RoleForm.vue';

// Use generated TypeScript types from DTOs
type TeamData = Domain.Membership.Team.Data.Resources.Team.TeamData;
type TeamAgentData = Domain.Membership.Team.Data.Resources.Team.TeamAgentData;
type PermissionMetaData = Domain.Membership.Team.Data.Resources.Permissions.PermissionMetaData;
type PermissionGroupData = Domain.Membership.Team.Data.Resources.Permissions.PermissionGroupData;
type PermissionScopeData = Domain.Membership.Team.Data.Resources.Permissions.PermissionScopeData;

interface Props {
    team: TeamData;
    agents: TeamAgentData[];
    permissionsConfig: Record<string, PermissionMetaData>;
    permissionGroups: Record<string, PermissionGroupData>;
    permissionScopes: Record<string, PermissionScopeData>;
}

const props = defineProps<Props>();

const { t } = useI18n();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: t('teams.breadcrumb'),
        href: '/my/teams',
    },
    {
        title: props.team.name,
        href: `/my/teams/${props.team.external_id}`,
    },
    {
        title: t('teams.common.create_role'),
        href: `/my/teams/${props.team.external_id}/roles/create`,
    },
];

const form = useForm({
    name: '',
    permissions: [] as string[],
    all_agents: true,
    agent_ids: [] as string[],
});

const submit = () => {
    form.post(createRole.url(props.team.external_id));
};
</script>

<template>
    <Head :title="t('teams.common.create_role')" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="h-full overflow-auto">
            <div class="container mx-auto max-w-3xl px-4 py-8">
                <div class="mb-6">
                    <h1 class="text-2xl font-bold tracking-tight">{{ t('teams.common.create_role') }}</h1>
                    <p class="text-muted-foreground">
                        {{ t('teams.roles.create.subtitle') }} <span class="font-medium">{{ team.name }}</span>
                    </p>
                </div>

                <RoleForm
                    v-model:name="form.name"
                    v-model:all-agents="form.all_agents"
                    v-model:agent-ids="form.agent_ids"
                    v-model:permissions="form.permissions"
                    :team="team"
                    :agents="agents"
                    :permissions-config="permissionsConfig"
                    :permission-groups="permissionGroups"
                    :permission-scopes="permissionScopes"
                    :errors="form.errors"
                    :processing="form.processing"
                    :submit-label="t('teams.common.create_role')"
                    autofocus-name
                    @submit="submit"
                />
            </div>
        </div>
    </AppLayout>
</template>
