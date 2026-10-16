<?php

use Domain\Agent\Models\Agent;
use Domain\Channel\Slack\Models\AgentSlackApp;
use Domain\Channel\Slack\Models\SlackChannel;
use Domain\Channel\Slack\Services\SlackChannelResolver;
use Domain\Conversation\Actions\Conversations\StartConversationAction;
use Domain\Conversation\Enums\ConversationSource;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Session;
use Illuminate\Support\Str;
use Tests\Support\Slack\FakeSlackClient;

/**
 * The resolver maps a Slack (app, channel_id) pair onto a persistent Chatvia
 * Session + Conversation, so a Slack channel keeps one ongoing conversation
 * across messages. First contact creates them (and looks the channel name up
 * once); later messages reuse the same mapping.
 */
function slackResolverApp(): AgentSlackApp
{
    $agent = Agent::factory()->create();

    return AgentSlackApp::create([
        'agent_id' => $agent->id,
        'bot_token' => 'xoxb-token',
        'signing_secret' => 'signing-secret-value-12345678',
        'bot_user_id' => 'U0BOT',
        'team_name' => 'Acme Inc',
        'webhook_secret' => Str::random(48),
        'enabled' => true,
    ]);
}

function slackResolver(FakeSlackClient $client): SlackChannelResolver
{
    return new SlackChannelResolver(app(StartConversationAction::class), $client);
}

it('creates a Session + Conversation + mapping on first contact', function (): void {
    $app = slackResolverApp();

    $channel = slackResolver(new FakeSlackClient)->resolve($app, 'C123CHAN');

    expect($channel->agent_slack_app_id)->toBe($app->id)
        ->and($channel->channel_id)->toBe('C123CHAN')
        ->and($channel->channel_name)->toBe('support')
        ->and($channel->chat_session_id)->not->toBeNull()
        ->and($channel->chat_conversation_id)->not->toBeNull();

    // The Session is real and belongs to the agent, with no chatter user.
    $session = $channel->session;
    expect($session->agent_id)->toBe($app->agent_id)
        ->and($session->user_id)->toBeNull()
        ->and($session->name)->toBe('#support');
});

it('reuses the same mapping for a returning channel', function (): void {
    $app = slackResolverApp();
    $resolver = slackResolver(new FakeSlackClient);

    $first = $resolver->resolve($app, 'C123CHAN');
    $second = $resolver->resolve($app, 'C123CHAN');

    expect($second->id)->toBe($first->id);
    expect(SlackChannel::query()->count())->toBe(1);
});

it('keeps separate conversations for different channels', function (): void {
    $app = slackResolverApp();
    $resolver = slackResolver(new FakeSlackClient);

    $a = $resolver->resolve($app, 'C100');
    $b = $resolver->resolve($app, 'D200');

    expect($a->id)->not->toBe($b->id)
        ->and($a->chat_conversation_id)->not->toBe($b->chat_conversation_id);
    expect(SlackChannel::query()->count())->toBe(2);
});

it('tags the conversation as a Slack conversation', function (): void {
    $app = slackResolverApp();

    $channel = slackResolver(new FakeSlackClient)->resolve($app, 'C123CHAN');

    expect(Conversation::query()
        ->where('external_id', $channel->conversation->external_id)
        ->where('source', ConversationSource::Slack)
        ->exists())->toBeTrue();
});

it('copes with a DM (no channel name) and a failed name lookup', function (): void {
    $app = slackResolverApp();

    $dmClient = new FakeSlackClient;
    $dmClient->channel = ['name' => null, 'is_im' => true];
    $dm = slackResolver($dmClient)->resolve($app, 'D123DM');

    $blindClient = new FakeSlackClient;
    $blindClient->channel = null; // conversations.info failed — still resolves.
    $blind = slackResolver($blindClient)->resolve($app, 'C999');

    expect($dm->channel_name)->toBeNull()
        ->and($blind->channel_name)->toBeNull();
    expect(SlackChannel::query()->count())->toBe(2);
});
