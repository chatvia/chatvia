<?php

use App\Admin\Settings\MailSettings;

/**
 * `chatvia:settings:set` sets an admin-panel setting from the command line,
 * so installers and scripts can set up an install without the panel. The
 * action itself is in tests/Unit/App/Admin/Settings/Actions/SetSettingActionTest.php.
 */
it('saves a value, and removes it again with --unset', function (): void {
    $this->artisan('chatvia:settings:set', ['key' => 'mail.from_address', 'value' => 'hello@acme.example'])->assertSuccessful();

    expect(savedSetting(MailSettings::class, 'from_address'))->toBe('hello@acme.example');

    $this->artisan('chatvia:settings:set', ['key' => 'mail.from_address', '--unset' => true])->assertSuccessful();

    expect(savedSetting(MailSettings::class, 'from_address'))->toBeNull();
});

it('fails on an unknown key', function (): void {
    $this->artisan('chatvia:settings:set', ['key' => 'mail.nope', 'value' => 'x'])
        ->assertFailed();
});
