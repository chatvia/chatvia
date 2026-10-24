<?php

use App\Admin\Clusters\Settings\Pages\ManageAiProviderSettings;
use App\Admin\Clusters\Settings\Pages\ManageChannelSettings;
use App\Admin\Clusters\Settings\Pages\ManageMailSettings;
use App\Admin\Clusters\Settings\Pages\ManageSearchSettings;
use App\Admin\Clusters\Settings\Pages\ManageSignInSettings;
use App\Admin\Clusters\Settings\Pages\ManageWorkspaceSettings;
use App\Admin\Settings\AiProviderSettings;
use App\Admin\Settings\ChannelSettings;
use App\Admin\Settings\MailSettings;
use App\Admin\Settings\SignInSettings;
use App\Admin\Settings\WorkspaceSettings;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\Http;

/**
 * What every Settings page of the admin panel does, shown on the pages that
 * have each part: every card is a collapsible section with an outline, the
 * advanced cards come last and closed, a service card shows its status,
 * saved secrets are write-only, a blank field keeps its default, and a value
 * that is refused saves nothing.
 */
beforeEach(function (): void {
    $this->admin = adminUser();
});

it('shows every card as a section of its page, with an outline', function (): void {
    $this->actingAs($this->admin, 'admin')
        ->get(ManageSignInSettings::getUrl())
        ->assertOk()
        ->assertSeeInOrder(['On this page', '#sign-in-google', '#sign-in-microsoft', '#sign-in-github', '#sign-in-slack'], escape: false)
        ->assertSee('id="sign-in-google"', escape: false)
        ->assertSee('Open the provider console')
        ->assertSee(route('auth.google.callback'))
        ->assertSee(route('auth.slack.callback'));
});

it('shows the callback URLs on APP_URL, with a warning while it is internal', function (): void {
    config(['app.url' => 'https://chat.example.com']);

    $this->actingAs($this->admin, 'admin')
        ->get('http://localhost:8000'.Str::after(ManageSignInSettings::getUrl(), (string) url('/')))
        ->assertOk()
        ->assertSee('https://chat.example.com/auth/google/callback')
        ->assertDontSee('http://localhost:8000/auth/google/callback')
        ->assertDontSee('which the provider cannot reach');

    config(['app.url' => 'http://localhost:8000']);

    $this->actingAs($this->admin, 'admin')
        ->get(ManageSignInSettings::getUrl())
        ->assertOk()
        ->assertSee('which the provider cannot reach');
});

it('shows each service card with its status', function (): void {
    saveSettings(SignInSettings::class, ['google_client_id' => 'id', 'google_client_secret' => 'secret', 'github_client_id' => 'id']);

    $this->actingAs($this->admin, 'admin')
        ->get(ManageSignInSettings::getUrl())
        ->assertOk()
        ->assertSeeInOrder(['Google', 'Ready to use', 'Microsoft', 'Not set up', 'GitHub', 'Missing: Client secret.']);
});

it('lists the advanced cards last, closed, under their own heading', function (): void {
    $this->actingAs($this->admin, 'admin')
        ->get(ManageSearchSettings::getUrl())
        ->assertOk()
        ->assertSeeInOrder(['Finding answers', 'Follow-up questions', 'Technical options. The defaults suit most installs.', 'search-tuning'])
        ->assertSeeText('Save changes')
        ->assertDontSee('cv-outline__save');
});

it('lets an admin without two-factor authentication change the values', function (): void {
    saveSettings(SignInSettings::class, ['google_client_id' => 'client-id-shown']);

    $this->actingAs($this->admin, 'admin')
        ->get(ManageSignInSettings::getUrl())
        ->assertOk()
        ->assertSee('client-id-shown');

    adminPage(ManageSignInSettings::class, $this->admin)
        ->fillForm(['google_client_id' => 'client-id-changed'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(savedSetting(SignInSettings::class, 'google_client_id'))->toBe('client-id-changed');
});

it('never sends a saved secret to the browser', function (): void {
    saveSettings(SignInSettings::class, ['google_client_id' => 'visible-id', 'google_client_secret' => 'top-secret-value']);

    adminPage(ManageSignInSettings::class, $this->admin)
        ->assertSchemaStateSet(['google_client_id' => 'visible-id', 'google_client_secret' => null])
        ->assertDontSee('top-secret-value');
});

it('keeps a saved secret when its input is left blank', function (): void {
    saveSettings(SignInSettings::class, ['google_client_id' => 'old-id', 'google_client_secret' => 'kept-secret']);

    adminPage(ManageSignInSettings::class, $this->admin)
        ->fillForm(['google_client_id' => ' new-id ', 'google_client_secret' => ''])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified('Settings saved');

    expect(savedSetting(SignInSettings::class, 'google_client_id'))->toBe('new-id')
        ->and(savedSetting(SignInSettings::class, 'google_client_secret'))->toBe('kept-secret');
});

it('saves a new secret and puts it into config', function (): void {
    Http::fake(['api.scaleway.ai/*' => Http::response(['data' => [['id' => 'gpt-oss-120b']]])]);

    adminPage(ManageAiProviderSettings::class, $this->admin)
        ->fillForm(['scaleway_api_key' => 'scw-key'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(savedSetting(AiProviderSettings::class, 'scaleway_api_key'))->toBe('scw-key')
        ->and(config('services.scalewayai.api_key'))->toBe('scw-key');
});

it('removes one saved secret', function (): void {
    saveSettings(SignInSettings::class, ['google_client_id' => 'id', 'google_client_secret' => 'secret']);

    adminPage(ManageSignInSettings::class, $this->admin)
        ->callAction(TestAction::make('forget_google_client_secret')->schemaComponent('google_client_secret', schema: 'form'));

    expect(savedSetting(SignInSettings::class, 'google_client_secret'))->toBeNull()
        ->and(savedSetting(SignInSettings::class, 'google_client_id'))->toBe('id');
});

it('keeps a value that still equals its default unsaved', function (): void {
    adminPage(ManageWorkspaceSettings::class, $this->admin)
        ->fillForm(['registration_enabled' => true, 'new_workspace_message_limit' => 0])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(savedSetting(WorkspaceSettings::class, 'registration_enabled'))->toBeNull()
        ->and(savedSetting(WorkspaceSettings::class, 'new_workspace_message_limit'))->toBeNull();
});

it('saves nothing for a value in the wrong format', function (): void {
    adminPage(ManageChannelSettings::class, $this->admin)
        ->fillForm(['whatsapp_register_pin' => 'not-six-digits'])
        ->call('save')
        ->assertHasFormErrors(['whatsapp_register_pin']);

    expect(savedSetting(ChannelSettings::class, 'whatsapp_register_pin'))->toBeNull();
});

it('refuses a choice that is not offered', function (): void {
    adminPage(ManageMailSettings::class, $this->admin)
        ->fillForm(['mailer' => 'carrier-pigeon'])
        ->call('save')
        ->assertHasFormErrors(['mailer']);

    expect(savedSetting(MailSettings::class, 'mailer'))->toBeNull();
});
