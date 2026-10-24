<?php

use App\Identity\ApiKey\Models\PersonalAccessToken;
use App\Identity\Workspace\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Sanctum saves `last_used_at` on every API request. The app's token model
 * writes it at most once a minute, and saves every other change as usual.
 */
function patToken(User $user): PersonalAccessToken
{
    /** @var PersonalAccessToken $token */
    $token = $user->createToken('account')->accessToken;

    return $token;
}

function patStoredLastUsedAt(PersonalAccessToken $token): ?string
{
    return DB::table('personal_access_tokens')->where('id', $token->id)->value('last_used_at');
}

it('writes the first use and skips another one within the minute', function (): void {
    $this->travelTo(now()->startOfMinute());
    $token = patToken(User::factory()->create());

    $token->forceFill(['last_used_at' => now()])->save();
    $first = patStoredLastUsedAt($token);

    $this->travel(30)->seconds();
    $saved = $token->forceFill(['last_used_at' => now()])->save();

    expect($first)->not->toBeNull()
        ->and($saved)->toBeTrue()
        ->and(patStoredLastUsedAt($token))->toBe($first)
        ->and($token->isDirty())->toBeFalse();
});

it('writes the use again once the stored one is a minute old', function (): void {
    $this->travelTo(now()->startOfMinute());
    $token = patToken(User::factory()->create());
    $token->forceFill(['last_used_at' => now()])->save();
    $first = patStoredLastUsedAt($token);

    $this->travel(61)->seconds();
    $token->forceFill(['last_used_at' => now()])->save();

    expect(patStoredLastUsedAt($token))->not->toBe($first);
});

it('saves any other change even right after a use', function (): void {
    $token = patToken(User::factory()->create());
    $token->forceFill(['last_used_at' => now()])->save();

    $token->forceFill(['name' => 'renamed', 'last_used_at' => now()])->save();

    expect($token->fresh()->name)->toBe('renamed');
});
