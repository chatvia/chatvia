<?php

use Domain\Channel\Messenger\Models\AgentMessengerPage;
use Domain\Channel\Messenger\Services\MessengerThreadResolver;
use Domain\Conversation\Actions\Conversations\StartConversationAction;
use Tests\Support\Channel\Messenger\FakeMessengerClient;

/**
 * How the Messenger resolver names a new thread: after the sender's first
 * name, looked up once on first contact. The find-or-create it shares with
 * every channel is in ChannelConversationResolverTest.
 */
it('names the thread, its session and its conversation after the sender’s first name', function (?array $profile, ?string $name, string $title): void {
    $client = new FakeMessengerClient;
    $client->profile = $profile;

    $thread = new MessengerThreadResolver(app(StartConversationAction::class), $client)
        ->resolve(AgentMessengerPage::factory()->create(), '24680');

    expect($thread->first_name)->toBe($name)
        ->and($thread->session->name)->toBe($name)
        ->and($thread->conversation->title)->toBe($title);
})->with([
    'the first name' => [['first_name' => 'Sam'], 'Sam', 'Messenger · Sam'],
    'nothing when the profile lookup fails' => [null, null, 'Messenger'],
]);
