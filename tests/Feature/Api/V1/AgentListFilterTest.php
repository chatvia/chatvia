<?php

use App\Identity\Workspace\Models\User;
use Domain\Membership\Team\Services\TeamService;

/**
 * The `team_id` filter of `GET /api/v1/agents` takes only one of the caller's
 * teams (AgentsApiTest covers the narrowing): another workspace's, an unknown
 * or a malformed id is a validation error, not a server error.
 */
beforeEach(function (): void {
    $this->user = User::factory()->create();
    app(TeamService::class)->createDefaultTeam($this->user);
    $this->agent = createAgent($this->user);
    $this->token = $this->user->createToken('account')->plainTextToken;
});

it('rejects a team the caller does not own, or a malformed id, as a validation error', function (string $teamId): void {
    $this->withToken($this->token)
        ->getJson('/api/v1/agents?team_id='.$teamId)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['team_id' => 'Team not found.']);
})->with([
    'another workspace' => [fn (): string => createAgent(User::factory()->create())->team->external_id],
    'unknown team' => [fn (): string => '0198a5d4-3b2c-7f00-8000-000000000000'],
    'not a uuid' => [fn (): string => 'not-a-team'],
]);
