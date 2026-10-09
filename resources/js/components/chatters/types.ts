/**
 * Types shared by the chatter screens: the agent → Chatters tab and the
 * workspace People → Chatters page show the same people from two different
 * endpoints, so the shared pieces only rely on the fields both rows carry.
 */
import type { WorkspaceChatterRow } from '@/api/chatters';
import type { Page } from '@inertiajs/core';

/** The fields every chatter row has, whichever list it came from. */
export type ChatterSummary = Pick<WorkspaceChatterRow, 'external_id' | 'name' | 'email' | 'avatar' | 'status' | 'created_at'>;

/**
 * Texts for the chatter sheet header. Each screen passes its own translations
 * (the two screens have separate i18n keys that differ in some languages).
 */
export interface ChatterSheetHeaderLabels {
    pending: string;
    active: string;
    noEmail: string;
    description: string;
    id: string;
    copyId: string;
    joined: string;
}

/** A protected agent a new chatter can be invited to (workspace People page). */
export interface InvitableAgent {
    external_id: string;
    name: string;
}

/** The invitation the server flashes after an invite, so the owner can copy the sign-up link. */
export interface ChatterInvitation {
    email: string;
    accept_url: string;
    expires_at: string;
}

/** The invitation flashed by the last invite visit, or null when the server did not send one. */
export function flashedInvitation(page: Page): ChatterInvitation | null {
    const flash = (page.props as { flash?: { invitation?: ChatterInvitation | null } }).flash;

    return flash?.invitation ?? null;
}
