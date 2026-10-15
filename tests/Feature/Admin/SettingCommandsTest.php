<?php

use App\Admin\Settings\Actions\SetSettingAction;
use App\Admin\Settings\MailSettings;

/**
 * Admin-panel settings can be set from the command line (`app:setting`), so
 * installers and scripts can set up an install without the panel, and listed
 * (`app:settings`). The action itself is in
 * tests/Unit/Admin/Settings/SetSettingActionTest.php.
 */
it('sets a value with app:setting', function (): void {
    $this->artisan('app:setting', ['key' => 'mail.from_address', 'value' => 'hello@acme.example'])
        ->expectsOutput('mail.from_address saved.')
        ->assertSuccessful();

    expect(savedSetting(MailSettings::class, 'from_address'))->toBe('hello@acme.example');

    $this->artisan('app:setting', ['key' => 'mail.from_address', '--unset' => true])->assertSuccessful();

    expect(savedSetting(MailSettings::class, 'from_address'))->toBeNull();
});

it('fails app:setting on an unknown key', function (): void {
    $this->artisan('app:setting', ['key' => 'mail.nope', 'value' => 'x'])
        ->expectsOutputToContain('Unknown setting [mail.nope]')
        ->assertFailed();
});

it('lists the settings with app:settings without showing secrets', function (): void {
    app(SetSettingAction::class)->execute('ai_providers.cohere_api_key', 'co-hidden-value');

    $this->artisan('app:settings', ['group' => 'ai_providers'])
        ->expectsOutputToContain('ai_providers.cohere_api_key')
        ->doesntExpectOutputToContain('co-hidden-value')
        ->assertSuccessful();
});
