<?php

use App\Admin\Clusters\Settings\Pages\ManageMailSettings;
use App\Admin\Pages\Setup;
use App\Admin\Settings\MailProvider;
use App\Admin\Settings\MailSettings;
use App\Admin\Settings\PlatformSettings;
use App\Admin\Settings\SettingsConfig;
use App\Identity\Workspace\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Http;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/**
 * Settings → Email offers every mailer Laravel ships with in one dropdown
 * ({@see MailProvider}) and asks only for the chosen one's settings. The
 * admin setup offers the same list.
 */
beforeEach(function (): void {
    $this->admin = User::factory()->admin()->create()->fresh();
    $this->admin->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP');
    Filament::setCurrentPanel('admin');
});

function mailPage(User $admin): Testable
{
    return Livewire::actingAs($admin, 'admin')->test(ManageMailSettings::class);
}

function mailSetting(string $property): mixed
{
    app()->forgetScopedInstances();

    return app(MailSettings::class)->{$property};
}

it('offers every mailer Laravel ships with, each with a mail.mailers entry', function (): void {
    expect(array_column(MailProvider::cases(), 'value'))->toBe(['smtp', 'ses', 'mailgun', 'postmark', 'resend', 'cloudflare', 'sendmail', 'log']);

    foreach (MailProvider::cases() as $provider) {
        expect(config("mail.mailers.{$provider->value}.transport"))->toBe($provider->value);

        foreach ($provider->fields() as $field) {
            expect(MailSettings::configKeys())->toHaveKey($field->property);
        }
    }
});

it('shows only the settings of the provider picked', function (): void {
    mailPage($this->admin)
        ->fillForm(['mailer' => 'mailgun'])
        ->assertFormFieldVisible('mailgun_domain')
        ->assertFormFieldVisible('mailgun_secret')
        ->assertFormFieldHidden('smtp_host')
        ->assertFormFieldHidden('postmark_token')
        ->fillForm(['mailer' => 'sendmail'])
        ->assertFormFieldHidden('mailgun_domain')
        ->assertFormFieldHidden('smtp_host');
});

it('saves the provider picked and its settings, and the mailer uses them', function (): void {
    mailPage($this->admin)
        ->fillForm([
            'mailer' => 'mailgun',
            'mailgun_domain' => 'mg.example.com',
            'mailgun_secret' => 'key-123',
            'mailgun_endpoint' => 'api.eu.mailgun.net',
            'from_address' => 'chat@example.com',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(mailSetting('mailer'))->toBe('mailgun')
        ->and(mailSetting('mailgun_secret'))->toBe('key-123')
        ->and(config('mail.default'))->toBe('mailgun')
        ->and(config('services.mailgun'))->toMatchArray(['domain' => 'mg.example.com', 'secret' => 'key-123', 'endpoint' => 'api.eu.mailgun.net']);

    Http::fake();

    // The Mailgun transport builds from what was saved.
    expect((string) app('mail.manager')->mailer('mailgun')->getSymfonyTransport())->toContain('api.eu.mailgun.net');
});

it('asks for the picked provider\'s required settings only', function (): void {
    mailPage($this->admin)
        ->fillForm(['mailer' => 'postmark'])
        ->call('save')
        ->assertHasFormErrors(['postmark_token'])
        ->assertHasNoFormErrors(['smtp_host', 'mailgun_domain']);

    expect(mailSetting('mailer'))->toBeNull();
});

it('keeps a mailer from .env that is not in the list', function (): void {
    // As if .env set it: the defaults are read when settings are first applied.
    config(['mail.default' => 'failover']);
    app()->forgetInstance(SettingsConfig::class);

    mailPage($this->admin)
        ->assertFormSet(['mailer' => 'failover'])
        ->fillForm(['from_address' => 'chat@example.com'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(mailSetting('mailer'))->toBeNull()
        ->and(mailSetting('from_address'))->toBe('chat@example.com');
});

it('offers the same providers in the admin setup and saves the one picked', function (): void {
    $platform = app(PlatformSettings::class);
    $platform->setup_completed_at = null;
    $platform->save();

    Http::fake(['openrouter.ai/api/v1/models' => Http::response(['data' => [['id' => 'some/model', 'name' => 'Some model']]])]);

    Livewire::actingAs($this->admin, 'admin')->test(Setup::class)
        ->fillForm(['chat_kind' => 'openrouter', 'chat_api_key' => 'sk-or-test'])
        ->call('loadModels')
        ->fillForm([
            'chat_model' => 'some/model',
            'embedding_provider' => 'cohere',
            'embedding_api_key' => 'co-test',
            'mailer' => 'postmark',
            'from_address' => 'chat@example.com',
        ])
        ->assertFormFieldVisible('postmark_token')
        ->assertFormFieldHidden('smtp_host')
        ->fillForm(['postmark_token' => 'pm-token'])
        ->call('complete')
        ->assertHasNoFormErrors();

    expect(mailSetting('mailer'))->toBe('postmark')
        ->and(mailSetting('postmark_token'))->toBe('pm-token')
        ->and(mailSetting('from_address'))->toBe('chat@example.com');
});
