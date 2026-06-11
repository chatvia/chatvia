<?php

use App\Identity\Workspace\Models\User;
use Domain\Integration\Function\Models\AgentFunction;
use Domain\Membership\Team\Services\TeamService;

beforeEach(function (): void {
    // UserObserver::created auto-provisions the default team + billing + quota.
    $this->user = User::factory()->create();
    $this->token = $this->user->createToken('account')->plainTextToken;
});

function functionHeaders(string $token): array
{
    return ['Authorization' => 'Bearer '.$token, 'Accept' => 'application/json'];
}

function makeApiFunction(User $owner, array $overrides = []): AgentFunction
{
    return $owner->functions()->create(array_merge([
        'internal_name' => 'get_order_status',
        'description' => 'Get the order status',
        'parameters_schema' => ['type' => 'object', 'properties' => ['order_id' => ['type' => 'string']]],
        'webhook_url' => 'https://example.com/webhook',
        'webhook_method' => 'POST',
        'auth_type' => 'none',
        'auth_config' => null,
        'is_enabled' => true,
    ], $overrides));
}

describe('GET /api/v1/functions', function (): void {

    it('lists the account owner’s functions', function (): void {
        $fn = makeApiFunction($this->user);

        $this->withHeaders(functionHeaders($this->token))
            ->getJson('/api/v1/functions')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $fn->external_id);
    });

    it('lists the workspace owner’s functions for a member token', function (): void {
        $fn = makeApiFunction($this->user);

        $member = User::factory()->employee($this->user)->create();
        $memberToken = $member->createToken('account')->plainTextToken;

        $this->withHeaders(functionHeaders($memberToken))
            ->getJson('/api/v1/functions')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $fn->external_id);
    });

    it('never lists another account’s functions', function (): void {
        $other = tap(User::factory()->create(), fn (User $u) => app(TeamService::class)->createDefaultTeam($u));
        makeApiFunction($other);

        $this->withHeaders(functionHeaders($this->token))
            ->getJson('/api/v1/functions')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    });
});

describe('GET /api/v1/functions/{function}', function (): void {

    it('returns an owned function', function (): void {
        $fn = makeApiFunction($this->user);

        $this->withHeaders(functionHeaders($this->token))
            ->getJson("/api/v1/functions/{$fn->external_id}")
            ->assertOk()
            ->assertJsonPath('data.id', $fn->external_id);
    });

    it('404s for a function belonging to another account', function (): void {
        $other = tap(User::factory()->create(), fn (User $u) => app(TeamService::class)->createDefaultTeam($u));
        $theirs = makeApiFunction($other);

        $this->withHeaders(functionHeaders($this->token))
            ->getJson("/api/v1/functions/{$theirs->external_id}")
            ->assertNotFound();
    });
});

describe('DELETE /api/v1/functions/{function}', function (): void {

    it('404s without deleting for a function belonging to another account', function (): void {
        $other = tap(User::factory()->create(), fn (User $u) => app(TeamService::class)->createDefaultTeam($u));
        $theirs = makeApiFunction($other);

        $this->withHeaders(functionHeaders($this->token))
            ->deleteJson("/api/v1/functions/{$theirs->external_id}")
            ->assertNotFound();

        expect(AgentFunction::whereKey($theirs->id)->exists())->toBeTrue();
    });
});
