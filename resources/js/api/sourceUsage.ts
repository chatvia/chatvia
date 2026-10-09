/**
 * Which knowledge sources, functions and MCP tools an agent used, how often
 * and when last. Feeds the agent's "Source usage" analytics page.
 */
import { get } from './http';

export interface KnowledgeUsageItem {
    name: string;
    sub_kind: string;
    mime_type: string | null;
    count: number;
    last_used_at: string | null;
}

export interface FunctionUsageItem {
    name: string;
    description: string;
    count: number;
    last_used_at: string | null;
}

export interface McpToolUsageItem {
    tool_name: string;
    tool_description: string | null;
    server_name: string;
    server_slug: string;
    connector_slug: string | null;
    count: number;
    last_used_at: string | null;
}

export interface SourceUsage {
    knowledge?: KnowledgeUsageItem[];
    functions?: FunctionUsageItem[];
    mcp_tools?: McpToolUsageItem[];
}

export function fetchSourceUsage(agentId: string): Promise<SourceUsage> {
    return get<SourceUsage>(route('agents.analytics.sources.data', agentId));
}
