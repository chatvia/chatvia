<?php

declare(strict_types=1);

use Domain\Llm\Data\Chat\ChatMessageData;
use Domain\Llm\Data\Chat\ChatRequestData;
use Domain\Llm\Enums\AiCallTag;
use Domain\Llm\Models\Llm;

/**
 * The app's own tasks (guardrails, titles, rewrites...) send a body fitted to
 * the model: no temperature to a model that takes none, and the least
 * thinking a reasoning model takes, since these tasks need none.
 */
function crdPayload(Llm $model): array
{
    return new ChatRequestData($model, [ChatMessageData::user('Hi')], AiCallTag::Guardrails, temperature: 0.0)->toPayload();
}

it('asks a reasoning model for its least thinking and no temperature', function (): void {
    $payload = crdPayload(new Llm(['name' => 'gpt-6-luna', 'capabilities' => modelsDevEntry(['none', 'low', 'medium'], temperature: false)]));

    expect($payload['reasoning_effort'])->toBe('none')
        ->and($payload)->not->toHaveKey('temperature');
});

it('sends a model the catalog does not know as it is', function (): void {
    $payload = crdPayload(new Llm(['name' => 'my-server-model']));

    expect($payload['temperature'])->toBe(0.0)
        ->and($payload)->not->toHaveKey('reasoning_effort');
});
