<?php

use App\Identity\Workspace\Models\User;

/**
 * The `license.api` route middleware: without a license key the public API
 * and the creating of API keys answer 403; any valid key opens both. A key
 * made earlier is kept and works again once the install has a license.
 */
beforeEach(function (): void {
    $this->owner = User::factory()->create();
    ownerTeam($this->owner);
    $this->token = $this->owner->createToken('account')->plainTextToken;
});

it('refuses the API without a license key', function (): void {
    $this->withToken($this->token)
        ->getJson('/api/v1/agents')
        ->assertForbidden()
        ->assertJsonPath('message', 'The API needs a ChatVia.ai license key.');
});

it('refuses to create an API key without a license key', function (): void {
    $this->actingAs($this->owner)
        ->post(route('integrations.api-keys.store'), ['name' => 'CI token'])
        ->assertForbidden();

    expect($this->owner->tokens()->where('name', 'CI token')->exists())->toBeFalse();
});

it('opens the API and the API keys with a license key', function (): void {
    withLicense();

    $this->withToken($this->token)
        ->getJson('/api/v1/agents')
        ->assertOk();

    $this->actingAs($this->owner)
        ->post(route('integrations.api-keys.store'), ['name' => 'CI token'])
        ->assertRedirect()
        ->assertSessionHasNoErrors();
});
