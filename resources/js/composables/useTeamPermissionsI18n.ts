import { useI18n } from 'vue-i18n';

function toPermissionKey(code: string): string {
    return code.replace(/\./g, '_').replace(/-/g, '_');
}

export function useTeamPermissionsI18n() {
    const { t, te } = useI18n();

    const translate = (path: string, fallback: string): string => {
        return te(path) ? t(path) : fallback;
    };

    const scopeLabel = (key: string, fallback: string): string => translate(`teams.permissions_meta.scopes.${key}.label`, fallback);

    const scopeDescription = (key: string, fallback: string): string => translate(`teams.permissions_meta.scopes.${key}.description`, fallback);

    const groupLabel = (key: string, fallback: string): string => translate(`teams.permissions_meta.groups.${key}.label`, fallback);

    const groupDescription = (key: string, fallback: string): string => translate(`teams.permissions_meta.groups.${key}.description`, fallback);

    const permissionLabel = (code: string, fallback: string): string =>
        translate(`teams.permissions_meta.codes.${toPermissionKey(code)}.label`, fallback);

    const permissionDescription = (code: string, fallback: string): string =>
        translate(`teams.permissions_meta.codes.${toPermissionKey(code)}.description`, fallback);

    return {
        scopeLabel,
        scopeDescription,
        groupLabel,
        groupDescription,
        permissionLabel,
        permissionDescription,
    };
}
