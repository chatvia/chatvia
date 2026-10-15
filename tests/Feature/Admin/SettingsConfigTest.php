<?php

use App\Admin\Settings\AdminSettings;
use App\Admin\Settings\AiSettings;
use App\Admin\Settings\BrandSettings;
use App\Admin\Settings\ChannelSettings;
use App\Admin\Settings\KnowledgeSettings;
use App\Admin\Settings\MailSettings;
use App\Admin\Settings\SettingsConfig;
use App\Admin\Settings\SignInSettings;
use App\Admin\Settings\WorkspaceSettings;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Spatie\LaravelSettings\SettingsRepositories\SettingsRepository;
use Tests\Support\LicenseKeys;

/**
 * The values saved in the admin panel are stored with spatie/laravel-settings
 * and copied into config, so the code that reads config needs no change. A
 * blank value keeps the config default.
 */
function settingsConfig(): SettingsConfig
{
    return app(SettingsConfig::class);
}

it('maps every settings property to a config key that exists', function (): void {
    foreach (settingsConfig()->classes() as $class) {
        foreach ($class::configKeys() as $property => $keys) {
            expect($keys)->not->toBeEmpty("{$class}::\${$property} has no config key");

            foreach ($keys as $key) {
                expect(config()->has($key->key))->toBeTrue("{$class}::\${$property} → {$key->key}");
            }
        }
    }
});

it('has a settings migration row for every property', function (): void {
    $repository = app(SettingsRepository::class);

    foreach (settingsConfig()->classes() as $class) {
        $stored = array_keys($repository->getPropertiesInGroup($class::group()));

        expect($stored)->toEqualCanonicalizing(array_keys($class::configKeys()), "rows of {$class::group()}");
    }
});

it('puts a saved value into config right away', function (): void {
    $settings = app(MailSettings::class);
    $settings->smtp_host = 'smtp.example.com';
    $settings->smtp_port = 587;
    $settings->save();

    expect(config('mail.mailers.smtp.host'))->toBe('smtp.example.com')
        ->and(config('mail.mailers.smtp.port'))->toBe(587);
});

it('keeps the config default while nothing is saved, and restores it when a value is removed', function (): void {
    $default = config('services.firecrawl.endpoint');

    $settings = app(KnowledgeSettings::class);
    $settings->firecrawl_endpoint = 'https://firecrawl.internal/v2';
    $settings->save();
    expect(config('services.firecrawl.endpoint'))->toBe('https://firecrawl.internal/v2');

    $settings->firecrawl_endpoint = null;
    $settings->save();
    expect(config('services.firecrawl.endpoint'))->toBe($default);
});

it('treats a saved false or 0 as a value, not as blank', function (): void {
    $settings = app(WorkspaceSettings::class);
    $settings->registration_enabled = false;
    $settings->new_workspace_message_limit = 0;
    $settings->save();

    expect(config('chatvia.registration_enabled'))->toBeFalse()
        ->and(config('chatvia.message_limits.new_workspace'))->toBe(0);
});

it('uses the callback URL of this app for a redirect with nothing saved', function (): void {
    $appUrl = rtrim((string) config('app.url'), '/');

    expect(config('services.google.redirect'))->toBe($appUrl.'/auth/google/callback')
        ->and(config('services.meta.instagram.redirect'))->toBe($appUrl.'/my/channels/instagram/oauth/callback')
        ->and(settingsConfig()->defaultOf(ChannelSettings::class, 'messenger_redirect'))->toBe($appUrl.'/my/channels/messenger/oauth/callback');
});

it('builds a default callback on the https app URL, whatever the request scheme', function (): void {
    // Behind a proxy that ends TLS the request is plain http while the app boots.
    config(['app.url' => 'https://chat.example.com']);
    app('url')->forceScheme('http');

    expect(settingsConfig()->defaultOf(SignInSettings::class, 'google_redirect'))
        ->toBe('https://chat.example.com/auth/google/callback');
});

it('hides the credit only with a license key activated for this domain', function (?string $key, bool $shown): void {
    LicenseKeys::trust();
    config(['app.url' => 'https://chat.acme.com']);

    $settings = app(BrandSettings::class);
    $settings->powered_by_enabled = false;
    $settings->license_key = $key;
    $settings->save();

    expect(config('chatvia.brand.powered_by.enabled'))->toBe($shown);
})->with([
    'activated for this domain' => fn (): array => [LicenseKeys::activatedFor('chat.acme.com'), false],
    'no key' => [null, true],
    'a key as bought, not activated' => [LicenseKeys::BOUGHT_KEY, true],
    'activated for another domain' => fn (): array => [LicenseKeys::activatedFor('chat.other.com'), true],
    'not a key' => ['cvl1.forged.key', true],
]);

it('keeps the credit on when the domain no longer matches the key', function (): void {
    LicenseKeys::trust();
    config(['app.url' => 'https://chat.acme.com']);

    $settings = app(BrandSettings::class);
    $settings->powered_by_enabled = false;
    $settings->license_key = LicenseKeys::activatedFor('chat.acme.com');
    $settings->save();

    expect(config('chatvia.brand.powered_by.enabled'))->toBeFalse();

    config(['app.url' => 'https://chat.moved.com']);
    settingsConfig()->apply();

    expect(config('chatvia.brand.powered_by.enabled'))->toBeTrue();
});

it('shows the credit when it is on, with or without a key', function (): void {
    LicenseKeys::trust();
    config(['app.url' => 'https://chat.acme.com']);

    $settings = app(BrandSettings::class);
    $settings->powered_by_enabled = true;
    $settings->license_key = LicenseKeys::activatedFor('chat.acme.com');
    $settings->save();

    expect(config('chatvia.brand.powered_by.enabled'))->toBeTrue();
});

it('stores secrets encrypted and other values in plain JSON', function (): void {
    $settings = app(SignInSettings::class);
    $settings->google_client_id = 'visible-id';
    $settings->google_client_secret = 'top-secret-value';
    $settings->save();

    $payload = fn (string $name): string => DB::table('settings')->where('group', 'sign_in')->where('name', $name)->value('payload');

    expect($payload('google_client_secret'))->not->toContain('top-secret-value')
        ->and(Crypt::decrypt(json_decode($payload('google_client_secret'))))->toBe('top-secret-value')
        ->and(json_decode($payload('google_client_id')))->toBe('visible-id')
        ->and(SignInSettings::secrets())->toBe(['google_client_secret', 'microsoft_client_secret', 'github_client_secret', 'slack_client_secret']);
});

it('applies a change made elsewhere before a queued job runs', function (): void {
    // Another process saved a new key: the row changed, this process has
    // not seen it yet.
    DB::table('settings')->where('group', 'ai_providers')->where('name', 'mistral_api_key')
        ->update(['payload' => json_encode(Crypt::encrypt('saved-elsewhere'))]);
    cache()->flush();
    // A queue worker forgets scoped instances before each job.
    app()->forgetScopedInstances();

    // The closure is serialized onto the (sync) queue, so it reports back
    // through the cache.
    dispatch(function (): void {
        cache()->put('mistral-key-seen-by-job', config('services.mistral.api_key'));
    });

    expect(cache('mistral-key-seen-by-job'))->toBe('saved-elsewhere');
});

it('casts input to the property type and turns blank or invalid input into null', function (string $class, string $property, mixed $input, mixed $expected): void {
    /** @var class-string<AdminSettings> $class */
    expect($class::cast($property, $input))->toBe($expected);
})->with([
    'trimmed text' => [MailSettings::class, 'smtp_host', '  smtp.example.com ', 'smtp.example.com'],
    'blank text' => [MailSettings::class, 'smtp_host', '   ', null],
    'integer' => [MailSettings::class, 'smtp_port', '587', 587],
    'not an integer' => [MailSettings::class, 'smtp_port', 'abc', null],
    'float' => [KnowledgeSettings::class, 'relevance_threshold', '0.35', 0.35],
    'toggle off' => [KnowledgeSettings::class, 'hybrid_search', '0', false],
    'toggle on' => [KnowledgeSettings::class, 'hybrid_search', true, true],
    'select' => [AiSettings::class, 'reranker', 'cohere', 'cohere'],
]);

it('lets the search toggle turn keyword search off', function (): void {
    expect(config('chatvia.vector.search.hybrid'))->toBeTrue();

    $settings = app(KnowledgeSettings::class);
    $settings->hybrid_search = false;
    $settings->save();

    expect(config('chatvia.vector.search.hybrid'))->toBeFalse();
});
