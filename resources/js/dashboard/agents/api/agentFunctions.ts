/**
 * The agent's Functions page: the workspace's webhook functions with their
 * attach state for this agent, and attaching / detaching one. Functions
 * themselves are created in Workspace → Integrations → Functions.
 */
import functions from '@/routes/agents/sources/functions';
import { del, get, post } from '@/shared/api/http';

export type AgentFunctionRow = Domain.Integration.Function.Data.Resources.AgentFunctionRowData;

type AgentFunctionsList = Domain.Integration.Function.Data.Resources.AgentFunctionsListResourceData;

/** Every function of the workspace, each flagged with whether this agent uses it. */
export async function fetchAgentFunctions(agentId: string): Promise<AgentFunctionRow[]> {
    const response = await get<AgentFunctionsList>(functions.data.url(agentId));

    return response.data;
}

/** Let this agent call the function. */
export async function attachFunction(agentId: string, functionId: string): Promise<void> {
    await post(functions.attach.url({ agent: agentId, function: functionId }));
}

/** Stop this agent from calling the function; the function itself stays. */
export async function detachFunction(agentId: string, functionId: string): Promise<void> {
    await del(functions.detach.url({ agent: agentId, function: functionId }));
}
