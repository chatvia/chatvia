/**
 * Label, description and prerequisites of a team permission code, read from
 * the permission catalog the server sends with the teams pages. Labels and
 * descriptions are translated when a translation exists, else the server's
 * English text is shown.
 */
import { toValue, type MaybeRefOrGetter } from 'vue';

import { useTeamPermissionsI18n } from '@/composables/useTeamPermissionsI18n';

type PermissionMetaData = Domain.Membership.Team.Data.Resources.Permissions.PermissionMetaData;

export function usePermissionCatalog(catalog: MaybeRefOrGetter<Record<string, PermissionMetaData>>) {
    const { permissionLabel, permissionDescription } = useTeamPermissionsI18n();

    const label = (code: string): string => permissionLabel(code, toValue(catalog)[code]?.label || code);

    const description = (code: string): string => permissionDescription(code, toValue(catalog)[code]?.description || '');

    /** The codes this permission needs to work (direct prerequisites only). */
    const requires = (code: string): string[] => toValue(catalog)[code]?.requires || [];

    return { label, description, requires };
}
