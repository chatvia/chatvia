/**
 * Keeps a role's selected permissions consistent with the catalog's
 * `requires` links: ticking a permission also ticks everything it needs
 * (recursively), and unticking one also unticks everything that needs it, so
 * a role can never hold a permission without its prerequisites.
 */
import { toValue, type MaybeRefOrGetter, type Ref } from 'vue';

type PermissionMetaData = Domain.Membership.Team.Data.Resources.Permissions.PermissionMetaData;
type PermissionGroupData = Domain.Membership.Team.Data.Resources.Permissions.PermissionGroupData;

export function usePermissionDependencies(
    selected: Ref<string[]>,
    catalog: MaybeRefOrGetter<Record<string, PermissionMetaData>>,
    groups: MaybeRefOrGetter<Record<string, PermissionGroupData>>,
) {
    // Get all required permissions recursively
    const getRequiredPermissions = (permission: string): string[] => {
        const permData = toValue(catalog)[permission];
        if (!permData?.requires?.length) return [];

        const required: string[] = [];
        for (const req of permData.requires) {
            required.push(req);
            // Recursively get requirements of requirements
            required.push(...getRequiredPermissions(req));
        }
        return [...new Set(required)]; // Remove duplicates
    };

    // Get all permissions that depend on a given permission
    const getDependentPermissions = (permission: string): string[] => {
        const dependents: string[] = [];
        for (const [code, data] of Object.entries(toValue(catalog))) {
            if (data.requires?.includes(permission)) {
                dependents.push(code);
                // Recursively get permissions that depend on this one
                dependents.push(...getDependentPermissions(code));
            }
        }
        return [...new Set(dependents)];
    };

    const togglePermission = (permission: string, checked: boolean | 'indeterminate') => {
        const isChecked = checked === true;

        if (isChecked) {
            // Add the permission and all its requirements
            const toAdd = [permission, ...getRequiredPermissions(permission)];
            const newPerms = toAdd.filter((p) => !selected.value.includes(p));
            selected.value = [...selected.value, ...newPerms];
        } else {
            // Remove the permission and all permissions that depend on it
            const toRemove = [permission, ...getDependentPermissions(permission)];
            selected.value = selected.value.filter((p) => !toRemove.includes(p));
        }
    };

    const toggleGroupPermissions = (groupKey: string, checked: boolean | 'indeterminate') => {
        const isChecked = checked === true;
        const groupPerms = toValue(groups)[groupKey]?.permissions || [];

        if (isChecked) {
            // Add all permissions in group plus their requirements
            const toAdd: string[] = [];
            for (const perm of groupPerms) {
                toAdd.push(perm, ...getRequiredPermissions(perm));
            }
            const newPerms = [...new Set(toAdd)].filter((p) => !selected.value.includes(p));
            selected.value = [...selected.value, ...newPerms];
        } else {
            // Remove all group permissions and their dependents
            const toRemove: string[] = [];
            for (const perm of groupPerms) {
                toRemove.push(perm, ...getDependentPermissions(perm));
            }
            selected.value = selected.value.filter((p) => !toRemove.includes(p));
        }
    };

    return { togglePermission, toggleGroupPermissions };
}
