<?php

namespace Domain\Evaluation\Services\Scorers;

use Domain\Analytics\Services\ConversationAnalyzer;
use Domain\Evaluation\Data\ScoreResultData;
use Domain\Evaluation\Models\EvalCase;
use Domain\Llm\Contracts\LlmProviderInterface;
use Throwable;

/**
 * Asks an open-weight judge model for a constrained pass/fail verdict on the
 * agent's answer, given the case's grading criteria (and an optional reference
 * answer). Uses the provider's json_schema response format — same mechanism as
 * {@see ConversationAnalyzer} — so the verdict always
 * comes back as `{verdict, score, rationale}`. Temperature 0 for repeatability.
 *
 * A failed/garbled judge call fails the case (rather than silently passing) so a
 * provider outage can never be mistaken for a green eval.
 */
class LlmJudgeScorer implements ScorerInterface
{
    public function __construct(
        private readonly LlmProviderInterface $provider,
    ) {}

    public function score(EvalCase $case, string $actual): ScoreResultData
    {
        $criteria = trim((string) ($case->scorer_config['criteria'] ?? ''));

        if ($criteria === '') {
            return ScoreResultData::fail(0.0, 'No grading criteria configured for the judge.');
        }

        try {
            $response = $this->provider->completion([
                'model' => config('chatvia.ai.models.judge'),
                'temperature' => 0,
                'response_format' => [
                    'type' => 'json_schema',
                    'json_schema' => [
                        'name' => 'eval_verdict',
                        'schema' => $this->schema(),
                    ],
                ],
                'messages' => [
                    ['role' => 'system', 'content' => $this->systemPrompt()],
                    ['role' => 'user', 'content' => $this->userPrompt($case, $criteria, $actual)],
                ],
            ]);

            $content = $response->json('choices.0.message.content');
            $data = $this->decodeJson(is_string($content) ? $content : null);
        } catch (Throwable $e) {
            return ScoreResultData::fail(0.0, 'Judge call failed: '.$e->getMessage());
        }

        if ($data === null) {
            return ScoreResultData::fail(0.0, 'Judge returned no parseable verdict.');
        }

        $pass = (bool) ($data['verdict'] ?? false);
        $score = $this->clampScore($data['score'] ?? null, $pass);
        $rationale = is_string($data['rationale'] ?? null) ? trim($data['rationale']) : null;

        return new ScoreResultData($pass, $score, $rationale !== '' ? $rationale : null);
    }

    private function userPrompt(EvalCase $case, string $criteria, string $actual): string
    {
        $reference = trim((string) ($case->expected_output ?? ''));
        $referenceBlock = $reference === ''
            ? ''
            : "\n\nREFERENCE ANSWER (a known-good answer; the agent need not match it word-for-word):\n{$reference}";

        $answer = $actual === '' ? '(the agent produced no answer)' : $actual;

        return <<<PROMPT
        QUESTION ASKED OF THE AGENT:
        {$case->input}

        GRADING CRITERIA (what a correct answer must satisfy):
        {$criteria}{$referenceBlock}

        AGENT'S ANSWER:
        {$answer}
        PROMPT;
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
        You are a strict but fair evaluator of an AI support agent's answer. Decide
        whether the answer satisfies the grading criteria. Judge only against the
        criteria (and the reference answer if given) — not your own opinions, and
        not style or wording. Be lenient on phrasing, strict on correctness: an
        answer that is factually wrong, omits a required point, or refuses when it
        should have answered fails. Return only the structured JSON verdict:
        verdict (true = pass), score (0.0 = wholly wrong .. 1.0 = fully correct),
        and a one-sentence rationale. Do not explain outside the JSON.
        PROMPT;
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
     * the score is missing or out of range.
     */
    private function clampScore(mixed $raw, bool $pass): float
    {
        if (! is_numeric($raw)) {
            return $pass ? 1.0 : 0.0;
        }

        return max(0.0, min(1.0, (float) $raw));
    }

    /**
     * Tolerant JSON extraction — mirrors ConversationAnalyzer::decodeJson so a
     * model that fences or prefaces the object still parses.
     *
     * @return array<string, mixed>|null
     */
    private function decodeJson(?string $content): ?array
    {
        if (! is_string($content) || $content === '') {
            return null;
        }

        $start = strpos($content, '{');
        $end = strrpos($content, '}');

        if ($start === false || $end === false || $end < $start) {
            return null;
        }

        $data = json_decode(substr($content, $start, $end - $start + 1), true);

        return is_array($data) ? $data : null;
    }
}
