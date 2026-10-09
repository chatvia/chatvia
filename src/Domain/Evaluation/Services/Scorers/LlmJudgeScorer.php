<?php

namespace Domain\Evaluation\Services\Scorers;

use Domain\Evaluation\Data\AgentRunResultData;
use Domain\Evaluation\Data\ScoreResultData;
use Domain\Evaluation\Models\EvalCase;
use Domain\Llm\Data\Chat\ChatMessageData;
use Domain\Llm\Data\Chat\ChatRequestData;
use Domain\Llm\Data\Chat\ResponseSchemaData;
use Domain\Llm\Services\StructuredCompletion;
use Throwable;

/**
 * Asks an open-weight judge model for a constrained pass/fail verdict on the
 * agent's answer, given the case's grading criteria (and an optional reference
 * answer). Uses the provider's json_schema response format, so the verdict
 * comes back as `{verdict, score, rationale}`. Temperature 0 for
 * repeatability. The prompts live in resources/prompts/evals.
 *
 * A failed/garbled judge call fails the case (rather than silently passing) so a
 * provider outage can never be mistaken for a green eval.
 */
class LlmJudgeScorer implements ScorerInterface
{
    public function __construct(
        private readonly StructuredCompletion $completion,
    ) {}

    public function score(EvalCase $case, AgentRunResultData $result): ScoreResultData
    {
        $criteria = trim((string) ($case->scorer_config['criteria'] ?? ''));

        if ($criteria === '') {
            return ScoreResultData::fail(0.0, 'No grading criteria configured for the judge.');
        }

        try {
            $data = $this->completion->send(new ChatRequestData(
                model: (string) config('chatvia.ai.models.judge'),
                messages: [
                    ChatMessageData::system(trim(prompt('evals/judge-system'))),
                    ChatMessageData::user($this->userPrompt($case, $criteria, $result->answer)),
                ],
                temperature: 0,
                responseSchema: new ResponseSchemaData('eval_verdict', $this->schema(), strict: false),
            ))->data;
        } catch (Throwable $e) {
            return ScoreResultData::fail(0.0, 'Judge call failed: '.$e->getMessage());
        }

        if ($data === null) {
            return ScoreResultData::fail(0.0, 'Judge returned no parseable verdict.');
        }

        $pass = (bool) ($data['verdict'] ?? false);
        $rationale = is_string($data['rationale'] ?? null) ? trim($data['rationale']) : null;

        return new ScoreResultData($pass, $this->clampScore($data['score'] ?? null, $pass), $rationale !== '' ? $rationale : null);
    }

    private function userPrompt(EvalCase $case, string $criteria, string $answer): string
    {
        return rtrim(prompt('evals/judge-user', [
            'question' => $case->input,
            'criteria' => $criteria,
            'reference' => trim((string) ($case->expected_output ?? '')),
            'answer' => $answer === '' ? '(the agent produced no answer)' : $answer,
        ]), "\n");
    }

    /**
     * @return array<string, mixed>
     */
    private function schema(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['verdict', 'score', 'rationale'],
            'properties' => [
                'verdict' => [
                    'type' => 'boolean',
                    'description' => 'true if the answer satisfies the grading criteria, false otherwise.',
                ],
                'score' => [
                    'type' => 'number',
                    'description' => 'Quality from 0.0 (wholly wrong) to 1.0 (fully correct).',
                ],
                'rationale' => [
                    'type' => 'string',
                    'description' => 'One concise sentence justifying the verdict.',
                ],
            ],
        ];
    }

    /**
     * Coerce the model's score into 0..1, falling back to the binary verdict when
     * the score is missing or not a number.
     */
    private function clampScore(mixed $raw, bool $pass): float
    {
        if (! is_numeric($raw)) {
            return $pass ? 1.0 : 0.0;
        }

        return max(0.0, min(1.0, (float) $raw));
    }
}
