/**
 * Permission checks for the signed-in user in the current team, read from the
 * shared Inertia props. The team owner (or a user with no current team, i.e.
 * a standalone workspace) passes every check; everyone else needs the code in
 * their team's permission list.
 */
import type { SharedData } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

export function useTeamPermissions() {
    const page = usePage<SharedData>();

    const isOwner = computed(() => {
        const currentTeam = page.props.currentTeam;
        return !currentTeam || currentTeam.is_owner;
    });

    const hasPermission = (permission: string): boolean => {
        if (isOwner.value) return true;

        const currentTeam = page.props.currentTeam;
        if (!currentTeam) return false;

        const teamPermissions = page.props.permissions[currentTeam.id] || [];
        return teamPermissions.includes(permission);
    };

    const hasAnyPermission = (permissions: string[]): boolean => {
        if (isOwner.value) return true;
        return permissions.some((p) => hasPermission(p));
    };

    const hasAllPermissions = (permissions: string[]): boolean => {
        if (isOwner.value) return true;
        return permissions.every((p) => hasPermission(p));
    };

    return {
        isOwner,
        hasPermission,
        hasAnyPermission,
        hasAllPermissions,
    };
}
