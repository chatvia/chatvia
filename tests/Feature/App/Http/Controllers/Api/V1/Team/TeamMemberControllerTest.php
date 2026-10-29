<?php

use App\Identity\Workspace\Models\User;
use Tests\Support\Api\ActsAsApiOwner;

/**
 * Removing a member from a team through the public API.
 */
uses(ActsAsApiOwner::class);

it('removes a member from the team', function (): void {
    $this->actAsApiOwner();
    $this->team = ownerTeam($this->owner);
    $member = User::factory()->employee($this->owner)->create();
    $this->team->members()->attach($member->id, ['role_id' => $this->team->roles()->value('id')]);

    $this->withToken($this->token)
        ->deleteJson("/api/v1/teams/{$this->team->external_id}/members/{$member->external_id}")
        ->assertNoContent();

    expect($this->team->members()->whereKey($member->id)->exists())->toBeFalse();
});
