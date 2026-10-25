<?php

use Domain\Llm\Enums\LlmProviderKind;
use Domain\Llm\Exceptions\NoSummaryModelException;
use Domain\Llm\Models\Llm;
use Domain\Llm\Services\SourceSummaryModel;

/**
 * Source summaries need a model that reads PDF files, from any provider. The
 * one an admin picks wins while it may be used; with none picked, the
 * default chat model when it reads them, else the first model that does.
 * Turned off, there is none, whatever the models read.
 */
function ssmModel(string $name, array $inputs, LlmProviderKind $kind = LlmProviderKind::Mistral): Llm
{
    return testLlm($name, $kind, ['capabilities' => ['reasoning' => false, 'modalities' => ['input' => ['text', ...$inputs]]]]);
}

beforeEach(function (): void {
    config(['chatvia.ai.source_summaries.enabled' => true, 'chatvia.ai.source_summaries.model' => null, 'chatvia.agent.default_model' => null]);
});

it('uses the picked model while it reads PDF files, whatever its provider', function (LlmProviderKind $kind): void {
    ssmModel('other-model', ['pdf']);
    $picked = ssmModel('picked-model', ['pdf'], $kind);
    config(['chatvia.ai.source_summaries.model' => (string) $picked->id]);

    expect(app(SourceSummaryModel::class)->find()?->is($picked))->toBeTrue();
})->with([
    'Mistral' => [LlmProviderKind::Mistral],
    'Anthropic' => [LlmProviderKind::Anthropic],
    'OpenAI' => [LlmProviderKind::OpenAi],
    'a server of your own' => [LlmProviderKind::Custom],
]);

it('counts a model that reads PDF files but not images', function (): void {
    $reader = ssmModel('pdf-only', ['pdf']);

    expect(app(SourceSummaryModel::class)->find()?->is($reader))->toBeTrue();
});

it('has none when the picked model does not read PDF files, rather than another', function (): void {
    ssmModel('reader', ['pdf']);
    config(['chatvia.ai.source_summaries.model' => (string) ssmModel('picked-model', ['image'])->id]);

    expect(app(SourceSummaryModel::class)->find())->toBeNull()
        ->and(fn (): Llm => app(SourceSummaryModel::class)->model())->toThrow(NoSummaryModelException::class);
});

it('takes the default chat model automatically when it reads PDF files, else the first model that does', function (): void {
    $default = ssmModel('default-model', ['image']);
    $reader = ssmModel('reader', ['pdf']);
    config(['chatvia.agent.default_model' => (string) $default->id]);

    expect(app(SourceSummaryModel::class)->find()?->is($reader))->toBeTrue();

    $default->update(['capabilities' => ['reasoning' => false, 'modalities' => ['input' => ['text', 'pdf']]]]);
    Llm::flushCatalogCache();

    expect(app(SourceSummaryModel::class)->find()?->is($default->fresh()))->toBeTrue();
});

it('has none when no model reads PDF files', function (): void {
    ssmModel('text-only', []);

    expect(app(SourceSummaryModel::class)->isAvailable())->toBeFalse();
});

it('has none while an admin has source summaries off', function (): void {
    ssmModel('reader', ['pdf']);
    config(['chatvia.ai.source_summaries.enabled' => false]);

    expect(app(SourceSummaryModel::class)->isAvailable())->toBeFalse();
});
