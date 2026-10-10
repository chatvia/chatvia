/**
 * The agent's Forms page. Saving and deleting forms are Inertia visits; the
 * only JSON call is the AI rewrite of a form's "when to show it" note, which
 * returns a suggestion for the editor and saves nothing.
 */
import { rewriteDescription } from '@/routes/agents/forms';
import { post } from './http';

export interface RewriteFormDescriptionPayload {
    agent_instructions: string | null;
    name: string;
    form_type: string;
}

/** A sharper, intent-based version of the form's "when to show it" note. */
export async function rewriteFormDescription(agentId: string, payload: RewriteFormDescriptionPayload): Promise<string> {
    const response = await post<{ agent_instructions: string }>(rewriteDescription.url(agentId), payload);

    return response.agent_instructions;
}
