<?php

declare(strict_types=1);

use Domain\Llm\Actions\Catalog\CheckLlmListingAction;
use Domain\Llm\Actions\Catalog\RecheckUnlistedLlmsAction;
use Domain\Llm\Events\LlmUnlisted;
use Domain\Llm\Models\Llm;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

/**
 * A model the provider refused a call to and no longer lists is marked
 * unlisted (and the admins are told once); one it lists again comes back.
 * A list that could not be read changes nothing, and without a refused call
 * nothing is marked.
 */
beforeEach(function (): void {
    Event::fake([LlmUnlisted::class]);
});

function clListing(string ...$ids): void
{
    Http::fake(['llm.test/v1/models' => Http::response(['data' => array_map(fn (string $id): array => ['id' => $id], $ids)])]);
}

it('marks a refused model the provider no longer lists, once', function (): void {
    clListing('kept-model');
    $gone = testLlm('gone-model');

    app(CheckLlmListingAction::class)->execute($gone->provider, $gone);
    app(CheckLlmListingAction::class)->execute($gone->provider, $gone->fresh());

    expect($gone->fresh()->unlisted_at)->not->toBeNull()
        ->and($gone->fresh()->isAvailable())->toBeFalse();
    Event::assertDispatchedTimes(LlmUnlisted::class, 1);
    Event::assertDispatched(LlmUnlisted::class, fn (LlmUnlisted $event): bool => $event->llmId === $gone->id);
});

it('keeps a refused model the provider still lists', function (): void {
    clListing('busy-model');
    $busy = testLlm('busy-model');

    app(CheckLlmListingAction::class)->execute($busy->provider, $busy);

    expect($busy->fresh()->unlisted_at)->toBeNull();
    Event::assertNotDispatched(LlmUnlisted::class);
});

it('marks nothing when the list cannot be read', function (int $status): void {
    Http::fake(['llm.test/v1/models' => Http::response('down', $status)]);
    $model = testLlm('some-model');

    app(CheckLlmListingAction::class)->execute($model->provider, $model);

    expect($model->fresh()->unlisted_at)->toBeNull();
    Event::assertNotDispatched(LlmUnlisted::class);
})->with([
    'refused key' => [401],
    'server error' => [500],
]);

it('marks nothing without a refused call, and brings back a model listed again', function (): void {
    clListing('back-model');
    $back = testLlm('back-model', attributes: ['unlisted_at' => now()]);
    $typed = Llm::factory()->for($back->provider, 'provider')->create(['name' => 'typed-alias']);

    app(CheckLlmListingAction::class)->execute($back->provider);

    expect($back->fresh()->unlisted_at)->toBeNull()
        ->and($typed->fresh()->unlisted_at)->toBeNull();
    Event::assertNotDispatched(LlmUnlisted::class);
});

it('rechecks only the providers with a marked model', function (): void {
    clListing('back-model');
    $back = testLlm('back-model', attributes: ['unlisted_at' => now()]);
    testLlm('other-model');

    expect(app(RecheckUnlistedLlmsAction::class)->execute())->toBe(1)
        ->and($back->fresh()->unlisted_at)->toBeNull();
    Http::assertSentCount(1);
});
