<?php

use App\Admin\Clusters\Settings\Pages\ManageSignInSettings;
use App\Admin\Settings\SignInSettings;
use Filament\Actions\Testing\TestAction;

/**
 * What every Settings page of the admin panel does with a saved secret: it
 * is write-only and never sent to the browser, a blank input keeps it, and
 * it can be removed on its own.
 */
beforeEach(function (): void {
    $this->admin = adminUser();
});

it('never sends a saved secret to the browser', function (): void {
    saveSettings(SignInSettings::class, ['google_client_id' => 'visible-id', 'google_client_secret' => 'top-secret-value']);

    adminPage(ManageSignInSettings::class, $this->admin)
        ->assertSchemaStateSet(['google_client_id' => 'visible-id', 'google_client_secret' => null])
        ->assertDontSee('top-secret-value');
});

it('keeps a saved secret when its input is left blank', function (): void {
    saveSettings(SignInSettings::class, ['google_client_id' => 'old-id', 'google_client_secret' => 'kept-secret']);

    adminPage(ManageSignInSettings::class, $this->admin)
        ->fillForm(['google_client_id' => ' new-id ', 'google_client_secret' => ''])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(savedSetting(SignInSettings::class, 'google_client_id'))->toBe('new-id')
        ->and(savedSetting(SignInSettings::class, 'google_client_secret'))->toBe('kept-secret');
});

it('removes one saved secret and keeps the other values', function (): void {
    saveSettings(SignInSettings::class, ['google_client_id' => 'id', 'google_client_secret' => 'secret']);

    adminPage(ManageSignInSettings::class, $this->admin)
        ->callAction(TestAction::make('forget_google_client_secret')->schemaComponent('google_client_secret', schema: 'form'));

    expect(savedSetting(SignInSettings::class, 'google_client_secret'))->toBeNull()
        ->and(savedSetting(SignInSettings::class, 'google_client_id'))->toBe('id');
});
