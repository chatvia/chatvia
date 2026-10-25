<?php

declare(strict_types=1);

namespace Domain\Evaluation\Services\Scorers;

use Domain\Evaluation\Data\AgentRunResultData;
use Domain\Evaluation\Data\ScoreResultData;
use Domain\Evaluation\Models\EvalCase;
use Domain\Llm\Data\Chat\ChatMessageData;
use Domain\Llm\Data\Chat\ChatRequestData;
use Domain\Llm\Data\Chat\ResponseSchemaData;
use Domain\Llm\Enums\AiCallTag;
use Domain\Llm\Enums\LlmTask;
use Domain\Llm\Services\StructuredCompletion;
use Domain\Llm\Services\TaskModels;
use Illuminate\Support\Str;
use Throwable;

/**
 * For an agent that cites its sources: does each passage the answer cites
 * really support the sentence it is cited for? The answer is cut into the
 * sentences that carry `[n]` markers; a marker for a block the agent was not
 * given counts as unsupported, and the judge model checks the rest in one
 * call. The score is the share of supported citations; the case passes at
 * `scorer_config.min_supported` (0.8 by default). An answer that cites
 * nothing fails: this case is about citing. The prompts live in
 * resources/prompts/evals.
 */
class CitationScorer implements ScorerInterface
{
    private const float DEFAULT_MIN_SUPPORTED = 0.8;

    /** A sentence ends at . ! ? or a line break, and keeps the markers after it. */
    private const string SENTENCE = '/[^.!?\n]+[.!?]*(?:\s*\[\d{1,3}(?:\s*,\s*\d{1,3})*\])*/u';

    private const string MARKER = '/\[(\d{1,3}(?:\s*,\s*\d{1,3})*)\]/';

    public function __construct(
        private readonly StructuredCompletion $completion,
        private readonly TaskModels $models,
    ) {}

    public function score(EvalCase $case, AgentRunResultData $result): ScoreResultData
    {
        $claims = $this->claims($result->answer);

        if ($claims === []) {
            return ScoreResultData::fail(0.0, 'The answer cites no source.');
        }

        $known = count($result->blocks);
        $checkable = array_values(array_filter($claims, static fn (array $claim): bool => max($claim['blocks']) <= $known));
        $supported = 0;

        if ($checkable !== []) {
            try {
                $verdicts = $this->judge($result, $checkable);
            } catch (Throwable $e) {
                return ScoreResultData::fail(0.0, 'Judge call failed: '.$e->getMessage());
            }

            if ($verdicts === null) {
                return ScoreResultData::fail(0.0, 'Judge returned no parseable verdict.');
            }

            $supported = count(array_filter($verdicts, static fn (mixed $verdict): bool => (bool) $verdict));
        }

        $score = round($supported / count($claims), 4);
        $minimum = (float) ($case->scorer_config['min_supported'] ?? self::DEFAULT_MIN_SUPPORTED);
        $rationale = sprintf('%d of %d cited sentences are supported by the passage they cite.', $supported, count($claims));

        return new ScoreResultData($score >= $minimum, $score, $rationale);
    }

    /**
     * The sentences that carry markers, without them, with the blocks they cite.
     *
     * @return list<array{sentence: string, blocks: non-empty-list<int>}>
     */
    private function claims(string $answer): array
    {
        $claims = [];

        foreach (Str::matchAll(self::SENTENCE, $answer) as $sentence) {
            $blocks = Str::matchAll(self::MARKER, $sentence)
                ->flatMap(static fn (string $numbers): array => Str::of($numbers)->explode(',')->map(static fn (string $n): int => (int) Str::trim($n))->all())
                ->filter(static fn (int $n): bool => $n > 0)
                ->unique()
                ->values()
                ->all();

            $text = Str::of($sentence)->replaceMatches(self::MARKER, '')->squish()->value();

            if ($blocks !== [] && $text !== '') {
                $claims[] = ['sentence' => $text, 'blocks' => $blocks];
            }
        }

        return $claims;
    }

    /**
     * @param  list<array{sentence: string, blocks: non-empty-list<int>}>  $claims
     * @return list<mixed>|null one verdict per claim, or null when the reply cannot be read
     */
    private function judge(AgentRunResultData $result, array $claims): ?array
    {
        $data = $this->completion->send(new ChatRequestData(
            model: $this->models->model(LlmTask::EvalJudge),
            messages: [
                ChatMessageData::system(Str::trim(prompt('evals/citations-system'))),
                ChatMessageData::user(Str::rtrim(prompt('evals/citations-user', ['blocks' => $result->blocks, 'claims' => $claims]), "\n")),
            ],
            tag: AiCallTag::EvaluationJudge,
            temperature: 0,
            responseSchema: new ResponseSchemaData('citation_verdicts', [
                'type' => 'object',
                'additionalProperties' => false,
                'required' => ['supported'],
                'properties' => [
                    'supported' => [
                        'type' => 'array',
                        'description' => 'One entry per numbered sentence, in order: true when the cited passages state it.',
                        'items' => ['type' => 'boolean'],
                    ],
                ],
            ], strict: false),
        ))->data;

        $verdicts = $data['supported'] ?? null;

        return is_array($verdicts) && count($verdicts) === count($claims) ? array_values($verdicts) : null;
    }
}
