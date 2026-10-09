/**
 * Badge style for a team role code, so the owner and admin roles stand out
 * from custom roles wherever a role is shown on the team page.
 */
export type RoleBadgeVariant = 'default' | 'secondary' | 'outline';

export function roleBadgeVariant(role: string | null): RoleBadgeVariant {
    switch (role) {
        case 'owner':
            return 'default';
        case 'admin':
            return 'secondary';
        default:
            return 'outline';
    }
}
