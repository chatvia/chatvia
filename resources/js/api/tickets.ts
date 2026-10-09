/**
 * The current user's support tickets (Support page) and the ticket shapes
 * shared with the ticket detail page.
 */
import { get } from './http';

export interface TicketReply {
    id: number;
    user_id: number | null;
    message: string;
    created_at: string;
    type: 'user' | 'staff';
}

export interface Ticket {
    id: number;
    user_id: number;
    assigned_to: number | null;
    title: string;
    message: string;
    status: string;
    priority: 'low' | 'medium' | 'high' | string;
    resolved_at: string | null;
    created_at: string;
    updated_at: string;
    replies: TicketReply[];
}

/** Newest first, with their replies. */
export function fetchTickets(): Promise<Ticket[]> {
    return get<Ticket[]>(route('tickets.index'));
}
