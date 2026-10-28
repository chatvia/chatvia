<?php

declare(strict_types=1);

namespace Domain\Evaluation\Services\Scorers;

use Domain\Evaluation\Contracts\Scorer;
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
 * Asks an open-weight judge model for a constrained pass/fail verdict on the
 * agent's answer, given the case's grading criteria (and an optional reference
 * answer). Uses the provider's json_schema response format, so the verdict
 * comes back as `{verdict, score, rationale}`. Temperature 0 for
 * repeatability. The prompts live in resources/prompts/Evaluation.
 *
 * A failed/garbled judge call fails the case (rather than silently passing) so a
 * provider outage can never be mistaken for a green eval.
 */
class LlmJudgeScorer implements Scorer
{
    public function __construct(
        private readonly StructuredCompletion $completion,
        private readonly TaskModels $models,
    ) {}

    public function score(EvalCase $case, AgentRunResultData $result): ScoreResultData
    {
        $criteria = Str::trim((string) ($case->scorer_config['criteria'] ?? ''));

        if ($criteria === '') {
            return ScoreResultData::fail(0.0, 'No grading criteria configured for the judge.');
        }

        try {
            $data = $this->completion->send(new ChatRequestData(
                model: $this->models->model(LlmTask::EvalJudge),
                messages: [
                    ChatMessageData::system(Str::trim(prompt('Evaluation/judge-system'))),
                    ChatMessageData::user($this->userPrompt($case, $criteria, $result->answer)),
                ],
                tag: AiCallTag::EvaluationJudge,
                temperature: 0,
                responseSchema: new ResponseSchemaData('eval_verdict', $this->schema(), strict: false),
            ))->data;
        } catch (Throwable $e) {
            return ScoreResultData::fail(0.0, "Judge call failed: {$e->getMessage()}");
        }

        if ($data === null) {
            return ScoreResultData::fail(0.0, 'Judge returned no parseable verdict.');
        }

        $pass = (bool) ($data['verdict'] ?? false);
        $rationale = filled($data['rationale'] ?? null) ? Str::trim($data['rationale']) : null;

        return new ScoreResultData($pass, $this->clampScore($data['score'] ?? null, $pass), $rationale !== '' ? $rationale : null);
    }

    private function userPrompt(EvalCase $case, string $criteria, string $answer): string
    {
        return Str::rtrim(prompt('Evaluation/judge-user', [
            'question' => $case->input,
            'criteria' => $criteria,
            'reference' => Str::trim($case->expected_output ?? ''),
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
