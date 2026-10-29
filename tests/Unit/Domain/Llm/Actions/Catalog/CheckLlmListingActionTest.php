<?php

declare(strict_types=1);

use Domain\Llm\Actions\Catalog\CheckLlmListingAction;
use Domain\Llm\Events\LlmUnlisted;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

/**
 * A model the provider refused a call to and no longer lists is marked
 * unlisted (and the admins are told once); one it still lists is kept. A
 * list that could not be read changes nothing.
 */
beforeEach(function (): void {
    Event::fake([LlmUnlisted::class]);
});

function clListing(string ...$ids): void
{
    Http::fake(['llm.test/v1/models' => Http::response(['data' => array_map(fn (string $id): array => ['id' => $id], $ids)])]);
}

it('marks a refused model the provider no longer lists, once, and keeps one it still lists', function (): void {
    clListing('busy-model');
    $gone = testLlm('gone-model');
    $busy = testLlm('busy-model');

    app(CheckLlmListingAction::class)->execute($gone->provider, $gone);
    app(CheckLlmListingAction::class)->execute($gone->provider, $gone->fresh());
    app(CheckLlmListingAction::class)->execute($busy->provider, $busy);

    expect($gone->fresh()->unlisted_at)->not->toBeNull()
        ->and($gone->fresh()->isAvailable())->toBeFalse()
        ->and($busy->fresh()->unlisted_at)->toBeNull();
    Event::assertDispatchedTimes(LlmUnlisted::class, 1);
    Event::assertDispatched(LlmUnlisted::class, fn (LlmUnlisted $event): bool => $event->llmId === $gone->id);
});

it('marks nothing when the list cannot be read', function (): void {
    Http::fake(['llm.test/v1/models' => Http::response('down', 500)]);
    $model = testLlm('some-model');

    app(CheckLlmListingAction::class)->execute($model->provider, $model);

    expect($model->fresh()->unlisted_at)->toBeNull();
    Event::assertNotDispatched(LlmUnlisted::class);
});
