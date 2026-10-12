<?php

use App\Admin\Clusters\Settings\Pages\ManageAiSettings;
use App\Admin\Clusters\Settings\Pages\ManageChannelSettings;
use App\Admin\Clusters\Settings\Pages\ManageMailSettings;
use App\Admin\Clusters\Settings\Pages\ManagePlatformSettings;
use App\Admin\Clusters\Settings\Pages\ManageSignInSettings;
use App\Admin\Clusters\Settings\Pages\ManageWorkspaceSettings;
use App\Admin\Clusters\Settings\Pages\SettingsOverview;
use App\Admin\Clusters\Settings\SettingsCluster;
use App\Admin\Settings\AiSettings;
use App\Admin\Settings\ChannelSettings;
use App\Admin\Settings\MailSettings;
use App\Admin\Settings\PlatformSettings;
use App\Admin\Settings\SignInSettings;
use App\Admin\Settings\WorkspaceSettings;
use App\Identity\Workspace\Models\User;
use Database\Factories\AgentFileFactory;
use Domain\Knowledge\Ingestion\Models\Chunk;
use Domain\Knowledge\Retrieval\Services\Bm25Tokenizer;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/**
 * The Settings pages of the admin panel: an overview of every page, and on
 * each page every card as a collapsible section with an outline. Saved
 * secrets are write-only and a blank field keeps its default.
 */
beforeEach(function (): void {
    $this->admin = User::factory()->admin()->create()->fresh();
    $this->admin->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP');
});

/**
 * The value stored for one settings property, as saved (secrets decrypted).
 */
function storedSetting(string $class, string $property): mixed
{
    app()->forgetScopedInstances();

    return app($class)->{$property};
}

/**
 * One settings page, as the given admin.
 *
 * @param  class-string  $page
 */
function settingsPage(User $admin, string $page): Testable
{
    return Livewire::actingAs($admin, 'admin')->test($page);
}

it('shows every settings page, and the overview links to each one', function (): void {
    $this->actingAs($this->admin, 'admin');

    $overview = $this->get(SettingsOverview::getUrl())->assertOk();

    foreach (SettingsCluster::getClusteredComponents() as $page) {
        $this->get($page::getUrl())->assertOk()->assertSee($page::getNavigationLabel());

        if ($page !== SettingsOverview::class) {
            $overview->assertSee($page::getUrl());
        }
    }

    expect(SettingsCluster::getClusteredComponents())->toHaveCount(14);
});

it('shows a card for every service, with its status', function (): void {
    $settings = app(SignInSettings::class);
    $settings->google_client_id = 'id';
    $settings->google_client_secret = 'secret';
    $settings->github_client_id = 'id';
    $settings->save();

    $this->actingAs($this->admin, 'admin')
        ->get(ManageSignInSettings::getUrl())
        ->assertOk()
        ->assertSeeInOrder(['Google', 'Ready to use', 'Microsoft', 'Not set up', 'GitHub', 'Missing: Client secret.']);

    $this->get(SettingsOverview::getUrl())->assertSee('1 of 4 set up');
});

it('locks the values for admins without two-factor authentication', function (): void {
    $settings = app(SignInSettings::class);
    $settings->google_client_id = 'client-id-hidden';
    $settings->save();

    $withoutMfa = User::factory()->admin()->create()->fresh();

    $this->actingAs($withoutMfa, 'admin')
        ->get(ManageSignInSettings::getUrl())
        ->assertOk()
        ->assertSee('Set up two-factor authentication first')
        ->assertDontSee('client-id-hidden');

    $this->get(SettingsOverview::getUrl())
        ->assertSee('Set up two-factor authentication first')
        ->assertDontSee('set up</span>', escape: false);
});

it('never sends a saved secret to the browser', function (): void {
    $settings = app(SignInSettings::class);
    $settings->google_client_id = 'visible-id';
    $settings->google_client_secret = 'top-secret-value';
    $settings->save();

    settingsPage($this->admin, ManageSignInSettings::class)
        ->assertSchemaStateSet(['google_client_id' => 'visible-id', 'google_client_secret' => null])
        ->assertDontSee('top-secret-value');
});

it('keeps a saved secret when its input is left blank', function (): void {
    $settings = app(SignInSettings::class);
    $settings->google_client_id = 'old-id';
    $settings->google_client_secret = 'kept-secret';
    $settings->save();

    settingsPage($this->admin, ManageSignInSettings::class)
        ->fillForm(['google_client_id' => ' new-id ', 'google_client_secret' => ''])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified('Settings saved');

    expect(storedSetting(SignInSettings::class, 'google_client_id'))->toBe('new-id')
        ->and(storedSetting(SignInSettings::class, 'google_client_secret'))->toBe('kept-secret');
});

it('saves a new secret encrypted', function (): void {
    settingsPage($this->admin, ManageAiSettings::class)
        ->fillForm(['scaleway_api_key' => 'scw-key'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(storedSetting(AiSettings::class, 'scaleway_api_key'))->toBe('scw-key')
        ->and(config('services.scalewayai.api_key'))->toBe('scw-key')
        ->and(DB::table('settings')->where('name', 'scaleway_api_key')->value('payload'))->not->toContain('scw-key');
});

it('removes one saved secret', function (): void {
    $settings = app(SignInSettings::class);
    $settings->google_client_id = 'id';
    $settings->google_client_secret = 'secret';
    $settings->save();

    settingsPage($this->admin, ManageSignInSettings::class)
        ->callAction(TestAction::make('forget_google_client_secret')->schemaComponent('google_client_secret', schema: 'form'));

    expect(storedSetting(SignInSettings::class, 'google_client_secret'))->toBeNull()
        ->and(storedSetting(SignInSettings::class, 'google_client_id'))->toBe('id');
});

it('validates the format of a field', function (): void {
    settingsPage($this->admin, ManageChannelSettings::class)
        ->fillForm(['whatsapp_register_pin' => 'not-six-digits'])
        ->call('save')
        ->assertHasFormErrors(['whatsapp_register_pin']);

    expect(storedSetting(ChannelSettings::class, 'whatsapp_register_pin'))->toBeNull();
});

it('takes a path on this server or a full address for the widget script', function (): void {
    expect(config('chatvia.widget.script_url_embed'))->toBe('/shakomako/embed.js')
        ->and(url(config('chatvia.widget.script_url_embed')))->toBe(url('/shakomako/embed.js'));

    foreach (['/shakomako/next/embed.js', 'https://cdn.example.com/embed.js'] as $address) {
        settingsPage($this->admin, ManagePlatformSettings::class)
            ->fillForm(['widget_script_url' => $address])
            ->call('save')
            ->assertHasNoFormErrors();

        expect(storedSetting(PlatformSettings::class, 'widget_script_url'))->toBe($address);
    }

    expect(url(config('chatvia.widget.script_url_embed')))->toBe('https://cdn.example.com/embed.js');

    foreach (['shakomako/embed.js', '//cdn.example.com/embed.js', 'javascript:alert(1)', 'ftp://cdn.example.com/embed.js'] as $address) {
        settingsPage($this->admin, ManagePlatformSettings::class)
            ->fillForm(['widget_script_url' => $address])
            ->call('save')
            ->assertHasFormErrors(['widget_script_url']);
    }
});

it('shows the workspace values in use', function (): void {
    Livewire::actingAs($this->admin, 'admin')
        ->test(ManageWorkspaceSettings::class)
        ->assertSee('Sign-up open · no message limit')
        ->assertSee('Kept 366 days');

    settingsPage($this->admin, ManageWorkspaceSettings::class)
        ->assertSee('Sign-up and limits')
        ->assertSchemaStateSet(['registration_enabled' => true, 'new_workspace_message_limit' => null]);
});

it('keeps a value that still equals its default unsaved', function (): void {
    settingsPage($this->admin, ManageWorkspaceSettings::class)
        ->fillForm(['registration_enabled' => true, 'new_workspace_message_limit' => 0])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(storedSetting(WorkspaceSettings::class, 'registration_enabled'))->toBeNull()
        ->and(storedSetting(WorkspaceSettings::class, 'new_workspace_message_limit'))->toBeNull();
});

it('saves the workspace values, an off switch included', function (): void {
    settingsPage($this->admin, ManageWorkspaceSettings::class)
        ->fillForm(['registration_enabled' => false, 'new_workspace_message_limit' => 500])
        ->call('save')
        ->assertHasNoFormErrors();

    settingsPage($this->admin, ManageWorkspaceSettings::class)
        ->fillForm(['retention_days' => 90])
        ->call('save')
        ->assertHasNoFormErrors();

    $this->actingAs($this->admin, 'admin')
        ->get(ManageWorkspaceSettings::getUrl())
        ->assertSee('Sign-up closed · 500 messages a month');

    expect(storedSetting(WorkspaceSettings::class, 'registration_enabled'))->toBeFalse()
        ->and(storedSetting(WorkspaceSettings::class, 'new_workspace_message_limit'))->toBe(500)
        ->and(storedSetting(WorkspaceSettings::class, 'retention_days'))->toBe(90)
        ->and(config('chatvia.registration_enabled'))->toBeFalse();
});

it('validates the workspace values', function (): void {
    settingsPage($this->admin, ManageWorkspaceSettings::class)
        ->fillForm(['retention_days' => 0])
        ->call('save')
        ->assertHasFormErrors(['retention_days']);

    expect(storedSetting(WorkspaceSettings::class, 'retention_days'))->toBeNull();
});

it('refuses a choice that is not offered', function (): void {
    settingsPage($this->admin, ManageMailSettings::class)
        ->fillForm(['mailer' => 'carrier-pigeon'])
        ->call('save')
        ->assertHasFormErrors(['mailer']);

    expect(storedSetting(MailSettings::class, 'mailer'))->toBeNull();
});

it('refuses an embedding provider that cannot return the stored vector size', function (): void {
    Chunk::query()->delete();
    config(['chatvia.embeddings.dimensions' => 4096]);

    settingsPage($this->admin, ManageAiSettings::class)
        ->assertFormFieldEnabled('embedding_provider')
        ->fillForm(['embedding_provider' => 'mistral'])
        ->call('save')
        ->assertHasFormErrors(['embedding_provider']);

    expect(storedSetting(AiSettings::class, 'embedding_provider'))->toBeNull();
});

it('locks the embedding provider and models once a source is trained', function (): void {
    $source = AgentFileFactory::new()->create(['workspace_id' => $this->admin->workspace()->id]);
    Chunk::create([
        'workspace_id' => $this->admin->workspace()->id,
        'chunkable_type' => $source->getMorphClass(),
        'chunkable_id' => $source->getKey(),
        'chunk_index' => 1,
        'chunk' => 'Trained text.',
        'tokens' => 2,
        'embedding' => '['.implode(',', array_fill(0, 1024, 0)).']',
        'embedding_bm25' => Bm25Tokenizer::expression('Trained text.'),
        'checksum' => Str::random(32),
    ]);

    settingsPage($this->admin, ManageAiSettings::class)
        ->assertFormFieldDisabled('embedding_provider');

    settingsPage($this->admin, ManageAiSettings::class)
        ->assertFormFieldDisabled('scaleway_embedding_model')
        ->assertFormFieldEnabled('scaleway_rerank_model');
});

it('picks the model of each job from the models that may be used', function (): void {
    $fast = testLlm('fast-model');
    $off = testLlm('off-model', attributes: ['is_enabled' => false]);

    settingsPage($this->admin, ManageAiSettings::class)
        ->fillForm(['fast_model' => $off->external_name])
        ->call('save')
        ->assertHasFormErrors(['fast_model']);

    settingsPage($this->admin, ManageAiSettings::class)
        ->fillForm(['fast_model' => $fast->external_name])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(storedSetting(AiSettings::class, 'fast_model'))->toBe($fast->external_name)
        ->and(config('chatvia.ai.roles.fast'))->toBe($fast->external_name);
});

it('sends a test email with the saved settings', function (): void {
    Mail::fake();
    Filament::setCurrentPanel('admin');

    Livewire::actingAs($this->admin, 'admin')
        ->test(ManageMailSettings::class)
        ->callAction('sendTestEmail')
        ->assertNotified('Test email sent');
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
