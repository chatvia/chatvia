<?php

use App\Identity\Workspace\Models\User;
use App\Identity\Workspace\Services\AgentSignInTokenService;
use Domain\Membership\Team\Models\Role;
use Domain\Membership\Team\Services\TeamService;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    $this->owner = User::factory()->create();
    app(TeamService::class)->createDefaultTeam($this->owner);
    $this->agent = createAgent($this->owner);
    $this->service = app(AgentSignInTokenService::class);
});

it('encodes and decodes the owner back to the same user', function (): void {
    $token = $this->service->issue($this->owner, $this->agent)['token'];

    $resolved = $this->service->verify($token, $this->agent);

    expect($resolved)->not->toBeNull()
        ->and($resolved->id)->toBe($this->owner->id)
        ->and($resolved->external_id)->toBe($this->owner->external_id);
});

it('signs the JWT with the expected sub/aud/exp claims', function (): void {
    // issue() signs with the per-agent secret.
    $secret = $this->agent->ensureAuthSecret();

    ['token' => $token, 'expires_at' => $expiresAt] = $this->service->issue($this->owner, $this->agent);

    $payload = JWT::decode($token, new Key($secret, 'HS256'));

    expect($payload->sub)->toBe($this->owner->external_id)
        ->and($payload->aud)->toBe($this->agent->external_id)
        ->and((int) $payload->exp)->toBe($expiresAt->getTimestamp());
});

it('encodes and decodes a member whose role grants this agent', function (): void {
    $team = $this->owner->primaryTeam();
    app(PermissionRegistrar::class)->setPermissionsTeamId($team->id);

    // A role scoped to a single agent (the "specific agent" grant).
    $role = Role::create([
        'name' => 'Support',
        'code' => 'support',
        'team_id' => $team->id,
        'guard_name' => 'web',
    ]);
    $role->agents()->attach($this->agent->id);

    $member = User::factory()->employee($this->owner)->create([
        'current_team_id' => $team->id,
    ]);
    $team->members()->attach($member->id, ['role_id' => $role->id]);

    $token = $this->service->issue($member, $this->agent)['token'];

    $resolved = $this->service->verify($token, $this->agent);

    expect($resolved)->not->toBeNull()
        ->and($resolved->id)->toBe($member->id);
});

it('rejects a managed user with no permission to the agent', function (): void {
    // Found under the account, but holds no role granting this agent — the
    // signature is valid, yet verify() must still refuse (no access).
    $stranger = User::factory()->employee($this->owner)->create([
        'current_team_id' => $this->owner->primaryTeam()->id,
    ]);

    $token = $this->service->issue($stranger, $this->agent)['token'];

    expect($this->service->verify($token, $this->agent))->toBeNull();
});
