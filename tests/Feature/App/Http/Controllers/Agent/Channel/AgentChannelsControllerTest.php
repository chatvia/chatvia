<?php

use App\Identity\Workspace\Models\User;
use Domain\Channel\Enums\MetaApp;
use Domain\Channel\Messenger\Models\AgentMessengerPage;
use Inertia\Testing\AssertableInertia;

/**
 * The agent's Channels page. It has its own `agent.channels` permission
 * (`agent.setup` alone does not open it). Messenger, WhatsApp and Instagram
 * run on Meta apps an admin sets up in the admin panel (Settings → Channels),
 * and the page tells the dashboard which of them are set up, so it can
 * disable the cards of the rest.
 */
beforeEach(function (): void {
    $this->owner = User::factory()->create();
    $this->agent = createAgent($this->owner);
});

/** Fill in (or clear) every field the admin panel requires for a Meta app. */
function accConfigureMetaApp(MetaApp $app, bool $configured): void
{
    $value = static fn (string $filled): ?string => $configured ? $filled : null;

    config([
        "services.meta.{$app->value}.app_id" => $value('APP-ID'),
        "services.meta.{$app->value}.app_secret" => $value('APP-SECRET'),
        "services.meta.{$app->value}.webhook_verify_token" => $value('VERIFY-TOKEN'),
        "services.meta.{$app->value}.config_id" => $value('CONFIG-ID'),
    ]);
}

it('lets a teammate with agent.channels open the page', function (): void {
    $this->actingAs(teammateWithPermissions($this->owner, ['agent.channels']))
        ->get(route('agents.channels', $this->agent))
        ->assertOk();
});

it('forbids a teammate without agent.channels', function (array $permissions): void {
    $this->actingAs(teammateWithPermissions($this->owner, $permissions))
        ->get(route('agents.channels', $this->agent))
        ->assertForbidden();
})->with([
    'no permissions' => [[]],
    'only agent.setup' => [['agent.setup']],
]);

it('marks the Meta channels as unavailable until an admin sets up their apps', function (): void {
    accConfigureMetaApp(MetaApp::Messenger, configured: false);
    accConfigureMetaApp(MetaApp::Whatsapp, configured: false);
    accConfigureMetaApp(MetaApp::Instagram, configured: false);

    $this->actingAs($this->owner)
        ->get(route('agents.channels', $this->agent))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('messenger.available', false)
            ->where('whatsapp.available', false)
            ->where('instagram.available', false));
});

it('marks a Meta channel as available once its app is set up', function (): void {
    accConfigureMetaApp(MetaApp::Messenger, configured: true);
    accConfigureMetaApp(MetaApp::Whatsapp, configured: false);
    accConfigureMetaApp(MetaApp::Instagram, configured: true);

    $this->actingAs($this->owner)
        ->get(route('agents.channels', $this->agent))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('messenger.available', true)
            ->where('whatsapp.available', false)
            ->where('instagram.available', true));
});

it('still reports a connected channel whose app was cleared, so it can be disconnected', function (): void {
    accConfigureMetaApp(MetaApp::Messenger, configured: false);
    AgentMessengerPage::factory()->forAgent($this->agent)->create();

    $this->actingAs($this->owner)
        ->get(route('agents.channels', $this->agent))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('messenger.available', false)
            ->where('messenger.connected', true));
});
