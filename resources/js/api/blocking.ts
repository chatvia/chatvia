/**
 * Blocking API: who cannot chat with an agent (the workspace's blocked
 * visitors and the agent's switched-off chatters), loaded when a blocked list
 * opens, and removing a visitor block.
 */
import { list } from '@/routes/agents/security/blocked';
import { destroy } from '@/routes/security/blocked-visitors';
import { del, get } from './http';

/** A visitor block in force. Never carries the visitor's network address. */
export interface BlockedVisitor {
    external_id: string;
    // The first characters of the fingerprint, as the Activity log shows them.
    visitor: string;
    browser: string | null;
    os: string | null;
    country: string | null;
    agent_name: string | null;
    reason: string | null;
    blocked_by: string | null;
    created_at: string | null;
    // Null: until someone unblocks the visitor.
    expires_at: string | null;
}

/** A chatter with access to the agent whose account is switched off. */
export interface BlockedChatter {
    external_id: string;
    name: string;
    reason: string | null;
    deactivated_at: string | null;
}

export interface AgentBlocks {
    visitors: BlockedVisitor[];
    chatters: BlockedChatter[];
}

export function listAgentBlocks(agentId: string): Promise<AgentBlocks> {
    return get<AgentBlocks>(list.url(agentId));
}

export function unblockVisitor(blockId: string): Promise<void> {
    return del(destroy.url(blockId));
}
