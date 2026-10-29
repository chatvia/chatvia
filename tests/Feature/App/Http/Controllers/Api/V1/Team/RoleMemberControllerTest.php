<?php

use App\Identity\Chatter\Models\Chatter;
use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Tests\Support\Api\ActsAsApiOwner;

/**
 * Assigning a teammate to a role through the public API. A person holds one
 * role per team, so a new role replaces the old one. Only the workspace's
 * staff accounts resolve: a chatter's id or another workspace's teammate is
 * a field error.
 */
uses(ActsAsApiOwner::class);

beforeEach(function (): void {
    $this->actAsApiOwner();
    $this->team = ownerTeam($this->owner);
    $this->role = teamRole($this->team, 'Support', permissions: []);
});

it('assigns a teammate to the role and moves them on reassignment, one role per team', function (): void {
    $other = teamRole($this->team, 'Other', permissions: []);
    $teammate = User::factory()->employee($this->owner)->create();

    foreach ([$this->role, $other] as $role) {
        $this->withToken($this->token)
            ->postJson("/api/v1/teams/{$this->team->external_id}/roles/{$role->external_id}/users", ['user_id' => $teammate->external_id])
            ->assertOk()
            ->assertJsonPath('data.id', $teammate->external_id);
    }

    expect($this->team->members()->where('users.id', $teammate->id)->count())->toBe(1)
        ->and($this->team->members()->where('users.id', $teammate->id)->wherePivot('role_id', $other->id)->exists())->toBeTrue();
});

it('rejects an id that is not a staff account of the workspace as a field error', function (Closure $userId): void {
    $id = $userId($this->agent);

    $this->withToken($this->token)
        ->postJson("/api/v1/teams/{$this->team->external_id}/roles/{$this->role->external_id}/users", ['user_id' => $id])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['user_id']);

    expect($this->team->members()->where('users.external_id', $id)->exists())->toBeFalse();
})->with([
    "a chatter's id" => [fn (Agent $agent): string => Chatter::factory()->grantedTo($agent)->create()->external_id],
    "another workspace's teammate" => [fn (): string => User::factory()->employee(User::factory()->create())->create()->external_id],
]);
