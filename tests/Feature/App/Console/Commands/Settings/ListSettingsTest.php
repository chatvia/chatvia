<?php

use App\Admin\Settings\AiProviderSettings;

/**
 * `chatvia:settings:list` lists the admin-panel settings of a group, with
 * their keys, and never prints a secret's value.
 */
it('lists the settings without showing secrets', function (): void {
    saveSettings(AiProviderSettings::class, ['cohere_api_key' => 'co-hidden-value']);

    $this->artisan('chatvia:settings:list', ['group' => 'ai_providers'])
        ->expectsOutputToContain('ai_providers.cohere_api_key')
        ->doesntExpectOutputToContain('co-hidden-value')
        ->assertSuccessful();
});
