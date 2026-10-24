<?php

use App\Identity\Workspace\Models\User;
use Tests\Support\Api\ActsAsApiOwner;

/**
 * Removing a member from a team through the public API. Only someone who
 * holds a membership of the team resolves: the owner holds the team itself,
 * so they, like anyone not on the team, are a 404.
 */
uses(ActsAsApiOwner::class);

beforeEach(function (): void {
    $this->actAsApiOwner();
    $this->team = ownerTeam($this->owner);
});

it('removes a member from the team', function (): void {
    $member = User::factory()->employee($this->owner)->create();
    $this->team->members()->attach($member->id, ['role_id' => $this->team->roles()->value('id')]);

    $this->withToken($this->token)
        ->deleteJson("/api/v1/teams/{$this->team->external_id}/members/{$member->external_id}")
        ->assertNoContent();

    expect($this->team->members()->whereKey($member->id)->exists())->toBeFalse();
});

it('answers 404 for someone who holds no membership of the team', function (Closure $person): void {
    $this->withToken($this->token)
        ->deleteJson("/api/v1/teams/{$this->team->external_id}/members/{$person($this->owner)->external_id}")
        ->assertNotFound();
})->with([
    'the owner' => [fn (User $owner): User => $owner],
    'a teammate not on the team' => [fn (User $owner): User => User::factory()->employee($owner)->create()],
]);
