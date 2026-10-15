<?php

use Domain\Agent\Models\Agent;
use Domain\Channel\Actions\HandleChannelMessageAction;
use Domain\Channel\Messenger\Models\AgentMessengerPage;
use Domain\Channel\Messenger\Models\MessengerThread;
use Domain\Channel\Messenger\Services\MessengerAdapter;
use Domain\Channel\Messenger\Services\MessengerThreadResolver;
use Domain\Channel\Services\ChannelReplyRunner;
use Domain\Channel\Slack\Models\AgentSlackApp;
use Domain\Channel\Slack\Services\SlackAdapter;
use Domain\Channel\Slack\Services\SlackChannelResolver;
use Domain\Conversation\Actions\Conversations\StartConversationAction;
use Domain\Conversation\Enums\ConversationSource;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\Support\Channel\StubChannelReplyRunner;
use Tests\Support\Messenger\FakeMessengerClient;
use Tests\Support\Slack\FakeSlackClient;

/**
 * The shared channel turn, resolved from the container as the channel jobs get
 * it: the access gate first, then the sender's chat, then the reply in the
 * channel's own way (typing then one message, or a placeholder that is edited),
 * then the chat is stamped. The pipeline is the stub runner, so this checks the
 * turn around it, not the model's answer. Each chat is resolved once before
 * the turn, at an earlier time, so the stamp the turn writes can be told apart
 * from the one written when the chat was created.
 */
beforeEach(function (): void {
    $this->runner = new StubChannelReplyRunner('Hello from the agent');
    app()->instance(ChannelReplyRunner::class, $this->runner);
    $this->travelTo(Carbon::parse('2026-10-01 10:00:00'));
});

function hcmMessengerPage(bool $public = true): AgentMessengerPage
{
    return AgentMessengerPage::create([
        'agent_id' => Agent::factory()->create(['is_public' => $public])->id,
        'page_access_token' => 'EAAG-page-token',
        'page_id' => '1234567890',
        'page_name' => 'Acme Support',
        'enabled' => true,
    ]);
}

function hcmSlackApp(): AgentSlackApp
{
    return AgentSlackApp::create([
        'agent_id' => Agent::factory()->create(['is_public' => true])->id,
        'bot_token' => 'xoxb-bot-token',
        'signing_secret' => 'signing-secret-value-12345678',
        'bot_user_id' => 'U0BOT',
        'team_name' => 'Acme Inc',
        'webhook_secret' => Str::random(48),
        'enabled' => true,
    ]);
}

it('answers a typing channel with one whole reply in the sender’s chat and stamps it', function (): void {
    $page = hcmMessengerPage();
    $client = new FakeMessengerClient;
    $resolver = new MessengerThreadResolver(app(StartConversationAction::class), $client);
    $thread = $resolver->resolve($page, '24680');
    $this->travelTo(Carbon::parse('2026-10-01 10:05:00'));

    app(HandleChannelMessageAction::class)->execute(new MessengerAdapter($client, $resolver, $page, '24680'), 'hi there');

    expect($client->typing)->toBe([['token' => 'EAAG-page-token', 'psid' => '24680']])
        ->and($client->sent)->toBe([['token' => 'EAAG-page-token', 'psid' => '24680', 'markdown' => 'Hello from the agent']]);

    // The pipeline answers in the chat's own Session + Conversation.
    $turn = $this->runner->turn;
    expect($turn->agent->is($page->agent))->toBeTrue()
        ->and($turn->sessionExternalId)->toBe($thread->session->external_id)
        ->and($turn->conversationExternalId)->toBe($thread->conversation->external_id)
        ->and($turn->message)->toBe('hi there')
        ->and($turn->source)->toBe(ConversationSource::Messenger);

    expect(MessengerThread::query()->count())->toBe(1)
        ->and($thread->fresh()->last_message_at->toDateTimeString())->toBe('2026-10-01 10:05:00');
});

it('streams on an editable channel: a placeholder, the reply edited into it, and the chat stamped', function (): void {
    $app = hcmSlackApp();
    $client = new FakeSlackClient;
    $resolver = new SlackChannelResolver(app(StartConversationAction::class), $client);
    $channel = $resolver->resolve($app, 'C123CHAN');
    $this->travelTo(Carbon::parse('2026-10-01 10:05:00'));

    app(HandleChannelMessageAction::class)->execute(new SlackAdapter($client, $resolver, $app, 'C123CHAN'), 'hi there');

    expect($client->posted)->toBe([['token' => 'xoxb-bot-token', 'channelId' => 'C123CHAN', 'markdown' => '_Thinking…_', 'threadTs' => null]])
        ->and($client->updates)->toBe([['token' => 'xoxb-bot-token', 'channelId' => 'C123CHAN', 'ts' => $client->nextTs, 'markdown' => 'Hello from the agent']])
        ->and($this->runner->received())->toBe('hi there')
        ->and($channel->fresh()->last_message_at->toDateTimeString())->toBe('2026-10-01 10:05:00');
});

it('runs no turn and leaves the chat unstamped when the placeholder cannot be posted', function (): void {
    $app = hcmSlackApp();
    $client = new FakeSlackClient;
    $client->nextTs = null;
    $resolver = new SlackChannelResolver(app(StartConversationAction::class), $client);
    $channel = $resolver->resolve($app, 'C123CHAN');
    $this->travelTo(Carbon::parse('2026-10-01 10:05:00'));

    app(HandleChannelMessageAction::class)->execute(new SlackAdapter($client, $resolver, $app, 'C123CHAN'), 'hi there');

    expect($this->runner->received())->toBeNull()
        ->and($client->updates)->toBe([])
        ->and($channel->fresh()->last_message_at->toDateTimeString())->toBe('2026-10-01 10:00:00');
});

it('stops at the access gate for a protected agent: one notice, no chat stored, no turn run', function (): void {
    $page = hcmMessengerPage(public: false);
    $client = new FakeMessengerClient;

    app(HandleChannelMessageAction::class)->execute(
        new MessengerAdapter($client, new MessengerThreadResolver(app(StartConversationAction::class), $client), $page, '24680'),
        'hello',
    );

    expect($client->sent)->toBe([['token' => 'EAAG-page-token', 'psid' => '24680', 'markdown' => 'This assistant isn’t available on Messenger right now.']])
        ->and($client->typing)->toBe([])
        ->and($this->runner->received())->toBeNull()
        ->and(MessengerThread::query()->count())->toBe(0);
});
