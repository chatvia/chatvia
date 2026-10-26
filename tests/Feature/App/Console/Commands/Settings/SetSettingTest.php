<?php

use App\Admin\Settings\MailSettings;

/**
 * `chatvia:settings:set` sets an admin-panel setting from the command line,
 * so installers and scripts can set up an install without the panel. The
 * action itself is in tests/Unit/App/Admin/Settings/Actions/SetSettingActionTest.php.
 */
it('saves a value', function (): void {
    $this->artisan('chatvia:settings:set', ['key' => 'mail.from_address', 'value' => 'hello@acme.example'])
        ->expectsOutput('mail.from_address saved.')
        ->assertSuccessful();

    expect(savedSetting(MailSettings::class, 'from_address'))->toBe('hello@acme.example');
});

it('asks for the key when it is left out', function (): void {
    $this->artisan('chatvia:settings:set', ['value' => 'hello@acme.example'])
        ->expectsQuestion('Setting key', 'mail.from_address')
        ->expectsOutput('mail.from_address saved.')
        ->assertSuccessful();

    expect(savedSetting(MailSettings::class, 'from_address'))->toBe('hello@acme.example');
});

it('removes a saved value with --unset', function (): void {
    saveSettings(MailSettings::class, ['from_address' => 'hello@acme.example']);

    $this->artisan('chatvia:settings:set', ['key' => 'mail.from_address', '--unset' => true])->assertSuccessful();

    expect(savedSetting(MailSettings::class, 'from_address'))->toBeNull();
});

it('fails on an unknown key', function (): void {
    $this->artisan('chatvia:settings:set', ['key' => 'mail.nope', 'value' => 'x'])
        ->expectsOutputToContain('Unknown setting [mail.nope]')
        ->assertFailed();
});
