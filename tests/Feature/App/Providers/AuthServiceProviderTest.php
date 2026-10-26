<?php

use App\Identity\Workspace\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * How AuthServiceProvider sets up API keys on a real API request: Sanctum
 * uses the app's token model, which writes `last_used_at` at most once a
 * minute (PersonalAccessTokenTest), and the key of a deactivated account
 * stops working at once.
 */
beforeEach(function (): void {
    withLicense();

    $this->owner = User::factory()->create();
    ownerTeam($this->owner);
});

it('records the first API request, then skips the next one within the minute', function (): void {
    $this->travelTo(now()->startOfMinute());
    $plain = $this->owner->createToken('account')->plainTextToken;
    $lastUsedAt = fn (): ?string => DB::table('personal_access_tokens')->where('id', $this->owner->tokens()->sole()->id)->value('last_used_at');

    withApiToken($plain)->getJson(route('api.v1.teams.index'))->assertOk();
    $first = $lastUsedAt();

    $this->travel(20)->seconds();
    withApiToken($plain)->getJson(route('api.v1.teams.index'))->assertOk();

    expect($first)->not->toBeNull()
        ->and($lastUsedAt())->toBe($first);
});

it('stops accepting an account API key once the account is deactivated', function (): void {
    $token = $this->owner->createToken('account')->plainTextToken;

    withApiToken($token)->getJson(route('api.v1.teams.index'))->assertOk();

    $this->owner->update(['deactivated_at' => now()]);

    withApiToken($token)->getJson(route('api.v1.teams.index'))->assertUnauthorized();
});
