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

it('takes the default chat model when it reads PDF files, else the first that does, and a picked one only while it reads them and summaries are on', function (): void {
    $default = ssmModel('default-model', ['image']);
    $reader = ssmModel('reader', ['pdf']);
    config(['chatvia.agent.default_model' => (string) $default->id]);

    $first = app(SourceSummaryModel::class)->find();
    $default->update(['capabilities' => ['reasoning' => false, 'modalities' => ['input' => ['text', 'pdf']]]]);
    Llm::flushCatalogCache();
    $auto = app(SourceSummaryModel::class)->find();
    config(['chatvia.ai.source_summaries.model' => (string) ssmModel('images-only', ['image'])->id]);
    $unfit = app(SourceSummaryModel::class)->find();
    $picked = ssmModel('picked-model', ['pdf'], LlmProviderKind::Anthropic);
    config(['chatvia.ai.source_summaries.model' => (string) $picked->id]);
    $fit = app(SourceSummaryModel::class)->find();
    config(['chatvia.ai.source_summaries.enabled' => false]);

    expect($first?->is($reader))->toBeTrue()
        ->and($auto?->is($default->fresh()))->toBeTrue()
        ->and($unfit)->toBeNull()
        ->and($fit?->is($picked))->toBeTrue()
        ->and(app(SourceSummaryModel::class)->isAvailable())->toBeFalse()
        ->and(fn (): Llm => app(SourceSummaryModel::class)->model())->toThrow(NoSummaryModelException::class);
});
