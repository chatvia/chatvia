<?php

use App\Admin\Settings\Actions\SetSettingAction;
use App\Admin\Settings\AiSettings;
use App\Admin\Settings\MailSettings;
use App\Admin\Settings\WorkspaceSettings;

/**
 * Admin-panel settings can be set from the command line (`app:setting`), so
 * installers and scripts can set up an install without the panel.
 */
function settingValue(string $class, string $property): mixed
{
    app()->forgetScopedInstances();

    return app($class)->{$property};
}

it('saves a setting by its key and casts it to the property type', function (): void {
    $action = app(SetSettingAction::class);

    $action->execute('mail.smtp_host', ' smtp.example.com ');
    $action->execute('mail.smtp_port', '587');
    $action->execute('workspaces.registration_enabled', 'false');

    expect(settingValue(MailSettings::class, 'smtp_host'))->toBe('smtp.example.com')
        ->and(settingValue(MailSettings::class, 'smtp_port'))->toBe(587)
        ->and(settingValue(WorkspaceSettings::class, 'registration_enabled'))->toBeFalse()
        ->and(config('mail.mailers.smtp.port'))->toBe(587);
});

it('removes a saved value when given null', function (): void {
    $action = app(SetSettingAction::class);
    $action->execute('ai.mistral_api_key', 'mistral-key');

    $action->execute('ai.mistral_api_key', null);

    expect(settingValue(AiSettings::class, 'mistral_api_key'))->toBeNull();
});

it('refuses an unknown key', function (string $key): void {
    app(SetSettingAction::class)->execute($key, 'x');
})->with(['mail.nope', 'nope.smtp_host', 'mail', ''])->throws(InvalidArgumentException::class, 'Unknown setting');

it('refuses a value of the wrong type', function (): void {
    app(SetSettingAction::class)->execute('mail.smtp_port', 'not-a-port');
})->throws(InvalidArgumentException::class, 'not a valid value');

it('sets a value with app:setting', function (): void {
    $this->artisan('app:setting', ['key' => 'mail.from_address', 'value' => 'hello@acme.example'])
        ->expectsOutput('mail.from_address saved.')
        ->assertSuccessful();

    expect(settingValue(MailSettings::class, 'from_address'))->toBe('hello@acme.example');

    $this->artisan('app:setting', ['key' => 'mail.from_address', '--unset' => true])->assertSuccessful();

    expect(settingValue(MailSettings::class, 'from_address'))->toBeNull();
});

it('fails app:setting on an unknown key', function (): void {
    $this->artisan('app:setting', ['key' => 'mail.nope', 'value' => 'x'])
        ->expectsOutputToContain('Unknown setting [mail.nope]')
        ->assertFailed();
});

it('lists the settings with app:settings without showing secrets', function (): void {
    app(SetSettingAction::class)->execute('ai.groq_api_key', 'gsk-hidden-value');

    $this->artisan('app:settings', ['group' => 'ai'])
        ->expectsOutputToContain('ai.groq_api_key')
        ->doesntExpectOutputToContain('gsk-hidden-value')
        ->assertSuccessful();
});
