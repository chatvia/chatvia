<?php

use App\Identity\Workspace\Models\User;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Services\Turn\SpectraTurnTagger;
use Spectra\Spectra;
use Spectra\Support\Tracking\RequestContext;

/**
 * A turn's AI calls are attributed to its conversation on Spectra's
 * Trackables screen: the tagger sets the conversation as Spectra's trackable
 * for the rest of the request, and only while Spectra records.
 */
/** The trackable Spectra would store on the next recorded AI call. */
function sttNextRequest(): RequestContext
{
    return app(Spectra::class)->startRequest('openai', 'gpt-test');
}

it('tags the AI calls of the turn with the conversation', function (): void {
    config(['spectra.enabled' => true]);
    $conversation = Conversation::factory()->create();

    app(SpectraTurnTagger::class)->tag($conversation);

    $request = sttNextRequest();
    expect($request->trackableType)->toBe('conversation')
        ->and($request->trackableId)->toBe($conversation->id);
});

it('resolves a deferred conversation lookup only when Spectra records', function (): void {
    config(['spectra.enabled' => true]);
    $conversation = Conversation::factory()->create();

    app(SpectraTurnTagger::class)->tag(fn (): Conversation => $conversation);

    expect(sttNextRequest()->trackableType)->toBe('conversation');
});

it('tags nothing while Spectra is not recording, without looking the conversation up', function (): void {
    config(['spectra.enabled' => false]);

    app(SpectraTurnTagger::class)->tag(function (): never {
        throw new LogicException('The conversation must not be looked up while Spectra is off.');
    });

    expect(sttNextRequest()->trackableType)->toBeNull();
});

it('tags nothing for a turn without a conversation row', function (): void {
    config(['spectra.enabled' => true]);

    app(SpectraTurnTagger::class)->tag(null);

    expect(sttNextRequest()->trackableType)->toBeNull();
});

it('wins over the signed-in user Spectra would otherwise fall back to', function (): void {
    config(['spectra.enabled' => true, 'spectra.tracking.auto_track_user' => true]);
    test()->actingAs(User::factory()->create());
    $conversation = Conversation::factory()->create();

    app(SpectraTurnTagger::class)->tag($conversation);

    $request = sttNextRequest();
    expect($request->trackableType)->toBe('conversation')
        ->and($request->trackableId)->toBe($conversation->id);
});
