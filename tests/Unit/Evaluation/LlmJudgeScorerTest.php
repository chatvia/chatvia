<?php

use Domain\Evaluation\Data\AgentRunResultData;
use Domain\Evaluation\Models\EvalCase;
use Domain\Evaluation\Services\Scorers\LlmJudgeScorer;
use Domain\Llm\Contracts\LlmProviderInterface;
use Domain\Llm\Services\LlmChat;
use Domain\Llm\Services\ModelRoles;
use Domain\Llm\Services\StructuredCompletion;
use GuzzleHttp\Psr7\Response as PsrResponse;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Client\Response as ClientResponse;
use Psr\Http\Message\ResponseInterface;

/**
 * The LLM judge is the only scorer with an external dependency, so its contract
 * is: shape the prompt, read the constrained verdict, and — critically — FAIL
 * (never silently pass) when the judge errors or returns garbage, so a provider
 * outage can't be mistaken for a green eval.
 */
function judgeCase(array $config = [], ?string $expected = null): EvalCase
{
    $case = new EvalCase;
    $case->name = 'judge case';
    $case->input = 'What is your refund window?';
    $case->scorer_type = 'llm_judge';
    $case->scorer_config = $config;
    $case->expected_output = $expected;

    return $case;
}

/** Wrap the agent's answer in the run-result the scorer now receives. */
function judgeRun(string $answer): AgentRunResultData
{
    return new AgentRunResultData($answer, [], [], null, 0, 'trace');
}

/**
 * A provider whose completion() returns a canned chat-completion body. `$body`
 * is the assistant message content (the judge's JSON verdict); pass null to make
 * completion() throw, simulating an outage.
 */
function judgeProvider(?string $body, bool $throws = false): LlmProviderInterface
{
    return new readonly class($body, $throws) implements LlmProviderInterface
    {
        public function __construct(private ?string $body, private bool $throws) {}

        public function stream(string $model, array $messages, array $settings = []): ResponseInterface
        {
            throw new RuntimeException('not used');
        }

        public function client(): PendingRequest
        {
            throw new RuntimeException('not used');
        }

        public function completion(array $data): Response
        {
            if ($this->throws) {
                throw new RuntimeException('provider down');
            }

            $payload = ['choices' => [['message' => ['content' => $this->body]]]];

            return new ClientResponse(new PsrResponse(200, [], (string) json_encode($payload)));
        }

        public function headers(): array
        {
            return [];
        }

        public function getGenerationCost(string $generationId): ?array
        {
            return null;
        }

        public function getCompletionEndpoint(): string
        {
            return '';
        }

        public function supportsCostTracking(): bool
        {
            return false;
        }
    };
}

it('passes when the judge returns a positive verdict', function (): void {
    $provider = judgeProvider(json_encode(['verdict' => true, 'score' => 0.9, 'rationale' => 'States the 30-day window.']));

    $result = new LlmJudgeScorer(new StructuredCompletion(app(LlmChat::class)->using($provider)), app(ModelRoles::class))->score(judgeCase(['criteria' => 'Must mention 30 days.']), judgeRun('Refunds within 30 days.'));

    expect($result->pass)->toBeTrue()
        ->and($result->score)->toBe(0.9)
        ->and($result->rationale)->toContain('30-day');
});

it('fails when the judge returns a negative verdict', function (): void {
    $provider = judgeProvider(json_encode(['verdict' => false, 'score' => 0.1, 'rationale' => 'No refund window stated.']));

    $result = new LlmJudgeScorer(new StructuredCompletion(app(LlmChat::class)->using($provider)), app(ModelRoles::class))->score(judgeCase(['criteria' => 'Must mention 30 days.']), judgeRun('We do not offer refunds.'));

    expect($result->pass)->toBeFalse()
        ->and($result->score)->toBe(0.1);
});

it('clamps an out-of-range score into 0..1', function (): void {
    $provider = judgeProvider(json_encode(['verdict' => true, 'score' => 4.5, 'rationale' => 'ok']));

    $result = new LlmJudgeScorer(new StructuredCompletion(app(LlmChat::class)->using($provider)), app(ModelRoles::class))->score(judgeCase(['criteria' => 'x']), judgeRun('answer'));

    expect($result->score)->toBe(1.0);
});

it('fails (not passes) when the judge call throws', function (): void {
    $provider = judgeProvider(null, throws: true);

    $result = new LlmJudgeScorer(new StructuredCompletion(app(LlmChat::class)->using($provider)), app(ModelRoles::class))->score(judgeCase(['criteria' => 'x']), judgeRun('answer'));

    expect($result->pass)->toBeFalse()
        ->and($result->rationale)->toContain('Judge call failed');
});

it('fails when the judge returns unparseable content', function (): void {
    $provider = judgeProvider('I cannot produce JSON today.');

    $result = new LlmJudgeScorer(new StructuredCompletion(app(LlmChat::class)->using($provider)), app(ModelRoles::class))->score(judgeCase(['criteria' => 'x']), judgeRun('answer'));

    expect($result->pass)->toBeFalse()
        ->and($result->rationale)->toContain('no parseable verdict');
});

it('fails without calling the judge when no criteria are configured', function (): void {
    $provider = judgeProvider(null, throws: true); // would throw if ever called

    $result = new LlmJudgeScorer(new StructuredCompletion(app(LlmChat::class)->using($provider)), app(ModelRoles::class))->score(judgeCase([]), judgeRun('answer'));

    expect($result->pass)->toBeFalse()
        ->and($result->rationale)->toContain('No grading criteria');
});
