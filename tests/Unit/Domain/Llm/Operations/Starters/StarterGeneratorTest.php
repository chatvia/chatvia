<?php

declare(strict_types=1);

use Domain\Llm\Enums\LlmTask;
use Domain\Llm\Exceptions\LlmCallFailedException;
use Domain\Llm\Models\Llm;
use Domain\Llm\Operations\Starters\StarterGenerator;
use Illuminate\Support\Facades\Http;

/**
 * Suggested starters: the model's JSON list comes back as clean questions, and
 * a provider that refuses or cannot be reached fails with a message that names
 * the model and says what the provider said.
 */

/** A test model picked for the starters task in Settings → AI. */
function sgStartersModel(string $name = 'test-model'): Llm
{
    $model = testLlm($name);
    config([LlmTask::Starters->configKey() => $model->id]);

    return $model;
}

it('returns the questions the model suggests', function (): void {
    sgStartersModel();
    fakeLlmCompletion('["What are your opening hours?", "  How do I get a refund?  ", ""]');

    expect(app(StarterGenerator::class)->generate(['We open at nine.'], 5))
        ->toBe(['What are your opening hours?', 'How do I get a refund?']);
});

it('says what the provider said when it refuses the call', function (): void {
    sgStartersModel('qwen/qwen3-30b-a3b-instruct-2507');
    Http::fake(['*' => Http::response([
        'error' => ['message' => 'No allowed providers are available for the selected model.', 'code' => 404],
    ], 404)]);

    $generate = fn (): array => app(StarterGenerator::class)->generate(['We open at nine.'], 5);

    expect($generate)->toThrow(LlmCallFailedException::class, 'The model "qwen/qwen3-30b-a3b-instruct-2507" on')
        ->and($generate)->toThrow(LlmCallFailedException::class, '(error 404): No allowed providers are available for the selected model.');
});

it('says when the provider cannot be reached', function (): void {
    sgStartersModel();
    Http::fake(['*' => Http::failedConnection()]);

    expect(fn (): array => app(StarterGenerator::class)->generate(['We open at nine.'], 5))
        ->toThrow(LlmCallFailedException::class, 'could not be reached to run the model "test-model"');
});

it('asks no model when there is no content', function (): void {
    Http::fake();

    expect(app(StarterGenerator::class)->generate([], 5))->toBe([]);

    Http::assertNothingSent();
});
