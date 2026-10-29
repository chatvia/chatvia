<?php

use App\Admin\Settings\BrandSettings;
use App\Admin\Settings\KnowledgeSettings;
use App\Admin\Settings\MailSettings;
use App\Admin\Settings\SettingsConfig;
use App\Admin\Settings\SignInSettings;
use App\Admin\Settings\WorkspaceSettings;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Spatie\LaravelSettings\SettingsRepositories\SettingsRepository;
use Tests\Support\License\LicenseKeys;

/**
 * The values saved in the admin panel are stored with spatie/laravel-settings
 * (secrets encrypted) and copied into config, so the code that reads config
 * needs no change. A blank value keeps the config default. A license key
 * activated for this domain lifts the free-use limits, lets the credit be
 * hidden and, for an Enterprise key, unlocks the Enterprise features.
 */
function scConfig(): SettingsConfig
{
    return app(SettingsConfig::class);
}

it('maps every settings property to a config key that exists and to a settings migration row', function (): void {
    $repository = app(SettingsRepository::class);

    foreach (scConfig()->classes() as $class) {
        foreach ($class::configKeys() as $property => $keys) {
            expect($keys)->not->toBeEmpty("{$class}::\${$property} has no config key");

            foreach ($keys as $key) {
                expect(config()->has($key->key))->toBeTrue("{$class}::\${$property} → {$key->key}");
            }
        }

        $stored = array_keys($repository->getPropertiesInGroup($class::group()));

        expect($stored)->toEqualCanonicalizing(array_keys($class::configKeys()), "rows of {$class::group()}");
    }
});

it('copies a saved value into config at once, keeps the default while blank, and stores secrets encrypted', function (): void {
    $default = config('services.firecrawl.endpoint');
    config(['app.url' => 'https://chat.example.com']);
    // Behind a proxy that ends TLS the request is plain http while the app boots.
    app('url')->forceScheme('http');

    $mail = app(MailSettings::class);
    $mail->smtp_host = 'smtp.example.com';
    $mail->smtp_port = 587;
    $mail->save();
    $workspaces = app(WorkspaceSettings::class);
    $workspaces->registration_enabled = false;
    $workspaces->new_workspace_message_limit = 0;
    $workspaces->save();
    $knowledge = app(KnowledgeSettings::class);
    $knowledge->firecrawl_endpoint = 'https://firecrawl.internal/v2';
    $knowledge->save();
    $savedEndpoint = config('services.firecrawl.endpoint');
    $knowledge->firecrawl_endpoint = null;
    $knowledge->save();
    $signIn = app(SignInSettings::class);
    $signIn->google_client_id = 'visible-id';
    $signIn->google_client_secret = 'top-secret-value';
    $signIn->save();

    $payload = fn (string $name): string => DB::table('settings')->where('group', 'sign_in')->where('name', $name)->value('payload');

    expect(config('mail.mailers.smtp.host'))->toBe('smtp.example.com')
        ->and(config('mail.mailers.smtp.port'))->toBe(587)
        ->and(config('chatvia.registration_enabled'))->toBeFalse()
        ->and(config('chatvia.message_limits.new_workspace'))->toBe(0)
        ->and($savedEndpoint)->toBe('https://firecrawl.internal/v2')
        ->and(config('services.firecrawl.endpoint'))->toBe($default)
        ->and(scConfig()->defaultOf(SignInSettings::class, 'google_redirect'))->toBe('https://chat.example.com/auth/google/callback')
        ->and($payload('google_client_secret'))->not->toContain('top-secret-value')
        ->and(Crypt::decrypt(json_decode($payload('google_client_secret'))))->toBe('top-secret-value')
        ->and(json_decode($payload('google_client_id')))->toBe('visible-id')
        ->and(SignInSettings::secrets())->toBe(['google_client_secret', 'microsoft_client_secret', 'github_client_secret', 'slack_client_secret']);
});

it('lifts the free-use limits, lets the credit be hidden and unlocks Enterprise only with a key activated for this domain', function (?string $key, bool $creditOn, string $checkedUrl, bool $licensed, bool $enterprise): void {
    LicenseKeys::trust();
    config(['app.url' => 'https://chat.acme.com']);
    $defaultChunkSize = config('chatvia.ai.chunking.size');
    $brand = app(BrandSettings::class);
    $brand->powered_by_enabled = $creditOn;
    $brand->license_key = $key;
    $brand->save();
    $knowledge = app(KnowledgeSettings::class);
    $knowledge->chunk_size = $defaultChunkSize + 123;
    $knowledge->save();

    config(['app.url' => $checkedUrl]);
    scConfig()->apply();

    expect(config('chatvia.license.licensed'))->toBe($licensed)
        ->and(config('chatvia.license.enterprise'))->toBe($enterprise)
        ->and(config('chatvia.powered_by'))->toBe($creditOn || ! $licensed)
        ->and(config('chatvia.ai.chunking.size'))->toBe($licensed ? $defaultChunkSize + 123 : $defaultChunkSize)
        ->and(app(KnowledgeSettings::class)->chunk_size)->toBe($defaultChunkSize + 123);
})->with([
    'Enterprise key for this domain' => fn (): array => [LicenseKeys::enterpriseFor('chat.acme.com'), false, 'https://chat.acme.com', true, true],
    'Enterprise key on its last day' => fn (): array => [LicenseKeys::enterpriseUntil(now()->toDateString(), 'chat.acme.com'), false, 'https://chat.acme.com', true, true],
    'Pro key for this domain' => fn (): array => [LicenseKeys::activatedFor('chat.acme.com'), false, 'https://chat.acme.com', true, false],
    'Pro key for this domain, credit switched on' => fn (): array => [LicenseKeys::activatedFor('chat.acme.com'), true, 'https://chat.acme.com', true, false],
    'Enterprise key past its last day' => fn (): array => [LicenseKeys::enterpriseUntil(now()->subDay()->toDateString(), 'chat.acme.com'), false, 'https://chat.acme.com', false, false],
    'Enterprise key for another domain' => fn (): array => [LicenseKeys::enterpriseFor('chat.other.com'), false, 'https://chat.acme.com', false, false],
    'Pro key, after the app moved to another domain' => fn (): array => [LicenseKeys::activatedFor('chat.acme.com'), false, 'https://chat.moved.com', false, false],
    'a key as bought, not activated' => [LicenseKeys::BOUGHT_KEY, false, 'https://chat.acme.com', false, false],
    'not a key' => ['cvl1.forged.key', false, 'https://chat.acme.com', false, false],
    'no key' => [null, false, 'https://chat.acme.com', false, false],
]);
