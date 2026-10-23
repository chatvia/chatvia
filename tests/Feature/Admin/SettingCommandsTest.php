<?php

use App\Admin\Settings\Actions\SetSettingAction;
use App\Admin\Settings\MailSettings;

/**
 * Admin-panel settings can be set from the command line (`chatvia:settings:set`), so
 * installers and scripts can set up an install without the panel, and listed
 * (`chatvia:settings:list`). The action itself is in
 * tests/Unit/App/Admin/Settings/SetSettingActionTest.php.
 */
it('sets a value with chatvia:settings:set', function (): void {
    $this->artisan('chatvia:settings:set', ['key' => 'mail.from_address', 'value' => 'hello@acme.example'])
        ->expectsOutput('mail.from_address saved.')
        ->assertSuccessful();

    expect(savedSetting(MailSettings::class, 'from_address'))->toBe('hello@acme.example');

    $this->artisan('chatvia:settings:set', ['key' => 'mail.from_address', '--unset' => true])->assertSuccessful();

    expect(savedSetting(MailSettings::class, 'from_address'))->toBeNull();
});

it('fails chatvia:settings:set on an unknown key', function (): void {
    $this->artisan('chatvia:settings:set', ['key' => 'mail.nope', 'value' => 'x'])
        ->expectsOutputToContain('Unknown setting [mail.nope]')
        ->assertFailed();
});

it('lists the settings with chatvia:settings:list without showing secrets', function (): void {
    app(SetSettingAction::class)->execute('ai_providers.cohere_api_key', 'co-hidden-value');

    $this->artisan('chatvia:settings:list', ['group' => 'ai_providers'])
        ->expectsOutputToContain('ai_providers.cohere_api_key')
        ->doesntExpectOutputToContain('co-hidden-value')
        ->assertSuccessful();
});
