<?php

use Domain\Agent\Models\Agent;
use Domain\Llm\Enums\LlmTask;
use Domain\Llm\Exceptions\NoLlmAvailableException;
use Domain\Llm\Models\Llm;
use Domain\Llm\Models\LlmProvider;
use Domain\Llm\Services\TaskModels;

/**
 * The app's own LLM calls use a catalog model per task, picked in Settings →
 * AI by alias. A task that is unset, or whose model may not be used (it or
 * its provider is off), falls back to the model for app tasks, then to the
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
        'chatvia.agent.default_model' => $chat->external_name,
        TaskModels::SHARED_KEY => $shared->external_name,
        'chatvia.ai.tasks.titles' => $titles->external_name,
    ]);

    expect(app(TaskModels::class)->model(LlmTask::Titles)->is($titles))->toBeTrue()
        ->and(app(TaskModels::class)->model(LlmTask::Chat)->is($chat))->toBeTrue();
});

it('falls back to the model for app tasks when a task is unset', function (): void {
    $chat = testLlm('chat-model');
    $shared = testLlm('shared-model');
    config([
        'chatvia.agent.default_model' => $chat->external_name,
        TaskModels::SHARED_KEY => $shared->external_name,
        'chatvia.ai.tasks.images' => null,
    ]);

    expect(app(TaskModels::class)->model(LlmTask::Images)->is($shared))->toBeTrue()
        ->and(app(TaskModels::class)->model(LlmTask::Chat)->is($chat))->toBeTrue();
});

it('falls back to the chat model when neither the task nor app tasks have one', function (): void {
    $chat = testLlm('chat-model');
    testLlm('other-model');
    config(['chatvia.agent.default_model' => $chat->external_name, TaskModels::SHARED_KEY => null, 'chatvia.ai.tasks.images' => null]);

    expect(app(TaskModels::class)->model(LlmTask::Images)->is($chat))->toBeTrue();
});

it('skips a picked model that is off, or whose provider is off', function (string $what, string $level): void {
    $chat = testLlm('chat-model');
    $shared = testLlm('shared-model');
    $guardrails = testLlm('guardrails-model');
    config([
        'chatvia.agent.default_model' => $chat->external_name,
        TaskModels::SHARED_KEY => $shared->external_name,
        'chatvia.ai.tasks.guardrails' => $level === 'task' ? $guardrails->external_name : null,
    ]);

    $off = $level === 'task' ? $guardrails : $shared;
    $what === 'model' ? $off->update(['is_enabled' => false]) : $off->provider->update(['is_enabled' => false]);

    expect(app(TaskModels::class)->model(LlmTask::Guardrails)->is($level === 'task' ? $shared : $chat))->toBeTrue();
})->with(['model', 'provider'])->with(['task', 'app tasks']);

it('uses the first usable model when nothing is picked', function (): void {
    testLlm('off-model', attributes: ['is_enabled' => false, 'descriptive_name' => 'A first']);
    $usable = testLlm('usable-model', attributes: ['descriptive_name' => 'B second']);
    config(['chatvia.agent.default_model' => null, TaskModels::SHARED_KEY => null]);

    expect(app(TaskModels::class)->model(LlmTask::Enhancement)->is($usable))->toBeTrue();
});

it('keeps the chat model out of the model for app tasks', function (): void {
    $first = testLlm('first-model', attributes: ['descriptive_name' => 'A first']);
    $shared = testLlm('shared-model', attributes: ['descriptive_name' => 'B shared']);
    config(['chatvia.agent.default_model' => null, TaskModels::SHARED_KEY => $shared->external_name]);

    expect(app(TaskModels::class)->model(LlmTask::Chat)->is($first))->toBeTrue();
});

it('says no model is set up when the catalog has none that may be used', function (): void {
    testLlm(attributes: ['is_enabled' => false]);

    expect(app(TaskModels::class)->find(LlmTask::Chat))->toBeNull()
        ->and(fn (): Llm => app(TaskModels::class)->model(LlmTask::Titles))->toThrow(NoLlmAvailableException::class);
});

it('starts new agents on the chat model', function (): void {
    testLlm('first-model', attributes: ['descriptive_name' => 'A first']);
    $chat = testLlm('chat-model', attributes: ['descriptive_name' => 'B chat']);
    config(['chatvia.agent.default_model' => $chat->external_name]);

    expect(Agent::factory()->create()->fresh()->llm_id)->toBe($chat->id);
});

it('flushes the cached catalog when a provider changes', function (): void {
    $model = testLlm();

    expect(Llm::available()->contains('id', $model->id))->toBeTrue();

    $model->provider->update(['is_enabled' => false]);

    expect(Llm::available()->contains('id', $model->id))->toBeFalse();
});
