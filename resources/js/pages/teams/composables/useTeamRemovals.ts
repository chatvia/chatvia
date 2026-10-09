/**
 * The three destructive actions on the team page, each driven by a "target"
 * ref: set it to open the confirmation, clear it to close. Removing a member
 * only takes them off this team; deleting a teammate from the workspace erases
 * their whole account; deleting a role removes it from the team.
 */
import { useForm } from '@inertiajs/vue3';
import { ref, toValue, type MaybeRefOrGetter } from 'vue';

type TeamData = Domain.Membership.Team.Data.Resources.Team.TeamData;
type TeamMemberData = Domain.Membership.Team.Data.Resources.Members.TeamMemberData;
type TeamRoleData = Domain.Membership.Team.Data.Resources.Roles.TeamRoleData;

export function useTeamRemovals(team: MaybeRefOrGetter<TeamData>) {
    const memberToRemove = ref<TeamMemberData | null>(null);
    const removeMemberForm = useForm({});

    const removeMember = () => {
        if (!memberToRemove.value) return;

        removeMemberForm.delete(route('teams.remove-member', { team: toValue(team).external_id, member: memberToRemove.value.id }), {
            onSuccess: () => {
                memberToRemove.value = null;
            },
        });
    };

    // Workspace-level, so it is not scoped to this team.
    const memberToDeleteFromWorkspace = ref<TeamMemberData | null>(null);
    const removeFromWorkspaceForm = useForm({});

    const removeFromWorkspace = () => {
        if (!memberToDeleteFromWorkspace.value) return;

        removeFromWorkspaceForm.delete(route('teammates.remove', { member: memberToDeleteFromWorkspace.value.id }), {
            onSuccess: () => {
                memberToDeleteFromWorkspace.value = null;
            },
        });
    };

    const roleToDelete = ref<TeamRoleData | null>(null);
    const deleteRoleForm = useForm({});

    const deleteRole = () => {
        if (!roleToDelete.value) return;

        deleteRoleForm.delete(route('teams.delete-role', { team: toValue(team).external_id, roleId: roleToDelete.value.id }), {
            onSuccess: () => {
                roleToDelete.value = null;
            },
        });
    };

    return {
        memberToRemove,
        removeMemberForm,
        removeMember,
        memberToDeleteFromWorkspace,
        removeFromWorkspaceForm,
        removeFromWorkspace,
        roleToDelete,
        deleteRoleForm,
        deleteRole,
    };
}
