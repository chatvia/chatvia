<?php

declare(strict_types=1);

use Domain\Llm\Enums\LlmTask;
use Domain\Llm\Exceptions\NoLlmAvailableException;
use Domain\Llm\Models\Llm;
use Domain\Llm\Models\LlmProvider;
use Domain\Llm\Services\TaskModels;

/**
 * The app's own LLM calls use a catalog model per task, picked in Settings →
 * AI by alias. A task that is unset, or whose model may not be used (it or
 * its provider is off, or the provider no longer serves it), falls back to
 * the model for app tasks, then to the chat model, then to the first usable
 * model of the catalog; the chat itself never takes the app tasks model.
 */
beforeEach(function (): void {
    // Start from an empty catalog: no starter models.
    LlmProvider::query()->delete();
});

it('uses the model picked for the task, else the app tasks model, else the chat model, skipping one that may not be used', function (string $pickedFor, string $off, string $expected): void {
    $models = [
        'chat' => testLlm('chat-model'),
        'app tasks' => testLlm('shared-model'),
        'task' => testLlm('guardrails-model'),
    ];
    config([
        'chatvia.agent.default_model' => (string) $models['chat']->id,
        TaskModels::SHARED_KEY => $pickedFor === 'chat' ? null : (string) $models['app tasks']->id,
        'chatvia.ai.tasks.guardrails' => $pickedFor === 'task' ? (string) $models['task']->id : null,
    ]);

    $picked = $models[$pickedFor];
    match ($off) {
        'nothing' => null,
        'model' => $picked->update(['is_enabled' => false]),
        'unlisted' => $picked->update(['unlisted_at' => now()]),
        'provider' => $picked->provider->update(['is_enabled' => false]),
    };

    expect(app(TaskModels::class)->model(LlmTask::Guardrails)->is($models[$expected]))->toBeTrue()
        ->and(app(TaskModels::class)->model(LlmTask::Chat)->is($models['chat']))->toBeTrue();
})->with([
    'picked for the task' => ['task', 'nothing', 'task'],
    'the task unset: the app tasks model' => ['app tasks', 'nothing', 'app tasks'],
    'neither set: the chat model' => ['chat', 'nothing', 'chat'],
    'the task\'s model is off: the app tasks model' => ['task', 'model', 'app tasks'],
    'the task\'s provider is off: the app tasks model' => ['task', 'provider', 'app tasks'],
    'the task\'s provider no longer serves it: the app tasks model' => ['task', 'unlisted', 'app tasks'],
    'the app tasks model is off: the chat model' => ['app tasks', 'model', 'chat'],
]);

it('gives the chat the first usable model when none is picked, never the app tasks model, and says none is set up when none may be used', function (): void {
    testLlm('a-off-model', attributes: ['is_enabled' => false]);
    $first = testLlm('b-first-model');
    $shared = testLlm('c-shared-model');
    config(['chatvia.agent.default_model' => null, TaskModels::SHARED_KEY => (string) $shared->id]);

    $chat = app(TaskModels::class)->model(LlmTask::Chat);
    $first->update(['is_enabled' => false]);
    $shared->update(['is_enabled' => false]);
    Llm::flushCatalogCache();

    expect($chat->is($first))->toBeTrue()
        ->and(app(TaskModels::class)->find(LlmTask::Chat))->toBeNull()
        ->and(fn (): Llm => app(TaskModels::class)->model(LlmTask::Titles))->toThrow(NoLlmAvailableException::class);
});
