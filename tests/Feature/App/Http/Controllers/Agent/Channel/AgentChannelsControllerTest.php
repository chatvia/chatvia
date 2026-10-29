<?php

use App\Identity\Workspace\Models\User;
use Domain\Channel\Enums\MetaApp;
use Domain\Channel\Messenger\Models\AgentMessengerPage;
use Inertia\Testing\AssertableInertia;

/**
 * The agent's Channels page. It has its own `agent.channels` permission
 * (`agent.setup` alone does not open it). Messenger, WhatsApp and Instagram
 * run on Meta apps an admin sets up in the admin panel, and the page tells the
 * dashboard which of them are set up; a connected channel whose app was
 * cleared still shows as connected, so it can be disconnected.
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

it('opens the page only for a teammate with agent.channels, not agent.setup alone', function (): void {
    $this->actingAs(teammateWithPermissions($this->owner, ['agent.setup']))
        ->get(route('agents.channels', $this->agent))
        ->assertForbidden();

    $this->actingAs(teammateWithPermissions($this->owner, ['agent.channels']))
        ->get(route('agents.channels', $this->agent))
        ->assertOk();
});

it('marks only the Meta channels whose app is set up as available, and still reports a connected one', function (): void {
    accConfigureMetaApp(MetaApp::Messenger, configured: false);
    accConfigureMetaApp(MetaApp::Whatsapp, configured: false);
    accConfigureMetaApp(MetaApp::Instagram, configured: true);
    AgentMessengerPage::factory()->forAgent($this->agent)->create();

    $this->actingAs($this->owner)
        ->get(route('agents.channels', $this->agent))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('messenger.available', false)
            ->where('messenger.connected', true)
            ->where('whatsapp.available', false)
            ->where('instagram.available', true));
});
