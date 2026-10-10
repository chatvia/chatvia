<script setup lang="ts">
// "Edit role" page: the role form pre-filled from the role and PUT back to it.
import { Head, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

import AppLayout from '@/layouts/AppLayout.vue';
import { updateRole } from '@/routes/teams';
import { type BreadcrumbItem } from '@/types';
import RoleForm from './components/RoleForm.vue';

// Use generated TypeScript types from DTOs
type TeamData = Domain.Membership.Team.Data.Resources.Team.TeamData;
type TeamAgentData = Domain.Membership.Team.Data.Resources.Team.TeamAgentData;
type TeamRoleData = Domain.Membership.Team.Data.Resources.Roles.TeamRoleData;
type PermissionMetaData = Domain.Membership.Team.Data.Resources.Permissions.PermissionMetaData;
type PermissionGroupData = Domain.Membership.Team.Data.Resources.Permissions.PermissionGroupData;
type PermissionScopeData = Domain.Membership.Team.Data.Resources.Permissions.PermissionScopeData;

interface Props {
    team: TeamData;
    role: TeamRoleData;
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
        title: t('teams.roles.edit.breadcrumb', { name: props.role.name }),
        href: `/my/teams/${props.team.external_id}/roles/${props.role.id}/edit`,
    },
];

const form = useForm({
    name: props.role.name,
    permissions: [...props.role.permissions],
    all_agents: props.role.all_agents,
    agent_ids: [...(props.role.agent_ids || [])],
});

const submit = () => {
    form.put(updateRole.url({ team: props.team.external_id, roleId: props.role.id }));
};
</script>

<template>
    <Head :title="t('teams.roles.edit.title', { name: role.name })" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="h-full overflow-auto">
            <div class="container mx-auto max-w-3xl px-4 py-8">
                <div class="mb-6">
                    <h1 class="text-2xl font-bold tracking-tight">{{ t('teams.roles.edit.heading') }}</h1>
                    <p class="text-muted-foreground">
                        {{ t('teams.roles.edit.subtitle_prefix') }} <span class="font-medium">{{ role.name }}</span>
                        {{ t('teams.roles.edit.subtitle_middle') }}
                        <span class="font-medium">{{ team.name }}</span>
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
                    :submit-label="t('teams.roles.edit.save')"
                    @submit="submit"
                />
            </div>
        </div>
    </AppLayout>
</template>
