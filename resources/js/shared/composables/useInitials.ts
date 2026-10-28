/**
 * Avatar initials from a display name: the first letter of the first and last
 * word ("Ada Lovelace" → "AL"), or one letter for a single-word name.
 */
export function getInitials(fullName?: string): string {
    if (!fullName) return '';

    const names = fullName.trim().split(' ');

    if (names.length === 0) return '';
    if (names.length === 1) return names[0].charAt(0).toUpperCase();

    return `${names[0].charAt(0)}${names[names.length - 1].charAt(0)}`.toUpperCase();
}

export function useInitials() {
    return { getInitials };
}
