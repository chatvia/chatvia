<?php

use App\Admin\Clusters\Settings\Pages\ManageAiModelSettings;
use App\Admin\Clusters\Settings\Pages\ManageAiProviderSettings;
use App\Admin\Clusters\Settings\Pages\ManageChannelSettings;
use App\Admin\Clusters\Settings\Pages\ManageConnectorSettings;
use App\Admin\Clusters\Settings\Pages\ManageKnowledgeSettings;
use App\Admin\Clusters\Settings\Pages\ManageMailSettings;
use App\Admin\Clusters\Settings\Pages\ManagePlatformSettings;
use App\Admin\Clusters\Settings\Pages\ManageSecuritySettings;
use App\Admin\Clusters\Settings\Pages\ManageSignInSettings;
use App\Admin\Clusters\Settings\Pages\ManageStorageSettings;
use App\Admin\Clusters\Settings\Pages\ManageWorkspaceSettings;
use App\Admin\Clusters\Settings\Pages\SettingsOverview;
use App\Admin\Clusters\Settings\SettingsCluster;
use App\Admin\Settings\AiProviderSettings;
use App\Admin\Settings\AiSettings;
use App\Admin\Settings\ChannelSettings;
use App\Admin\Settings\ConnectorSettings;
use App\Admin\Settings\MailSettings;
use App\Admin\Settings\PlatformSettings;
use App\Admin\Settings\SignInSettings;
use App\Admin\Settings\ToolRecording;
use App\Admin\Settings\WorkspaceSettings;
use App\Identity\Workspace\Models\User;
use Database\Factories\AgentFileFactory;
use Domain\Knowledge\Ingestion\Models\Chunk;
use Domain\Knowledge\Retrieval\Services\Bm25Tokenizer;
use Domain\Llm\Enums\AiProvider;
use Domain\Llm\Enums\LlmTask;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
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

    expect(collect(SettingsCluster::getClusteredComponents())->map(fn (string $page): string => $page::getSlug())->sort()->values()->all())
        ->toBe(['ai-models', 'ai-providers', 'branding', 'channels', 'connectors', 'email', 'knowledge', 'overview', 'platform', 'security', 'sign-in', 'storage', 'workspaces']);
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

    $this->get(SettingsOverview::getUrl())->assertSee('GitHub is not finished');

    $settings->github_client_secret = 'secret';
    $settings->save();

    $this->get(SettingsOverview::getUrl())->assertSee('2 set up');
});

it('groups the overview tiles by topic', function (): void {
    $this->actingAs($this->admin, 'admin')
        ->get(SettingsOverview::getUrl())
        ->assertSeeInOrder([
            'Access and security', 'Sign-in', 'Workspaces', 'Security',
            'AI and knowledge', 'AI providers', 'AI models', 'Knowledge',
            'Connections', 'Channels', 'Connectors',
            'Platform and brand', 'Platform', 'Email', 'Storage', 'Branding',
        ]);
});

it('keeps the services behind the built-in tools on Connectors, under Advanced', function (): void {
    $this->actingAs($this->admin, 'admin')
        ->get(ManageConnectorSettings::getUrl())
        ->assertOk()
        ->assertSeeInOrder(['GitHub', 'Technical options. The defaults suit most installs.', 'Perplexity', 'OpenWeather', 'ExchangeRate-API']);

    $this->get(ManagePlatformSettings::getUrl())->assertDontSee(['Perplexity', 'OpenWeather', 'ExchangeRate-API']);

    settingsPage($this->admin, ManageConnectorSettings::class)
        ->fillForm(['perplexity_base_url' => 'https://perplexity.example.com', 'github_client_id' => 'id', 'github_client_secret' => 'secret'])
        ->call('save')
        ->assertHasNoFormErrors();

    // The tile still counts the OAuth apps set up; the advanced addresses are not services.
    expect(storedSetting(ConnectorSettings::class, 'perplexity_base_url'))->toBe('https://perplexity.example.com')
        ->and(config('services.perplexity.base_url'))->toBe('https://perplexity.example.com')
        ->and(app(ManageConnectorSettings::class)->overviewStatus())->toBe(['1 set up', 'success']);
});

it('marks a page of optional services that has none set up as optional', function (): void {
    $this->actingAs($this->admin, 'admin')
        ->get(SettingsOverview::getUrl())
        ->assertSeeInOrder(['Sign-in', 'Optional'])
        ->assertSee(['Sign-up open', 'Bot check off', 'Not sending email yet'])
        ->assertDontSee('of 4 set up');
});

it('lists the advanced cards last, closed, under their own heading', function (): void {
    $this->actingAs($this->admin, 'admin')
        ->get(ManageKnowledgeSettings::getUrl())
        ->assertOk()
        ->assertSeeInOrder(['File types', 'Follow-up questions', 'Technical options. The defaults suit most installs.', 'knowledge-splitting', 'knowledge-try_it', 'knowledge-search_tuning'])
        ->assertSeeText('Save changes')
        ->assertDontSee('cv-outline__save');
});

it('lets an admin without two-factor authentication change the values', function (): void {
    $settings = app(SignInSettings::class);
    $settings->google_client_id = 'client-id-shown';
    $settings->save();

    $this->actingAs($this->admin, 'admin')
        ->get(ManageSignInSettings::getUrl())
        ->assertOk()
        ->assertSee('client-id-shown')
        ->assertDontSee('Set up two-factor authentication first');

    settingsPage($this->admin, ManageSignInSettings::class)
        ->fillForm(['google_client_id' => 'client-id-changed'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(storedSetting(SignInSettings::class, 'google_client_id'))->toBe('client-id-changed');
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
    Http::fake(['api.scaleway.ai/*' => Http::response(['data' => [['id' => 'gpt-oss-120b']]])]);

    settingsPage($this->admin, ManageAiProviderSettings::class)
        ->fillForm(['scaleway_api_key' => 'scw-key'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(storedSetting(AiProviderSettings::class, 'scaleway_api_key'))->toBe('scw-key')
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

it('keeps the tracking code as typed, and an empty box turns it off', function (): void {
    $snippet = "<script defer data-site=\"acme\" src=\"https://stats.acme.example/js/script.js\"></script>\n<script>window.stats = window.stats || [];</script>";

    settingsPage($this->admin, ManagePlatformSettings::class)
        ->fillForm(['tracking_code' => $snippet])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(storedSetting(PlatformSettings::class, 'tracking_code'))->toBe($snippet)
        ->and(config('chatvia.tracking_code'))->toBe($snippet);

    settingsPage($this->admin, ManagePlatformSettings::class)
        ->assertSchemaStateSet(['tracking_code' => $snippet])
        ->fillForm(['tracking_code' => ''])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(storedSetting(PlatformSettings::class, 'tracking_code'))->toBeNull();
});

it('turns Spectra on, and keeps only counts while prompts and replies are not saved', function (): void {
    // phpunit.xml leaves Pulse out of the install, and Spectra in.
    settingsPage($this->admin, ManagePlatformSettings::class)
        ->assertSee(['Off in .env', 'PULSE_ENABLED=false in .env', 'Open Spectra'])
        // A number left blank shows its default (30 days) as a placeholder.
        ->assertSchemaStateSet(['spectra_enabled' => false, 'spectra_store_content' => true, 'spectra_retention_days' => null])
        ->fillForm(['spectra_enabled' => true, 'spectra_store_content' => false, 'spectra_retention_days' => 7])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(storedSetting(PlatformSettings::class, 'spectra_enabled'))->toBeTrue()
        ->and(config('spectra.enabled'))->toBeTrue()
        ->and(config('spectra.storage.store_prompts'))->toBeFalse()
        ->and(config('spectra.storage.store_responses'))->toBeFalse()
        ->and(config('spectra.storage.store_system_prompts'))->toBeFalse()
        ->and(config('chatvia.spectra.retention_days'))->toBe(7);
});

it('restarts Pulse\'s server stats when the Pulse switch changes', function (): void {
    // As if PULSE_ENABLED were true when the app booted.
    config(['pulse.enabled' => true]);
    app()->forgetInstance(ToolRecording::class);
    Artisan::spy();

    settingsPage($this->admin, ManagePlatformSettings::class)
        ->fillForm(['tracking_code' => '<script></script>'])
        ->call('save');

    Artisan::shouldNotHaveReceived('call', ['pulse:restart']);

    settingsPage($this->admin, ManagePlatformSettings::class)
        ->assertSchemaStateSet(['pulse_enabled' => true])
        ->fillForm(['pulse_enabled' => false])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(storedSetting(PlatformSettings::class, 'pulse_enabled'))->toBeFalse();
    Artisan::shouldHaveReceived('call')->with('pulse:restart')->once();
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

it('refuses a fewest-days value above the days to keep conversations', function (): void {
    settingsPage($this->admin, ManageWorkspaceSettings::class)
        ->fillForm(['retention_days' => 30, 'retention_floor_days' => 60])
        ->call('save')
        ->assertHasFormErrors(['retention_floor_days']);

    expect(storedSetting(WorkspaceSettings::class, 'retention_floor_days'))->toBeNull();
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
    connectAiProviders(AiProvider::Mistral);

    settingsPage($this->admin, ManageAiModelSettings::class)
        ->assertFormFieldEnabled('embedding_provider')
        ->fillForm(['embedding_provider' => 'mistral'])
        ->call('save')
        ->assertHasFormErrors(['embedding_provider']);

    expect(storedSetting(AiSettings::class, 'embedding_provider'))->toBeNull();
});

it('keeps the embedding provider and models open once a source is trained', function (): void {
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

    // A change warns and re-indexes (AiModelsPageTest) instead of being locked.
    settingsPage($this->admin, ManageAiModelSettings::class)
        ->assertFormFieldEnabled('embedding_provider')
        ->assertFormFieldEnabled('scaleway_embedding_model');
});

it('picks the model of each app task from the models that may be used', function (string $property, string $configKey): void {
    $picked = testLlm('picked-model');
    $off = testLlm('off-model', attributes: ['is_enabled' => false]);

    settingsPage($this->admin, ManageAiModelSettings::class)
        ->fillForm([$property => $off->external_name])
        ->call('save')
        ->assertHasFormErrors([$property]);

    settingsPage($this->admin, ManageAiModelSettings::class)
        ->fillForm([$property => $picked->external_name])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(storedSetting(AiSettings::class, $property))->toBe($picked->external_name)
        ->and(config($configKey))->toBe($picked->external_name);
})->with([
    'the model for app tasks' => ['task_model', 'chatvia.ai.tasks.default'],
    'one task' => ['guardrails_model', 'chatvia.ai.tasks.guardrails'],
]);

it('has a setting and a field for every app task', function (): void {
    $page = settingsPage($this->admin, ManageAiModelSettings::class);

    foreach (LlmTask::appTasks() as $task) {
        expect(AiSettings::propertyFor($task->configKey()))->toBe($task->settingsProperty());

        $page->assertFormFieldExists($task->settingsProperty());
    }
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

it('shows the S3 fields only while S3-compatible storage is chosen', function (): void {
    config(['filesystems.storage' => 'local']);

    settingsPage($this->admin, ManageStorageSettings::class)
        ->assertFormFieldHidden('s3_region')
        ->fillForm(['driver' => 's3'])
        ->assertFormFieldVisible('s3_region')
        ->assertFormFieldVisible('s3_bucket');
});

it('refuses a security log floor above the days to keep it', function (): void {
    settingsPage($this->admin, ManageSecuritySettings::class)
        ->fillForm(['security_audit_retention_days' => 30, 'security_audit_retention_floor_days' => 60])
        ->call('save')
        ->assertHasFormErrors(['security_audit_retention_floor_days']);
});
