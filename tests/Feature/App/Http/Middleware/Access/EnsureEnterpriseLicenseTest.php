<?php

use App\Admin\Settings\BrandSettings;
use App\Identity\Workspace\Models\User;
use Tests\Support\License\LicenseKeys;

/**
 * The `license.enterprise` route middleware: an Enterprise route answers 403
 * until this install has an Enterprise key, and opens once it does.
 */
beforeEach(function (): void {
    $this->owner = User::factory()->create();
    ownerTeam($this->owner);
});

it('refuses an Enterprise route without any key', function (): void {
    $this->actingAs($this->owner)
        ->get(route('security.audit.export'))
        ->assertForbidden();
});

it('refuses an Enterprise route with a Pro key', function (): void {
    LicenseKeys::trust();
    saveSettings(BrandSettings::class, ['license_key' => LicenseKeys::activatedFor('localhost')]);

    $this->actingAs($this->owner)
        ->get(route('security.audit.export'))
        ->assertForbidden();
});

it('opens an Enterprise route with an Enterprise key', function (): void {
    withEnterpriseLicense();

    $this->actingAs($this->owner)
        ->get(route('security.audit.export'))
        ->assertOk();
});
