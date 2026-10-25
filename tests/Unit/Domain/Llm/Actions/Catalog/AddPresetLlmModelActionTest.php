<?php

declare(strict_types=1);

use Domain\Llm\Actions\Catalog\AddPresetLlmModelAction;
use Domain\Llm\Data\Catalog\RemoteModelData;
use Domain\Llm\Models\Llm;
use Domain\Llm\Models\LlmProvider;

/**
 * Adding a preset model: it starts with its "Reasons" switch off, and a
 * model already in the catalog keeps the switch the admin set.
 */
it('adds a preset model with reasoning off', function (): void {
    $provider = LlmProvider::factory()->create();

    $llm = app(AddPresetLlmModelAction::class)->execute($provider, new RemoteModelData('thinker', 'Thinker', ['reasoning' => true]));

    expect($llm->fresh()->is_reasoning_enabled)->toBeFalse();
});

it('keeps the switch of a model already in the catalog', function (): void {
    $provider = LlmProvider::factory()->create();
    $existing = Llm::factory()->for($provider, 'provider')->create(['name' => 'thinker', 'is_reasoning_enabled' => true]);

    $llm = app(AddPresetLlmModelAction::class)->execute($provider, new RemoteModelData('thinker', 'Thinker'));

    expect($llm->is($existing))->toBeTrue()
        ->and($llm->fresh()->is_reasoning_enabled)->toBeTrue();
});
