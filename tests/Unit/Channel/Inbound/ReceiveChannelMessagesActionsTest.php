<?php

use Domain\Agent\Models\Agent;
use Domain\Channel\Instagram\Actions\ReceiveInstagramMessagesAction;
use Domain\Channel\Instagram\Data\InstagramInboundMessageData;
use Domain\Channel\Instagram\Jobs\HandleInstagramMessageJob;
use Domain\Channel\Instagram\Models\AgentInstagramAccount;
use Domain\Channel\Messenger\Actions\ReceiveMessengerMessagesAction;
use Domain\Channel\Messenger\Data\MessengerInboundMessageData;
use Domain\Channel\Messenger\Jobs\HandleMessengerMessageJob;
use Domain\Channel\Messenger\Models\AgentMessengerPage;
use Domain\Channel\Slack\Actions\ReceiveSlackEventAction;
use Domain\Channel\Slack\Data\SlackEventData;
use Domain\Channel\Slack\Jobs\HandleSlackEventJob;
use Domain\Channel\Slack\Models\AgentSlackApp;
use Domain\Channel\Teams\Actions\ReceiveTeamsActivityAction;
use Domain\Channel\Teams\Jobs\HandleTeamsActivityJob;
use Domain\Channel\Teams\Models\AgentTeamsBot;
use Domain\Channel\Telegram\Actions\ReceiveTelegramUpdateAction;
use Domain\Channel\Telegram\Data\TelegramSenderData;
use Domain\Channel\Telegram\Data\TelegramUpdateData;
use Domain\Channel\Telegram\Jobs\HandleTelegramUpdateJob;
use Domain\Channel\Telegram\Models\AgentTelegramBot;
use Domain\Channel\Whatsapp\Actions\ReceiveWhatsappMessagesAction;
use Domain\Channel\Whatsapp\Data\WhatsappInboundMessageData;
use Domain\Channel\Whatsapp\Jobs\HandleWhatsappMessageJob;
use Domain\Channel\Whatsapp\Models\AgentWhatsappNumber;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * The inbound handlers behind the webhooks: route each verified message to
 * its agent's connection, ignore what isn't a user's message, and queue a
 * message at most once even when the provider delivers it again.
 */
beforeEach(function (): void {
    Bus::fake();
    Cache::flush();
    $this->agent = Agent::factory()->create(['is_public' => true]);
});

it('queues a Messenger message once and ignores the Page’s own and unknown Pages', function (): void {
    $page = AgentMessengerPage::create([
        'agent_id' => $this->agent->id, 'page_access_token' => 't', 'page_id' => 'PAGE-1',
        'page_name' => 'Acme', 'enabled' => true,
    ]);
    $action = app(ReceiveMessengerMessagesAction::class);

    $messages = [
        new MessengerInboundMessageData('PAGE-1', 'USER-1', 'hello', 'mid.1'),
        new MessengerInboundMessageData('PAGE-1', 'PAGE-1', 'our own reply', 'mid.2'),
        new MessengerInboundMessageData('PAGE-9', 'USER-1', 'unknown page', 'mid.3'),
    ];

    $action->execute($messages);
    $action->execute($messages); // re-delivery

    Bus::assertDispatchedTimes(HandleMessengerMessageJob::class, 1);
    Bus::assertDispatched(HandleMessengerMessageJob::class, fn (HandleMessengerMessageJob $job): bool => $job->page->is($page) && $job->psid === 'USER-1' && $job->text === 'hello' && $job->queue === 'messages');
});

it('queues an Instagram message once and ignores the account’s own', function (): void {
    AgentInstagramAccount::create([
        'agent_id' => $this->agent->id, 'page_access_token' => 't', 'page_id' => '99', 'ig_id' => 'IG-1', 'username' => 'acme', 'enabled' => true,
    ]);
    $action = app(ReceiveInstagramMessagesAction::class);

    $messages = [
        new InstagramInboundMessageData('IG-1', 'USER-1', 'hello', 'mid.1'),
        new InstagramInboundMessageData('IG-1', 'IG-1', 'our own reply', 'mid.2'),
    ];

    $action->execute($messages);
    $action->execute($messages);

    Bus::assertDispatchedTimes(HandleInstagramMessageJob::class, 1);
    Bus::assertDispatched(HandleInstagramMessageJob::class, fn (HandleInstagramMessageJob $job): bool => $job->queue === 'messages');
});

it('queues a WhatsApp message once with the sender’s name', function (): void {
    AgentWhatsappNumber::create([
        'agent_id' => $this->agent->id, 'access_token' => 't', 'phone_number_id' => '111', 'waba_id' => '999', 'enabled' => true,
    ]);
    $action = app(ReceiveWhatsappMessagesAction::class);

    $messages = [new WhatsappInboundMessageData('111', '4915551234567', 'hello', 'wamid.1', 'Sam')];

    $action->execute($messages);
    $action->execute($messages);

    Bus::assertDispatchedTimes(HandleWhatsappMessageJob::class, 1);
    Bus::assertDispatched(HandleWhatsappMessageJob::class, fn (HandleWhatsappMessageJob $job): bool => $job->messageId === 'wamid.1' && $job->name === 'Sam' && $job->queue === 'messages');
});

it('parses WhatsApp text messages and drops statuses and media', function (): void {
    $messages = WhatsappInboundMessageData::listFromWebhook([
        'object' => 'whatsapp_business_account',
        'entry' => [['changes' => [[
            'field' => 'messages',
            'value' => [
                'metadata' => ['phone_number_id' => '111'],
                'contacts' => [['wa_id' => '49155', 'profile' => ['name' => 'Sam']]],
                'messages' => [
                    ['id' => 'wamid.1', 'from' => '49155', 'type' => 'text', 'text' => ['body' => 'hi']],
                    ['id' => 'wamid.2', 'from' => '49155', 'type' => 'image'],
                ],
                'statuses' => [['id' => 'wamid.OUT', 'status' => 'delivered']],
            ],
        ]]]],
    ]);

    expect($messages)->toHaveCount(1)
        ->and($messages[0]->senderName)->toBe('Sam')
        ->and($messages[0]->text)->toBe('hi');
});

it('queues a Slack mention once per event id and ignores the bot’s own messages', function (): void {
    $app = AgentSlackApp::create([
        'agent_id' => $this->agent->id, 'bot_token' => 'xoxb', 'signing_secret' => 'secret-secret-secret-1',
        'bot_user_id' => 'U0BOT', 'team_name' => 'Acme', 'webhook_secret' => Str::random(48), 'enabled' => true,
    ]);
    $action = app(ReceiveSlackEventAction::class);

    $mention = new SlackEventData('Ev1', 'app_mention', 'C1', '<@U0BOT> hi', null, 'U1');
    $action->execute($app, $mention);
    $action->execute($app, $mention);
    $action->execute($app, new SlackEventData('Ev2', 'message', 'D1', 'echo', null, 'U0BOT', 'im'));
    $action->execute($app, new SlackEventData('Ev3', 'message', 'C1', 'chatter', null, 'U1', 'channel'));

    Bus::assertDispatchedTimes(HandleSlackEventJob::class, 1);
});

it('queues a Teams message activity once and ignores bot activities', function (): void {
    $bot = AgentTeamsBot::create([
        'agent_id' => $this->agent->id, 'app_id' => 'f5e1f9d2-1111-2222-3333-444455556666', 'app_secret' => 's',
        'webhook_secret' => Str::random(48), 'enabled' => true,
    ]);
    $action = app(ReceiveTeamsActivityAction::class);

    $activity = [
        'type' => 'message', 'id' => 'act-1', 'text' => 'hi', 'serviceUrl' => 'https://smba.example/',
        'conversation' => ['id' => 'conv-1'], 'from' => ['id' => 'user'], 'recipient' => ['id' => 'bot'],
    ];

    $action->execute($bot, $activity);
    $action->execute($bot, $activity);
    $action->execute($bot, [...$activity, 'id' => 'act-2', 'from' => ['id' => 'x', 'role' => 'bot']]);

    Bus::assertDispatchedTimes(HandleTeamsActivityJob::class, 1);
});

it('queues a Telegram update once and ignores updates without text', function (): void {
    $bot = AgentTelegramBot::create([
        'agent_id' => $this->agent->id, 'bot_token' => '123:token', 'bot_username' => 'demo_bot',
        'webhook_secret' => Str::random(48), 'enabled' => true,
    ]);
    $action = app(ReceiveTelegramUpdateAction::class);

    $update = new TelegramUpdateData('1', '555', 'hello', new TelegramSenderData('Sam', 'sam_t'));
    $action->execute($bot, $update);
    $action->execute($bot, $update);
    $action->execute($bot, new TelegramUpdateData('2', '555', null));

    Bus::assertDispatchedTimes(HandleTelegramUpdateJob::class, 1);
    Bus::assertDispatched(HandleTelegramUpdateJob::class, fn (HandleTelegramUpdateJob $job): bool => $job->from === ['first_name' => 'Sam', 'username' => 'sam_t']);
});
