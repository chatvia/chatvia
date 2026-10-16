<?php

use App\Identity\Workspace\Models\User;
use Domain\Channel\Enums\MetaApp;
use Domain\Channel\Messenger\Models\AgentMessengerPage;
use Domain\Membership\Team\Services\TeamService;
use Inertia\Testing\AssertableInertia;

/**
 * Messenger, WhatsApp and Instagram run on Meta apps an admin sets up in the
 * admin panel (Settings → Channels). The Channels page tells the dashboard
 * which of them are set up, so it can disable the cards of the rest.
 */
beforeEach(function (): void {
    $this->owner = User::factory()->create();
    app(TeamService::class)->createDefaultTeam($this->owner);
    $this->owner->refresh();

    $this->agent = createAgent($this->owner);
});

/** Fill in (or clear) every field the admin panel requires for a Meta app. */
function configureMetaApp(MetaApp $app, bool $configured): void
{
    $value = static fn (string $filled): ?string => $configured ? $filled : null;

    config([
        "services.meta.{$app->value}.app_id" => $value('APP-ID'),
        "services.meta.{$app->value}.app_secret" => $value('APP-SECRET'),
        "services.meta.{$app->value}.webhook_verify_token" => $value('VERIFY-TOKEN'),
        "services.meta.{$app->value}.config_id" => $value('CONFIG-ID'),
    ]);
}

it('marks the Meta channels as unavailable until an admin sets up their apps', function (): void {
    foreach (MetaApp::cases() as $app) {
        configureMetaApp($app, configured: false);
    }

    $this->actingAs($this->owner)
        ->get(route('agents.channels', $this->agent))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('messenger.available', false)
            ->where('whatsapp.available', false)
            ->where('instagram.available', false));
});

it('marks a Meta channel as available once its app is set up', function (): void {
    configureMetaApp(MetaApp::Messenger, configured: true);
    configureMetaApp(MetaApp::Whatsapp, configured: false);
    configureMetaApp(MetaApp::Instagram, configured: true);

    $this->actingAs($this->owner)
        ->get(route('agents.channels', $this->agent))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('messenger.available', true)
            ->where('whatsapp.available', false)
            ->where('instagram.available', true));
});

it('still reports a connected channel whose app was cleared, so it can be disconnected', function (): void {
    configureMetaApp(MetaApp::Messenger, configured: false);

    AgentMessengerPage::create([
        'agent_id' => $this->agent->id, 'page_access_token' => 't', 'page_id' => 'PAGE-1',
        'page_name' => 'Acme', 'is_enabled' => true,
    ]);

    $this->actingAs($this->owner)
        ->get(route('agents.channels', $this->agent))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('messenger.available', false)
            ->where('messenger.connected', true));
});

it('needs every required field before a Meta app counts as set up', function (): void {
    configureMetaApp(MetaApp::Instagram, configured: true);
    expect(MetaApp::Instagram->isConfigured())->toBeTrue();

    config(['services.meta.instagram.webhook_verify_token' => '']);
    expect(MetaApp::Instagram->isConfigured())->toBeFalse();
});

it('needs the Login configuration for WhatsApp but not for Messenger', function (): void {
    configureMetaApp(MetaApp::Whatsapp, configured: true);
    configureMetaApp(MetaApp::Messenger, configured: true);

    config([
        'services.meta.whatsapp.config_id' => null,
        'services.meta.messenger.config_id' => null,
    ]);

    expect(MetaApp::Whatsapp->isConfigured())->toBeFalse()
        ->and(MetaApp::Messenger->isConfigured())->toBeTrue();
});
