<?php

use Domain\Channel\Instagram\Models\AgentInstagramAccount;
use Domain\Channel\Instagram\Services\InstagramThreadResolver;
use Domain\Conversation\Actions\Conversations\StartConversationAction;
use Tests\Support\Channel\Instagram\FakeInstagramClient;

/**
 * How the Instagram resolver names a new thread: after the sender's profile,
 * looked up once on first contact. The find-or-create it shares with every
 * channel is in ChannelConversationResolverTest.
 */
it('names the thread, its session and its conversation after the sender’s profile', function (?array $profile, ?string $name, string $title): void {
    $client = new FakeInstagramClient;
    $client->profile = $profile;

    $thread = new InstagramThreadResolver(app(StartConversationAction::class), $client)
        ->resolve(AgentInstagramAccount::factory()->create(), '5550001234');

    expect($thread->name)->toBe($name)
        ->and($thread->session->name)->toBe($name)
        ->and($thread->conversation->title)->toBe($title);
})->with([
    'the profile name' => [['name' => 'Sam', 'username' => 'sam.codes'], 'Sam', 'Instagram · Sam'],
    'the @username when there is no name' => [['name' => null, 'username' => 'sam.codes'], '@sam.codes', 'Instagram · @sam.codes'],
    'nothing when the profile lookup fails' => [null, null, 'Instagram'],
]);
