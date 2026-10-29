<?php

use App\Identity\Workspace\Models\User;

/**
 * How AuthServiceProvider sets up API keys on a real API request: the key of
 * a deactivated account stops working at once.
 */
beforeEach(function (): void {
    withLicense();

    $this->owner = User::factory()->create();
    ownerTeam($this->owner);
});

it('stops accepting an account API key once the account is deactivated', function (): void {
    $token = $this->owner->createToken('account')->plainTextToken;

    withApiToken($token)->getJson(route('api.v1.teams.index'))->assertOk();

    $this->owner->update(['deactivated_at' => now()]);

    withApiToken($token)->getJson(route('api.v1.teams.index'))->assertUnauthorized();
});
