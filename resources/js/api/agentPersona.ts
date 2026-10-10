/**
 * The calls behind an agent's persona editors: the workspace's saved library
 * prompts, saving the agent's instructions (inline text or a library prompt),
 * and suggesting a free subdomain for the agent's hosted chat.
 */
import { generateRandomSubdomain as randomSubdomainRoute } from '@/routes';
import { update as updateAgentRoute } from '@/routes/agents';
import { list as libraryPromptsRoute } from '@/routes/library/prompts';
import { get, patch } from './http';

export type LibraryPrompt = Domain.Agent.Prompt.Data.Resources.AgentPromptPickerData;

/**
 * What the agent answers with: its own inline instructions, or a saved library
 * prompt by id. The server keeps the two mutually exclusive.
 */
export type AgentInstructionsUpdate = { system_instructions: string } | { prompt_id: string | null };

/** The workspace's saved prompts, newest first. */
export async function fetchLibraryPrompts(): Promise<LibraryPrompt[]> {
    const list = await get<Domain.Agent.Prompt.Data.Resources.AgentPromptPickerListData>(libraryPromptsRoute.url());

    return list.data ?? [];
}

/** Saves the agent's instructions. */
export function updateAgentInstructions(agentId: string, update: AgentInstructionsUpdate): Promise<void> {
    return patch(updateAgentRoute.url(agentId), update);
}

/** A free, randomly generated subdomain name (without the base domain). */
export async function generateRandomSubdomain(): Promise<string> {
    const { name } = await get<{ name: string }>(randomSubdomainRoute.url());

    return name;
}
