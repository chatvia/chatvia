<?php

use App\Admin\Settings\Actions\SetSettingAction;
use App\Admin\Settings\AiProviderSettings;
use App\Admin\Settings\MailSettings;
use App\Admin\Settings\WorkspaceSettings;

/**
 * SetSettingAction: one admin-panel setting set by its `group.property` key,
 * as `app:setting` does, cast to the property type and copied into config.
 */
it('saves a setting by its key and casts it to the property type', function (): void {
    $action = app(SetSettingAction::class);

    $action->execute('mail.smtp_host', ' smtp.example.com ');
    $action->execute('mail.smtp_port', '587');
    $action->execute('workspaces.registration_enabled', 'false');

    expect(savedSetting(MailSettings::class, 'smtp_host'))->toBe('smtp.example.com')
        ->and(savedSetting(MailSettings::class, 'smtp_port'))->toBe(587)
        ->and(savedSetting(WorkspaceSettings::class, 'registration_enabled'))->toBeFalse()
        ->and(config('mail.mailers.smtp.port'))->toBe(587);
});

it('removes a saved value when given null', function (): void {
    $action = app(SetSettingAction::class);
    $action->execute('ai_providers.mistral_api_key', 'mistral-key');

    $action->execute('ai_providers.mistral_api_key', null);

    expect(savedSetting(AiProviderSettings::class, 'mistral_api_key'))->toBeNull();
});

it('refuses an unknown key', function (string $key): void {
    app(SetSettingAction::class)->execute($key, 'x');
})->with(['mail.nope', 'nope.smtp_host', 'mail', ''])->throws(InvalidArgumentException::class, 'Unknown setting');

it('refuses a value of the wrong type', function (): void {
    app(SetSettingAction::class)->execute('mail.smtp_port', 'not-a-port');
})->throws(InvalidArgumentException::class, 'not a valid value');
