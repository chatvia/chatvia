/**
 * The Evals page's JSON calls. Cases and runs go through Inertia forms; this
 * holds the calls that answer with data the page shows without a reload.
 */
import type { KnowledgeQuestion } from '@/dashboard/agents/pages/Evals/types';
import { generate } from '@/routes/agents/evals/cases';
import { post } from '@/shared/api/http';

/**
 * Asks the AI to write `count` eval questions from a random sample of the
 * agent's knowledge. Each comes back tied to the chunk it is answered by, in
 * the shape the "From knowledge" picker lists.
 */
export function generateKnowledgeQuestions(agentId: string, count: number): Promise<{ questions: KnowledgeQuestion[] }> {
    return post<{ questions: KnowledgeQuestion[] }>(generate.url(agentId), { count });
}
