<script setup lang="ts">
// A single team: header (rename, copy ID), roles with their permissions,
// members, pending invitations, and the dialogs those sections open. Each
// section is its own component; this page wires them to the shared dialogs.
import { Head, Link, usePage } from '@inertiajs/vue3';
import { ChevronLeft } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';

import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type SharedData } from '@/types';
import EditMemberRoleDialog from './components/EditMemberRoleDialog.vue';
import InviteMemberDialog from './components/InviteMemberDialog.vue';
import PendingInvitationsCard from './components/PendingInvitationsCard.vue';
import RoleMembersDialog from './components/RoleMembersDialog.vue';
import TeamConfirmDialog from './components/TeamConfirmDialog.vue';
import TeamHeaderCard from './components/TeamHeaderCard.vue';
import TeamMembersCard from './components/TeamMembersCard.vue';
import TeamRolesCard from './components/TeamRolesCard.vue';
import { useTeamRemovals } from './composables/useTeamRemovals';

// Use generated TypeScript types from DTOs
type TeamData = Domain.Membership.Team.Data.Resources.Team.TeamData;
type TeamMemberData = Domain.Membership.Team.Data.Resources.Members.TeamMemberData;
type TeamRoleData = Domain.Membership.Team.Data.Resources.Roles.TeamRoleData;
type TeamAgentData = Domain.Membership.Team.Data.Resources.Team.TeamAgentData;
type TeamInvitationData = Domain.Membership.Team.Data.Resources.Members.TeamInvitationData;
type ManagedUserData = Domain.Membership.Team.Data.Resources.Members.ManagedUserData;
type PermissionMetaData = Domain.Membership.Team.Data.Resources.Permissions.PermissionMetaData;
type PermissionGroupData = Domain.Membership.Team.Data.Resources.Permissions.PermissionGroupData;
type RoleConfigData = Domain.Membership.Team.Data.Resources.Roles.RoleConfigData;

interface Props {
    team: TeamData;
    members: TeamMemberData[];
    roles: TeamRoleData[];
    agents: TeamAgentData[];
    pendingInvitations: TeamInvitationData[];
    managedUsers: ManagedUserData[];
    isOwner: boolean;
    userRole: string;
    availableRoles: Record<string, RoleConfigData>;
    permissionsConfig: Record<string, PermissionMetaData>;
    permissionGroups: Record<string, PermissionGroupData>;
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
];

const page = usePage<SharedData>();
const currentUser = page.props.auth.user;

const canManageMembers = computed(() => props.isOwner);

// The owner role is implicit: never listed, assigned or invited to.
const nonOwnerRoles = computed(() => props.roles.filter((r) => r.code !== 'owner'));

const inviteDialog = ref<InstanceType<typeof InviteMemberDialog> | null>(null);

const viewingRoleMembers = ref<TeamRoleData | null>(null);
const isRoleMembersDialogOpen = ref(false);

const openRoleMembersDialog = (role: TeamRoleData) => {
    viewingRoleMembers.value = role;
    isRoleMembersDialogOpen.value = true;
};

const memberToEdit = ref<TeamMemberData | null>(null);

const {
    memberToRemove,
    removeMemberForm,
    removeMember,
    memberToDeleteFromWorkspace,
    removeFromWorkspaceForm,
    removeFromWorkspace,
    roleToDelete,
    deleteRoleForm,
    deleteRole,
} = useTeamRemovals(() => props.team);
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="t('teams.show.title', { name: team.name })" />

        <div class="container mx-auto max-w-5xl py-8">
            <!-- Header with back button -->
            <div class="mb-8">
                <Button variant="ghost" size="sm" as-child class="-ms-2 mb-4 gap-2">
                    <Link :href="route('teams.index')">
                        <ChevronLeft class="size-4" />
                        {{ t('teams.show.back') }}
                    </Link>
                </Button>

                <TeamHeaderCard :team="team" :is-owner="isOwner" />
            </div>

            <!-- Cards Section -->
            <div class="space-y-6">
                <TeamRolesCard
                    :team="team"
                    :roles="nonOwnerRoles"
                    :members="members"
                    :agents="agents"
                    :permissions-config="permissionsConfig"
                    :permission-groups="permissionGroups"
                    :is-owner="isOwner"
                    :can-manage-members="canManageMembers"
                    @add-member="(role) => inviteDialog?.openForRole(role)"
                    @view-members="openRoleMembersDialog"
                    @delete-role="(role) => (roleToDelete = role)"
                />

                <TeamMembersCard
                    :members="members"
                    :current-user-id="currentUser.id"
                    :can-manage-members="canManageMembers"
                    @change-role="(member) => (memberToEdit = member)"
                    @remove="(member) => (memberToRemove = member)"
                    @delete-from-workspace="(member) => (memberToDeleteFromWorkspace = member)"
                />

                <PendingInvitationsCard
                    v-if="pendingInvitations.length > 0"
                    :team="team"
                    :invitations="pendingInvitations"
                    :roles="roles"
                    :can-manage-members="canManageMembers"
                />
            </div>

            <InviteMemberDialog ref="inviteDialog" :team="team" :roles="roles" :managed-users="managedUsers" />

            <RoleMembersDialog
                v-model:open="isRoleMembersDialogOpen"
                :role="viewingRoleMembers"
                :members="members"
                @remove="(member) => (memberToRemove = member)"
            />

            <EditMemberRoleDialog v-model:member="memberToEdit" :team="team" :roles="nonOwnerRoles" />

            <TeamConfirmDialog
                :open="!!memberToRemove"
                :title="t('teams.show.members.remove_title')"
                :description="t('teams.show.members.remove_description', { name: memberToRemove?.name })"
                :confirm-label="t('teams.show.members.remove_confirm')"
                :processing="removeMemberForm.processing"
                @confirm="removeMember"
                @close="memberToRemove = null"
            />

            <!-- Deletes the teammate's whole account, not just their place on this team. -->
            <TeamConfirmDialog
                :open="!!memberToDeleteFromWorkspace"
                :title="t('teams.show.members.delete_from_workspace_title')"
                :description="t('teams.show.members.delete_from_workspace_description', { name: memberToDeleteFromWorkspace?.name })"
                :confirm-label="t('teams.show.members.delete_from_workspace_confirm')"
                :processing="removeFromWorkspaceForm.processing"
                @confirm="removeFromWorkspace"
                @close="memberToDeleteFromWorkspace = null"
            />

            <TeamConfirmDialog
                :open="!!roleToDelete"
                :title="t('teams.show.roles.delete_role')"
                :description="t('teams.show.roles.delete_description', { name: roleToDelete?.name })"
                :confirm-label="t('teams.show.roles.delete_role')"
                :processing="deleteRoleForm.processing"
                @confirm="deleteRole"
                @close="roleToDelete = null"
            />
        </div>
    </AppLayout>
</template>
