<?php

use Domain\Conversation\Enums\MessageRole;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Message;
use Domain\Conversation\Services\Turn\SpectraTurnTagger;
use Spectra\Facades\Spectra;

/**
 * A turn's AI calls go in the conversation in Spectra, at the turn after the
 * replies so far, so /spectra's Conversations screen reads them as a transcript.
 * Nothing is tagged while Spectra is off or without a conversation.
 */
beforeEach(function (): void {
    config()->set('spectra.enabled', true);
});

it('tags the calls with the conversation, at the turn after its replies', function (): void {
    $conversation = Conversation::factory()->create();
    Message::factory()->count(2)->create(['chat_conversation_id' => $conversation->id, 'role' => MessageRole::Assistant]);
    Message::factory()->count(3)->create(['chat_conversation_id' => $conversation->id, 'role' => MessageRole::User]);

    app(SpectraTurnTagger::class)->tag($conversation);
    $call = Spectra::startRequest('openai', 'gpt-4o');

    expect($call->conversationId)->toBe((string) $conversation->id)
        ->and($call->conversationTurn)->toBe(3);
});

it('tags nothing while Spectra is off or without a conversation', function (): void {
    app(SpectraTurnTagger::class)->tag(fn (): ?Conversation => null);

    config()->set('spectra.enabled', false);
    app(SpectraTurnTagger::class)->tag(Conversation::factory()->create());

    expect(Spectra::startRequest('openai', 'gpt-4o')->conversationId)->toBeNull();
});
