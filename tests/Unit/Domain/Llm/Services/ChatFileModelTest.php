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
    config(['chatvia.ai.chat_files.enabled' => true, 'chatvia.ai.chat_files.model' => null, 'chatvia.agent.default_model' => null, 'chatvia.ai.models_dev.enabled' => false]);
});

it('uses the picked model while it reads PDF files and images', function (): void {
    cfmModel('other-model', ['image', 'pdf']);
    $picked = cfmModel('picked-model', ['image', 'pdf']);
    config(['chatvia.ai.chat_files.model' => (string) $picked->id]);

    expect(app(ChatFileModel::class)->find()?->is($picked))->toBeTrue();
});

it('has none when the picked model does not read both, rather than another', function (array $inputs): void {
    cfmModel('reader', ['image', 'pdf']);
    config(['chatvia.ai.chat_files.model' => (string) cfmModel('picked-model', $inputs)->id]);

    expect(app(ChatFileModel::class)->find())->toBeNull()
        ->and(app(ChatFileModel::class)->acceptedMimeTypes())->toBe([]);
})->with([
    'images only' => [['image']],
    'PDF files only' => [['pdf']],
    'neither' => [[]],
]);

it('takes the default chat model automatically when it reads both, else the first model that does', function (): void {
    $default = cfmModel('default-model', ['image']);
    $reader = cfmModel('reader', ['image', 'pdf']);
    config(['chatvia.agent.default_model' => (string) $default->id]);

    expect(app(ChatFileModel::class)->find()?->is($reader))->toBeTrue();

    $default->update(['capabilities' => ['reasoning' => false, 'modalities' => ['input' => ['text', 'image', 'pdf']]]]);
    Llm::flushCatalogCache();

    expect(app(ChatFileModel::class)->find()?->is($default->fresh()))->toBeTrue();
});

it('has none while an admin has chat file uploads off', function (): void {
    cfmModel('reader', ['image', 'pdf']);
    config(['chatvia.ai.chat_files.enabled' => false]);

    expect(app(ChatFileModel::class)->isAvailable())->toBeFalse()
        ->and(app(ChatFileModel::class)->acceptedExtensions())->toBe([]);
});

it('accepts PDF files and the four common image types', function (): void {
    cfmModel('reader', ['image', 'pdf']);

    expect(app(ChatFileModel::class)->acceptedMimeTypes())->toBe(['application/pdf', 'image/png', 'image/jpeg', 'image/webp', 'image/gif'])
        ->and(app(ChatFileModel::class)->acceptedExtensions())->toBe(['pdf', 'png', 'jpg', 'jpeg', 'webp', 'gif']);
});
