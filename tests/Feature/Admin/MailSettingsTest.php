<?php

use App\Admin\Clusters\Settings\Pages\ManageMailSettings;
use App\Admin\Settings\MailProvider;
use App\Admin\Settings\MailSettings;
use App\Admin\Settings\SettingsConfig;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Http;

/**
 * Settings → Email offers every mailer Laravel ships with in one dropdown
 * ({@see MailProvider}) and asks only for the chosen one's settings.
 */
beforeEach(function (): void {
    $this->admin = adminUser();
    Filament::setCurrentPanel('admin');
});

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
    adminPage(ManageMailSettings::class, $this->admin)
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
    adminPage(ManageMailSettings::class, $this->admin)
        ->fillForm([
            'mailer' => 'mailgun',
            'mailgun_domain' => 'mg.example.com',
            'mailgun_secret' => 'key-123',
            'mailgun_endpoint' => 'api.eu.mailgun.net',
            'from_address' => 'chat@example.com',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(savedSetting(MailSettings::class, 'mailer'))->toBe('mailgun')
        ->and(savedSetting(MailSettings::class, 'mailgun_secret'))->toBe('key-123')
        ->and(config('mail.default'))->toBe('mailgun')
        ->and(config('services.mailgun'))->toMatchArray(['domain' => 'mg.example.com', 'secret' => 'key-123', 'endpoint' => 'api.eu.mailgun.net']);

    Http::fake();

    // The Mailgun transport builds from what was saved.
    expect((string) app('mail.manager')->mailer('mailgun')->getSymfonyTransport())->toContain('api.eu.mailgun.net');
});

it('asks for the picked provider\'s required settings only', function (): void {
    adminPage(ManageMailSettings::class, $this->admin)
        ->fillForm(['mailer' => 'postmark'])
        ->call('save')
        ->assertHasFormErrors(['postmark_token'])
        ->assertHasNoFormErrors(['smtp_host', 'mailgun_domain']);

    expect(savedSetting(MailSettings::class, 'mailer'))->toBeNull();
});

it('keeps a mailer from .env that is not in the list', function (): void {
    // As if .env set it: the defaults are read when settings are first applied.
    config(['mail.default' => 'failover']);
    app()->forgetInstance(SettingsConfig::class);

    adminPage(ManageMailSettings::class, $this->admin)
        ->assertFormSet(['mailer' => 'failover'])
        ->fillForm(['from_address' => 'chat@example.com'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(savedSetting(MailSettings::class, 'mailer'))->toBeNull()
        ->and(savedSetting(MailSettings::class, 'from_address'))->toBe('chat@example.com');
});
