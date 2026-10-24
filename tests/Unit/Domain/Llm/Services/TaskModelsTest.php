<?php

declare(strict_types=1);

use Domain\Agent\Models\Agent;
use Domain\Llm\Enums\LlmTask;
use Domain\Llm\Exceptions\NoLlmAvailableException;
use Domain\Llm\Models\Llm;
use Domain\Llm\Models\LlmProvider;
use Domain\Llm\Services\TaskModels;

/**
 * The app's own LLM calls use a catalog model per task, picked in Settings →
 * AI by alias. A task that is unset, or whose model may not be used (it or
 * its provider is off, or the provider no longer serves it), falls back to the model for app tasks, then to the
 * chat model, then to the first usable model of the catalog.
 */
beforeEach(function (): void {
    // Start from an empty catalog: no starter models.
    LlmProvider::query()->delete();
});

it('uses the model picked for the task', function (): void {
    $chat = testLlm('chat-model');
    $shared = testLlm('shared-model');
    $titles = testLlm('titles-model');
    config([
        'chatvia.agent.default_model' => (string) $chat->id,
        TaskModels::SHARED_KEY => (string) $shared->id,
        'chatvia.ai.tasks.titles' => (string) $titles->id,
    ]);

    expect(app(TaskModels::class)->model(LlmTask::Titles)->is($titles))->toBeTrue()
        ->and(app(TaskModels::class)->model(LlmTask::Chat)->is($chat))->toBeTrue();
});

it('falls back to the model for app tasks when a task is unset', function (): void {
    $chat = testLlm('chat-model');
    $shared = testLlm('shared-model');
    config([
        'chatvia.agent.default_model' => (string) $chat->id,
        TaskModels::SHARED_KEY => (string) $shared->id,
        'chatvia.ai.tasks.images' => null,
    ]);

    expect(app(TaskModels::class)->model(LlmTask::Images)->is($shared))->toBeTrue()
        ->and(app(TaskModels::class)->model(LlmTask::Chat)->is($chat))->toBeTrue();
});

it('falls back to the chat model when neither the task nor app tasks have one', function (): void {
    $chat = testLlm('chat-model');
    testLlm('other-model');
    config(['chatvia.agent.default_model' => (string) $chat->id, TaskModels::SHARED_KEY => null, 'chatvia.ai.tasks.images' => null]);

    expect(app(TaskModels::class)->model(LlmTask::Images)->is($chat))->toBeTrue();
});

it('skips a picked model that is off, or whose provider is off', function (string $pickedFor, string $off, string $expected): void {
    $models = [
        'chat' => testLlm('chat-model'),
        'app tasks' => testLlm('shared-model'),
        'task' => testLlm('guardrails-model'),
    ];
    config([
        'chatvia.agent.default_model' => (string) $models['chat']->id,
        TaskModels::SHARED_KEY => (string) $models['app tasks']->id,
        'chatvia.ai.tasks.guardrails' => $pickedFor === 'task' ? (string) $models['task']->id : null,
    ]);

    $picked = $models[$pickedFor];
    match ($off) {
        'model' => $picked->update(['is_enabled' => false]),
        'unlisted' => $picked->update(['unlisted_at' => now()]),
        'provider' => $picked->provider->update(['is_enabled' => false]),
    };

    expect(app(TaskModels::class)->model(LlmTask::Guardrails)->is($models[$expected]))->toBeTrue();
})->with([
    'the task\'s model is off: the app tasks model' => ['task', 'model', 'app tasks'],
    'the task\'s provider is off: the app tasks model' => ['task', 'provider', 'app tasks'],
    'the task\'s provider no longer serves it: the app tasks model' => ['task', 'unlisted', 'app tasks'],
    'the app tasks model is off: the chat model' => ['app tasks', 'model', 'chat'],
    'the app tasks provider is off: the chat model' => ['app tasks', 'provider', 'chat'],
]);

it('uses the first usable model when nothing is picked', function (): void {
    testLlm('a-off-model', attributes: ['is_enabled' => false]);
    $usable = testLlm('b-usable-model');
    config(['chatvia.agent.default_model' => null, TaskModels::SHARED_KEY => null]);

    expect(app(TaskModels::class)->model(LlmTask::Enhancement)->is($usable))->toBeTrue();
});

it('keeps the chat model out of the model for app tasks', function (): void {
    $first = testLlm('a-first-model');
    $shared = testLlm('b-shared-model');
    config(['chatvia.agent.default_model' => null, TaskModels::SHARED_KEY => (string) $shared->id]);

    expect(app(TaskModels::class)->model(LlmTask::Chat)->is($first))->toBeTrue();
});

it('says no model is set up when the catalog has none that may be used', function (): void {
    testLlm(attributes: ['is_enabled' => false]);

    expect(app(TaskModels::class)->find(LlmTask::Chat))->toBeNull()
        ->and(fn (): Llm => app(TaskModels::class)->model(LlmTask::Titles))->toThrow(NoLlmAvailableException::class);
});

it('starts new agents on the chat model', function (): void {
    testLlm('a-first-model');
    $chat = testLlm('b-chat-model');
    config(['chatvia.agent.default_model' => (string) $chat->id]);

    expect(Agent::factory()->create()->fresh()->llm_id)->toBe($chat->id);
});
