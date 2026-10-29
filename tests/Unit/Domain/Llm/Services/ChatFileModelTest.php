<?php

use Domain\Llm\Enums\LlmProviderKind;
use Domain\Llm\Models\Llm;
use Domain\Llm\Services\ChatFileModel;

/**
 * Chat file uploads need a model that reads both PDF files and images. The
 * one an admin picks wins while it may be used; with none picked, the
 * default chat model when it reads them, else the first model that does.
 * Turned off, there is none, whatever the models read.
 */
function cfmModel(string $name, array $inputs, LlmProviderKind $kind = LlmProviderKind::Mistral): Llm
{
    return testLlm($name, $kind, ['capabilities' => ['reasoning' => false, 'modalities' => ['input' => ['text', ...$inputs]]]]);
}

beforeEach(function (): void {
    config(['chatvia.ai.chat_files.enabled' => true, 'chatvia.ai.chat_files.model' => null, 'chatvia.agent.default_model' => null]);
});

it('takes the default chat model when it reads both, else the first that does, and a picked one only while it reads both and uploads are on', function (): void {
    $default = cfmModel('default-model', ['image']);
    $reader = cfmModel('reader', ['image', 'pdf']);
    config(['chatvia.agent.default_model' => (string) $default->id]);

    $first = app(ChatFileModel::class)->find();
    $default->update(['capabilities' => ['reasoning' => false, 'modalities' => ['input' => ['text', 'image', 'pdf']]]]);
    Llm::flushCatalogCache();
    $auto = app(ChatFileModel::class)->find();
    config(['chatvia.ai.chat_files.model' => (string) cfmModel('images-only', ['image'])->id]);
    $unfit = app(ChatFileModel::class)->find();
    $picked = cfmModel('picked-model', ['image', 'pdf']);
    config(['chatvia.ai.chat_files.model' => (string) $picked->id]);
    $fit = app(ChatFileModel::class)->find();
    config(['chatvia.ai.chat_files.enabled' => false]);

    expect($first?->is($reader))->toBeTrue()
        ->and($auto?->is($default->fresh()))->toBeTrue()
        ->and($unfit)->toBeNull()
        ->and($fit?->is($picked))->toBeTrue()
        ->and(app(ChatFileModel::class)->isAvailable())->toBeFalse()
        ->and(app(ChatFileModel::class)->acceptedExtensions())->toBe([]);
});
