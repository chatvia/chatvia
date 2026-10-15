<?php

use App\Identity\Workspace\Models\User;
use Domain\Integration\Function\Models\AgentFunction;
use Domain\Membership\Team\Services\TeamService;

/**
 * The account API for functions (webhook tools): listing only the
 * workspace's own, and the parameters-schema rules on create and update.
 */
beforeEach(function (): void {
    // The account-level routes are gated by team permissions, so the owner
    // needs their default team to be recognised as an owner (ownsTeam → bypass).
    $this->user = User::factory()->create();
    app(TeamService::class)->createDefaultTeam($this->user);
    $this->token = $this->user->createToken('account')->plainTextToken;
});

describe('GET /api/v1/functions', function (): void {

    it('lists the account owner’s functions', function (): void {
        $fn = AgentFunction::factory()->forOwner($this->user)->create();

        $this->withToken($this->token)
            ->getJson('/api/v1/functions')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $fn->external_id);
    });

    it('never lists another account’s functions', function (): void {
        AgentFunction::factory()->forOwner(User::factory()->create())->create();

        $this->withToken($this->token)
            ->getJson('/api/v1/functions')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    });
});

describe('GET /api/v1/functions/{function}', function (): void {

    it('returns an owned function', function (): void {
        $fn = AgentFunction::factory()->forOwner($this->user)->create();

        $this->withToken($this->token)
            ->getJson("/api/v1/functions/{$fn->external_id}")
            ->assertOk()
            ->assertJsonPath('data.id', $fn->external_id);
    });
});

describe('POST /api/v1/functions', function (): void {

    it('rejects a parameters schema that is not an object schema', function (): void {
        $this->withToken($this->token)
            ->postJson('/api/v1/functions', [
                'internal_name' => 'get_order_status',
                'description' => 'Get the order status',
                'parameters_schema' => ['type' => 'object', 'properties' => ['x' => ['type' => 'function']]],
                'webhook_url' => 'https://example.com/webhook',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('parameters_schema');

        expect($this->user->workspace()->functions()->count())->toBe(0);
    });
});

describe('PATCH /api/v1/functions/{function}', function (): void {

    it('rejects a changed schema with an invalid property name', function (): void {
        $fn = AgentFunction::factory()->forOwner($this->user)->create();

        $this->withToken($this->token)
            ->patchJson("/api/v1/functions/{$fn->external_id}", [
                'parameters_schema' => ['type' => 'object', 'properties' => ['1bad' => ['type' => 'string']]],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('parameters_schema');
    });

    it('accepts a stored schema that fails the shape rules when it is sent back unchanged', function (): void {
        $stored = ['type' => 'object', 'properties' => ['order-id' => ['type' => 'string']]];
        $fn = AgentFunction::factory()->forOwner($this->user)->create(['parameters_schema' => $stored]);

        $this->withToken($this->token)
            ->patchJson("/api/v1/functions/{$fn->external_id}", [
                'description' => 'New description',
                'parameters_schema' => $stored,
            ])
            ->assertOk()
            ->assertJsonPath('data.description', 'New description');
    });
});
