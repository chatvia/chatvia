<?php

use Domain\Agent\Models\Agent;
use Domain\Channel\Actions\HandleChannelMessageAction;
use Domain\Channel\Instagram\Models\AgentInstagramAccount;
use Domain\Channel\Instagram\Services\InstagramAdapter;
use Domain\Channel\Instagram\Services\InstagramThreadResolver;
use Domain\Channel\Messenger\Models\AgentMessengerPage;
use Domain\Channel\Messenger\Models\MessengerThread;
use Domain\Channel\Messenger\Services\MessengerAdapter;
use Domain\Channel\Messenger\Services\MessengerThreadResolver;
use Domain\Channel\Services\ChannelReplyRunner;
use Domain\Channel\Slack\Models\AgentSlackApp;
use Domain\Channel\Slack\Services\SlackAdapter;
use Domain\Channel\Slack\Services\SlackChannelResolver;
use Domain\Channel\Whatsapp\Models\AgentWhatsappNumber;
use Domain\Channel\Whatsapp\Services\WhatsappAdapter;
use Domain\Channel\Whatsapp\Services\WhatsappChatResolver;
use Domain\Conversation\Actions\Conversations\StartConversationAction;
use Domain\Conversation\Enums\ConversationSource;
use Illuminate\Support\Carbon;
use Tests\Support\Channel\Instagram\FakeInstagramClient;
use Tests\Support\Channel\Messenger\FakeMessengerClient;
use Tests\Support\Channel\Slack\FakeSlackClient;
use Tests\Support\Channel\StubChannelReplyRunner;
use Tests\Support\Channel\Whatsapp\FakeWhatsappClient;

/**
 * The shared channel turn, resolved from the container as the channel jobs get
 * it: the access gate first, then the sender's chat, then the reply in the
 * channel's own way (typing then one message, or a placeholder that is edited),
 * then the chat is stamped. The pipeline is the stub runner, so this checks the
 * turn around it, not the model's answer. Each chat is resolved once before
 * the turn, at an earlier time, so the stamp the turn writes can be told apart
 * from the one written when the chat was created. The reply strategies have
 * their own tests (Services/Replies).
 */
beforeEach(function (): void {
    $this->runner = new StubChannelReplyRunner('Hello from the agent');
    app()->instance(ChannelReplyRunner::class, $this->runner);
    $this->travelTo(Carbon::parse('2026-10-01 10:00:00'));
});

function hcmSlack(FakeSlackClient $client): SlackAdapter
{
    $app = AgentSlackApp::factory()->create(['bot_token' => 'xoxb-bot-token']);

    return new SlackAdapter($client, new SlackChannelResolver(app(StartConversationAction::class), $client), $app, 'C123CHAN');
}

it('answers a typing channel with one whole reply to the sender, in their chat, and stamps it', function (Closure $channel, ConversationSource $source, string $prefix, array $typing, array $sent): void {
    [$adapter, $client] = $channel();
    $chat = $adapter->resolveConversation();
    $this->travelTo(Carbon::parse('2026-10-01 10:05:00'));

    app(HandleChannelMessageAction::class)->execute($adapter, 'hi there');

    expect($client->typing)->toBe([$typing])
        ->and($client->sent)->toBe([$sent]);

    // The pipeline answers in the chat's own Session + Conversation.
    $turn = $this->runner->turn;
    expect($turn->agent->is($adapter->agent()))->toBeTrue()
        ->and($turn->sessionExternalId)->toBe($chat->sessionExternalId())
        ->and($turn->conversationExternalId)->toBe($chat->conversationExternalId())
        ->and($turn->message)->toBe('hi there')
        ->and($turn->source)->toBe($source)
        ->and($turn->requestIdPrefix)->toBe($prefix)
        ->and($chat::query()->count())->toBe(1)
        ->and($chat->fresh()->last_message_at->toDateTimeString())->toBe('2026-10-01 10:05:00');
})->with([
    'Messenger' => [
        function (): array {
            $client = new FakeMessengerClient;
            $page = AgentMessengerPage::factory()->create(['page_access_token' => 'EAAG-page-token']);

            return [new MessengerAdapter($client, new MessengerThreadResolver(app(StartConversationAction::class), $client), $page, '24680'), $client];
        },
        ConversationSource::Messenger,
        'msgr_',
        ['token' => 'EAAG-page-token', 'psid' => '24680'],
        ['token' => 'EAAG-page-token', 'psid' => '24680', 'markdown' => 'Hello from the agent'],
    ],
    'Instagram' => [
        function (): array {
            $client = new FakeInstagramClient;
            $account = AgentInstagramAccount::factory()->create(['page_access_token' => 'IGAA-page-token']);

            return [new InstagramAdapter($client, new InstagramThreadResolver(app(StartConversationAction::class), $client), $account, '5550001234'), $client];
        },
        ConversationSource::Instagram,
        'ig_',
        ['token' => 'IGAA-page-token', 'igsid' => '5550001234'],
        ['token' => 'IGAA-page-token', 'igsid' => '5550001234', 'markdown' => 'Hello from the agent'],
    ],
    'WhatsApp, marking the inbound message read' => [
        function (): array {
            $client = new FakeWhatsappClient;
            $number = AgentWhatsappNumber::factory()->create(['access_token' => 'EAAG-wa-token', 'phone_number_id' => '111222333444555']);

            return [new WhatsappAdapter($client, app(WhatsappChatResolver::class), $number, '4915551234567', 'wamid.IN1', 'Sam'), $client];
        },
        ConversationSource::Whatsapp,
        'wa_',
        ['token' => 'EAAG-wa-token', 'phoneNumberId' => '111222333444555', 'messageId' => 'wamid.IN1'],
        ['token' => 'EAAG-wa-token', 'phoneNumberId' => '111222333444555', 'to' => '4915551234567', 'markdown' => 'Hello from the agent'],
    ],
]);

it('stamps the chat once a streamed reply lands', function (): void {
    $client = new FakeSlackClient;
    $channel = hcmSlack($client);
    $chat = $channel->resolveConversation();
    $this->travelTo(Carbon::parse('2026-10-01 10:05:00'));

    app(HandleChannelMessageAction::class)->execute($channel, 'hi there');

    expect(array_column($client->updates, 'markdown'))->toBe(['Hello from the agent'])
        ->and($chat->fresh()->last_message_at->toDateTimeString())->toBe('2026-10-01 10:05:00');
});

it('leaves the chat unstamped when the placeholder cannot be posted', function (): void {
    $client = new FakeSlackClient;
    $client->nextTs = null;
    $channel = hcmSlack($client);
    $chat = $channel->resolveConversation();
    $this->travelTo(Carbon::parse('2026-10-01 10:05:00'));

    app(HandleChannelMessageAction::class)->execute($channel, 'hi there');

    expect($this->runner->received())->toBeNull()
        ->and($chat->fresh()->last_message_at->toDateTimeString())->toBe('2026-10-01 10:00:00');
});

it('stops at the access gate for a protected agent: one notice, no chat stored, no turn run', function (): void {
    $client = new FakeMessengerClient;
    $page = AgentMessengerPage::factory()
        ->forAgent(Agent::factory()->create(['is_public' => false]))
        ->create(['page_access_token' => 'EAAG-page-token']);

    app(HandleChannelMessageAction::class)->execute(
        new MessengerAdapter($client, new MessengerThreadResolver(app(StartConversationAction::class), $client), $page, '24680'),
        'hello',
    );

    expect($client->sent)->toBe([['token' => 'EAAG-page-token', 'psid' => '24680', 'markdown' => 'This assistant isn’t available on Messenger right now.']])
        ->and($client->typing)->toBe([])
        ->and($this->runner->received())->toBeNull()
        ->and(MessengerThread::query()->count())->toBe(0);
});
