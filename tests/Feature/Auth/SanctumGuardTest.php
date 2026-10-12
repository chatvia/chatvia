<?php

use App\Identity\Workspace\Models\User;

/**
 * The public API's bearer tokens only ever resolve to a `users` account.
 * Without a pinned provider Sanctum accepts a token from any model that has
 * API tokens, so a second account model could otherwise reach staff routes.
 */
it('pins the sanctum guard to the users provider', function (): void {
    expect(config('auth.guards.sanctum.provider'))->toBe('users')
        ->and(config('auth.providers.users.model'))->toBe(User::class);
});

it('still accepts an account API key', function (): void {
    $owner = User::factory()->create();
    $token = $owner->createToken('test')->plainTextToken;

    $this->withToken($token)
        ->getJson('/api/v1/agents')
        ->assertOk();
});
