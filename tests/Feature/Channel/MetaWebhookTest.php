<?php

use Domain\Agent\Models\Agent;
use Domain\Channel\Enums\MetaApp;
use Domain\Channel\Instagram\Jobs\HandleInstagramMessageJob;
use Domain\Channel\Instagram\Models\AgentInstagramAccount;
use Domain\Channel\Messenger\Jobs\HandleMessengerMessageJob;
use Domain\Channel\Messenger\Models\AgentMessengerPage;
use Domain\Channel\Whatsapp\Jobs\HandleWhatsappMessageJob;
use Domain\Channel\Whatsapp\Models\AgentWhatsappNumber;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Tests\Support\Channel\MetaWebhook;

/**
 * The checks every Meta channel's webhook shares (InteractsWithMetaWebhooks):
 * the GET verify handshake echoes the challenge only for the channel app's
 * verify token, and a POSTed event is handled only when it is signed with the
 * channel app's secret. Routing and parsing are covered per channel.
 */
beforeEach(function (): void {
    Bus::fake();
    Cache::flush();

    foreach (MetaApp::cases() as $app) {
        config([
            "services.meta.{$app->value}.webhook_verify_token" => "verify-token-{$app->value}",
            "services.meta.{$app->value}.app_secret" => "app-secret-{$app->value}-0000000000",
        ]);
    }
});

/** The job a channel's webhook queues for a message. */
function metaWebhookJob(MetaApp $app): string
{
    return match ($app) {
        MetaApp::Messenger => HandleMessengerMessageJob::class,
        MetaApp::Instagram => HandleInstagramMessageJob::class,
        MetaApp::Whatsapp => HandleWhatsappMessageJob::class,
    };
}

/**
 * A text message as Meta delivers it, to a connection of the channel that
 * exists and is enabled.
 *
 * @return array<string, mixed>
 */
function metaWebhookMessage(MetaApp $app): array
{
    $agentId = Agent::factory()->create()->id;

    if ($app === MetaApp::Messenger) {
        AgentMessengerPage::create(['agent_id' => $agentId, 'page_access_token' => 'EAAG-page-token', 'page_id' => '1234567890', 'page_name' => 'Acme', 'enabled' => true]);

        return ['object' => 'page', 'entry' => [['id' => '1234567890', 'messaging' => [['sender' => ['id' => '24680'], 'message' => ['mid' => 'm1', 'text' => 'hello']]]]]];
    }

    if ($app === MetaApp::Instagram) {
        AgentInstagramAccount::create(['agent_id' => $agentId, 'page_access_token' => 'EAAG-page-token', 'page_id' => '998877', 'ig_id' => '17840012345', 'username' => 'acme', 'enabled' => true]);

        return ['object' => 'instagram', 'entry' => [['id' => '17840012345', 'messaging' => [['sender' => ['id' => '5550001234'], 'message' => ['mid' => 'm1', 'text' => 'hello']]]]]];
    }

    AgentWhatsappNumber::create(['agent_id' => $agentId, 'access_token' => 'EAAG-wa-token', 'phone_number_id' => '111222333444555', 'waba_id' => '999888777666555', 'display_phone_number' => '+15551234567', 'verified_name' => 'Acme', 'enabled' => true]);

    return ['object' => 'whatsapp_business_account', 'entry' => [['id' => '999888777666555', 'changes' => [['field' => 'messages', 'value' => [
        'metadata' => ['phone_number_id' => '111222333444555'],
        'messages' => [['from' => '4915551234567', 'id' => 'wamid.A1', 'type' => 'text', 'text' => ['body' => 'hello']]],
    ]]]]]];
}

it('echoes the challenge for the channel app\'s verify token', function (MetaApp $app): void {
    $response = MetaWebhook::verify($app, "verify-token-{$app->value}", 'challenge-123')->assertOk();

    expect($response->getContent())->toBe('challenge-123');
})->with(MetaApp::cases());

it('refuses the handshake for any other verify token', function (MetaApp $app): void {
    MetaWebhook::verify($app, 'wrong')->assertForbidden();
})->with(MetaApp::cases());

it('handles an event only when it is signed with the channel app\'s secret', function (MetaApp $app): void {
    $payload = metaWebhookMessage($app);

    MetaWebhook::post($app, $payload, null)->assertOk();
    MetaWebhook::post($app, $payload, 'wrong-app-secret-000000000000')->assertOk();
    Bus::assertNotDispatched(metaWebhookJob($app));

    MetaWebhook::post($app, $payload, "app-secret-{$app->value}-0000000000")->assertOk()->assertJson(['ok' => true]);
    Bus::assertDispatchedTimes(metaWebhookJob($app), 1);
})->with(MetaApp::cases());

it('verifies nothing while the channel app has no secret', function (MetaApp $app): void {
    $payload = metaWebhookMessage($app);
    config(["services.meta.{$app->value}.app_secret" => null]);

    // An HMAC keyed with an empty secret is easy to forge.
    MetaWebhook::post($app, $payload, '')->assertOk();

    Bus::assertNotDispatched(metaWebhookJob($app));
})->with(MetaApp::cases());
