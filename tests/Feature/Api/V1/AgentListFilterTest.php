<?php

use App\Identity\Workspace\Models\User;
use Domain\Membership\Team\Services\TeamService;

/**
 * The `team_id` filter of `GET /api/v1/agents` narrows the list to one of the
 * caller's teams; an unknown or malformed id is a validation error, not a
 * server error.
 */
beforeEach(function (): void {
    $this->user = User::factory()->create();
    app(TeamService::class)->createDefaultTeam($this->user);
    $this->agent = createAgent($this->user);
    $this->headers = ['Authorization' => 'Bearer '.$this->user->createToken('account')->plainTextToken, 'Accept' => 'application/json'];
});

it('lists the agents of the given team', function (): void {
    $this->withHeaders($this->headers)
        ->getJson('/api/v1/agents?team_id='.$this->user->primaryTeam()->external_id)
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('rejects a team the caller does not own, or a malformed id, as a validation error', function (string $teamId): void {
    $this->withHeaders($this->headers)
        ->getJson('/api/v1/agents?team_id='.$teamId)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['team_id' => 'Team not found.']);
})->with([
    'unknown team' => ['0198a5d4-3b2c-7f00-8000-000000000000'],
    'not a uuid' => ['not-a-team'],
]);
