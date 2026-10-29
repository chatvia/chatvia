<?php

declare(strict_types=1);

use Domain\Llm\Actions\Catalog\AddLlmModelAction;
use Domain\Llm\Data\Catalog\RemoteModelData;
use Domain\Llm\Models\LlmProvider;

/**
 * Adding a listed model to the catalog: it is kept under its provider's id,
 * once per provider.
 */
it('adds a model under its provider\'s id, once per provider', function (): void {
    $first = LlmProvider::factory()->create();
    $second = LlmProvider::factory()->create();
    $add = app(AddLlmModelAction::class);

    $one = $add->execute($first, new RemoteModelData('shared-model', 'Shared model'));
    $two = $add->execute($second, new RemoteModelData('shared-model', 'Shared model'));

    expect($one->name)->toBe('shared-model')
        ->and($two->name)->toBe('shared-model')
        ->and($two->is($one))->toBeFalse()
        ->and($add->execute($first, new RemoteModelData('shared-model', 'Renamed'))->is($one))->toBeTrue();
});
