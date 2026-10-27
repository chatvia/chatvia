<?php

use App\Admin\Clusters\Settings\Pages\ManagePlatformSettings;
use App\Admin\Settings\PlatformSettings;

/**
 * Settings → Platform → Sentry: where the server's and the browser's errors
 * go. Each DSN is saved into the config key its side reads.
 */
beforeEach(function (): void {
    $this->admin = adminUser();
});

it('saves each DSN and the environment into the config Sentry reads', function (): void {
    adminPage(ManagePlatformSettings::class, $this->admin)
        ->fillForm([
            'sentry_dsn' => 'https://abc123@o1.ingest.sentry.io/11',
            'sentry_browser_dsn' => 'https://def456@o1.ingest.sentry.io/22',
            'sentry_environment' => 'staging',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(savedSetting(PlatformSettings::class, 'sentry_dsn'))->toBe('https://abc123@o1.ingest.sentry.io/11')
        ->and(config('sentry.dsn'))->toBe('https://abc123@o1.ingest.sentry.io/11')
        ->and(config('chatvia.sentry.browser_dsn'))->toBe('https://def456@o1.ingest.sentry.io/22')
        ->and(config('sentry.environment'))->toBe('staging');
});

it('clears both DSNs when the fields are emptied', function (): void {
    adminPage(ManagePlatformSettings::class, $this->admin)
        ->fillForm(['sentry_dsn' => 'https://abc123@o1.ingest.sentry.io/11', 'sentry_browser_dsn' => 'https://def456@o1.ingest.sentry.io/22'])
        ->call('save')
        ->assertHasNoFormErrors();

    adminPage(ManagePlatformSettings::class, $this->admin)
        ->fillForm(['sentry_dsn' => '', 'sentry_browser_dsn' => '', 'sentry_environment' => ''])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(savedSetting(PlatformSettings::class, 'sentry_dsn'))->toBeNull()
        ->and(config('chatvia.sentry.browser_dsn'))->toBeNull();
});

it('refuses a DSN that is not an address', function (): void {
    adminPage(ManagePlatformSettings::class, $this->admin)
        ->fillForm(['sentry_dsn' => 'not a dsn'])
        ->call('save')
        ->assertHasFormErrors(['sentry_dsn']);
});
