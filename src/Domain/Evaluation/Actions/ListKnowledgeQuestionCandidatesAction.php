<?php

namespace Domain\Evaluation\Actions;

use Domain\Agent\Models\Agent;
use Domain\Evaluation\Data\KnowledgeQuestionCandidateData;
use Domain\Evaluation\Enums\EvalQuestionSource;
use Domain\Evaluation\Models\EvalCase;
use Domain\Knowledge\Ingestion\Models\Chunk;
use Domain\Knowledge\Source\Qa\Models\AgentQuestionAnswer;
use Illuminate\Support\Str;

/**
 * Candidate questions for the "From knowledge" picker: QA pairs first (they
 * carry a human-written ground-truth answer), then questions mined from the
 * agent's chunks. Capped at {@see self::MAX_CANDIDATES}, deduped
 * case-insensitively; questions already used as a case input are flagged so
 * the UI disables them.
 *
 * Mining decrypts up to ~200 chunk rows, so callers fetch this lazily (the
 * Evals page wires it behind an `Inertia::optional`, not first load).
 */
class ListKnowledgeQuestionCandidatesAction
{
    private const int MAX_CANDIDATES = 200;

    private const int MAX_QA_PAIRS = 100;

    private const int MAX_QUESTION_LENGTH = 255;

    private const array SOURCE_LABELS = [
        'source_file' => 'File',
        'source_website' => 'Website',
        'source_text' => 'Text',
        'source_youtube_video' => 'YouTube video',
    ];

    /**
     * @return list<KnowledgeQuestionCandidateData>
     */
    public function execute(Agent $agent): array
    {
        $seen = [];
        $candidates = [];

        foreach ([...$this->qaQuestions($agent), ...$this->minedQuestions($agent)] as [$type, $id, $question, $source]) {
            $normalized = Str::lower($question);

            if (isset($seen[$normalized])) {
                continue;
            }

            $seen[$normalized] = true;
            $candidates[] = [$type, $id, $question, $source];
        }

        $existing = EvalCase::query()->forAgent($agent->id)->pluck('input')
            ->mapWithKeys(fn (string $input): array => [Str::lower(Str::trim($input)) => true]);

        $result = [];

        foreach (array_slice($candidates, 0, self::MAX_CANDIDATES) as $i => [$type, $id, $question, $source]) {
            $result[] = new KnowledgeQuestionCandidateData(
                key: "{$type->value}:{$id}:{$i}",
                type: $type,
                id: $id,
                question: $question,
                source: $source,
                already_added: $existing->has(Str::lower($question)),
            );
        }

        return $result;
    }

    /**
     * Every question line of the agent's latest QA pairs.
     *
     * @return list<array{0: EvalQuestionSource, 1: int, 2: string, 3: string}>
     */
    private function qaQuestions(Agent $agent): array
    {
        $questions = [];

        $qaPairs = AgentQuestionAnswer::query()
            ->where('agent_id', $agent->id)
            ->orderByDesc('id')
            ->limit(self::MAX_QA_PAIRS)
            ->get(['id', 'title', 'questions']);

        foreach ($qaPairs as $qa) {
            foreach (Str::of($qa->questions)->split('/\r\n|\r|\n/') as $line) {
                $question = Str::trim($line);

                if ($question !== '' && Str::length($question) <= self::MAX_QUESTION_LENGTH) {
                    $questions[] = [EvalQuestionSource::Qa, $qa->id, $question, $qa->title];
                }
            }
        }

        return $questions;
    }

    /**
     * Questions mined from the agent's knowledge chunks.
     *
     * @return list<array{0: EvalQuestionSource, 1: int, 2: string, 3: string}>
     */
    private function minedQuestions(Agent $agent): array
    {
        $questions = [];

        foreach (Chunk::query()->questionCandidatesForAgent($agent) as $mined) {
            $questions[] = [
                EvalQuestionSource::Chunk,
                (int) $mined['chunk_id'],
                (string) $mined['question'],
                self::SOURCE_LABELS[$mined['source_type']] ?? Str::ucfirst(Str::replace('_', ' ', $mined['source_type'])),
            ];
        }

        return $questions;
    }
}
