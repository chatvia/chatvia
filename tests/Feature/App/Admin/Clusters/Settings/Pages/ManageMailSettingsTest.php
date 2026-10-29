<?php

use App\Admin\Clusters\Settings\Pages\ManageMailSettings;
use App\Admin\Settings\MailSettings;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Http;

/**
 * Settings → Email: the chosen mailer and its settings are saved and used
 * to build the mailer, and a save without the chosen mailer's required
 * settings is refused.
 */
beforeEach(function (): void {
    $this->admin = adminUser();
    Filament::setCurrentPanel('admin');
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
    Http::fake();

    expect(savedSetting(MailSettings::class, 'mailer'))->toBe('mailgun')
        ->and(savedSetting(MailSettings::class, 'mailgun_secret'))->toBe('key-123')
        ->and(config('mail.default'))->toBe('mailgun')
        ->and(config('services.mailgun'))->toMatchArray(['domain' => 'mg.example.com', 'secret' => 'key-123', 'endpoint' => 'api.eu.mailgun.net'])
        ->and((string) app('mail.manager')->mailer('mailgun')->getSymfonyTransport())->toContain('api.eu.mailgun.net');
});

it('refuses a mailer without its required settings and saves nothing', function (): void {
    adminPage(ManageMailSettings::class, $this->admin)
        ->fillForm(['mailer' => 'postmark'])
        ->call('save')
        ->assertHasFormErrors(['postmark_token'])
        ->assertHasNoFormErrors(['smtp_host', 'mailgun_domain']);

    expect(savedSetting(MailSettings::class, 'mailer'))->toBeNull();
});
