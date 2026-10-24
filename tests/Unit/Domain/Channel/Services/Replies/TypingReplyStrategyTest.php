<?php

use Domain\Channel\Messenger\Models\AgentMessengerPage;
use Domain\Channel\Messenger\Services\MessengerAdapter;
use Domain\Channel\Messenger\Services\MessengerThreadResolver;
use Domain\Channel\Services\Replies\TypingReplyStrategy;
use Domain\Conversation\Actions\Conversations\StartConversationAction;
use Tests\Support\Channel\Messenger\FakeMessengerClient;
use Tests\Support\Channel\StubChannelReplyRunner;

/**
 * The reply on a channel whose messages can't be edited (Telegram, Messenger,
 * WhatsApp, Instagram): "typing…" the moment the message lands, shown again
 * while the agent works only once the platform has cleared it, then the whole
 * reply at once. HandleChannelMessageActionTest covers what each channel
 * sends; this covers the refresh.
 */
it('shows "typing…" again only once the channel’s refresh is due', function (): void {
    // Messenger clears the indicator after 15 seconds; progress comes every 10.
    $runner = new StubChannelReplyRunner('Hello from the agent', ['a', 'b', 'c'], secondsBetweenPartials: 10);
    $client = new FakeMessengerClient;
    $page = AgentMessengerPage::factory()->create();
    $channel = new MessengerAdapter($client, new MessengerThreadResolver(app(StartConversationAction::class), $client), $page, '24680');

    new TypingReplyStrategy($runner)->deliver($channel, $channel->resolveConversation(), 'hi there');

    // Once when the message lands, once 20 seconds in.
    expect($client->typing)->toHaveCount(2)
        ->and(array_column($client->sent, 'markdown'))->toBe(['Hello from the agent']);
});
