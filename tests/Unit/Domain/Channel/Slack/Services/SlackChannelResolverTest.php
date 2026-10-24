<?php

use Domain\Channel\Slack\Models\AgentSlackApp;
use Domain\Channel\Slack\Services\SlackChannelResolver;
use Domain\Conversation\Actions\Conversations\StartConversationAction;
use Tests\Support\Channel\Slack\FakeSlackClient;

/**
 * How the Slack resolver names a new channel mapping: after the channel's
 * name, looked up once on first contact; a direct message has none. The
 * find-or-create it shares with every channel is in
 * ChannelConversationResolverTest.
 */
it('names the mapping, its session and its conversation after the Slack channel', function (?array $info, ?string $channelName, ?string $sessionName, string $title): void {
    $client = new FakeSlackClient;
    $client->channel = $info;

    $channel = new SlackChannelResolver(app(StartConversationAction::class), $client)
        ->resolve(AgentSlackApp::factory()->create(), 'C123CHAN');

    expect($channel->channel_name)->toBe($channelName)
        ->and($channel->session->name)->toBe($sessionName)
        ->and($channel->conversation->title)->toBe($title);
})->with([
    'a named channel' => [['name' => 'support', 'is_im' => false], 'support', '#support', 'Slack · #support'],
    'a direct message' => [['name' => null, 'is_im' => true], null, null, 'Slack · DM'],
    'a failed lookup' => [null, null, null, 'Slack'],
]);
