<?php

namespace Domain\Evaluation\Actions;

use Domain\Agent\Models\Agent;
use Domain\Evaluation\Models\EvalCase;
use Domain\Knowledge\Ingestion\Models\Chunk;
use Domain\Knowledge\Source\QA\Models\AgentQuestionAnswer;

/**
 * Candidate questions for the "From knowledge" picker: QA pairs first (they
 * carry a human-written ground-truth answer), then questions mined from the
 * agent's chunks. Capped at 200, deduped case-insensitively; questions already
 * used as a case input are flagged so the UI disables them.
 *
 * Mining decrypts up to ~200 chunk rows, so callers fetch this lazily (the
 * Evals page wires it behind an `Inertia::optional`, not first load).
 */
class ListKnowledgeQuestionCandidatesAction
{
    private const array SOURCE_LABELS = [
        'source_file' => 'File',
        'source_website' => 'Website',
        'source_text' => 'Text',
        'source_youtube_video' => 'YouTube video',
    ];

    /**
     * @return list<array{key: string, type: string, id: int, question: string, source: string, already_added: bool}>
     */
    public function execute(Agent $agent): array
    {
        $seen = [];
        $candidates = [];

        $qaPairs = AgentQuestionAnswer::query()
            ->where('agent_id', $agent->id)
            ->orderByDesc('id')
            ->limit(100)
            ->get(['id', 'title', 'questions']);

        foreach ($qaPairs as $qa) {
            foreach (preg_split('/\r\n|\r|\n/', $qa->questions) ?: [] as $question) {
                $question = trim($question);

                if ($question === '' || mb_strlen($question) > 255 || isset($seen[mb_strtolower($question)])) {
                    continue;
                }

                $seen[mb_strtolower($question)] = true;
                $candidates[] = ['type' => 'qa', 'id' => $qa->id, 'question' => $question, 'source' => $qa->title];
            }
        }

        foreach (Chunk::query()->questionCandidatesForAgent($agent->id) as $mined) {
            if (isset($seen[mb_strtolower($mined['question'])])) {
                continue;
            }

            $seen[mb_strtolower($mined['question'])] = true;
            $candidates[] = [
                'type' => 'chunk',
                'id' => $mined['chunk_id'],
                'question' => $mined['question'],
                'source' => self::SOURCE_LABELS[$mined['source_type']] ?? ucfirst(str_replace('_', ' ', $mined['source_type'])),
            ];
        }

        $existing = EvalCase::query()->forAgent($agent->id)->pluck('input')
            ->mapWithKeys(fn (string $input): array => [mb_strtolower(trim($input)) => true]);

        return collect($candidates)
            ->take(200)
            ->values()
            ->map(fn (array $candidate, int $i): array => [
                'key' => "{$candidate['type']}:{$candidate['id']}:{$i}",
                ...$candidate,
                'already_added' => $existing->has(mb_strtolower($candidate['question'])),
            ])
            ->all();
    }
}
