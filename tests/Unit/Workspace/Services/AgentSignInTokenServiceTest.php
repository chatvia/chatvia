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

describe('compact HMAC credential', function (): void {
    it('issues a token in the cvh1.<sub>.<exp>.<sig> shape with the expected signature', function (): void {
        // issueHmac() signs with the per-agent secret.
        $secret = $this->agent->ensureAuthSecret();

        ['token' => $token, 'expires_at' => $expiresAt] = $this->service->issueHmac($this->owner, $this->agent);

        expect($token)->toStartWith('cvh1.');

        $body = substr($token, 5);
        [$sub, $exp, $sig] = [
            substr($body, 0, strpos($body, '.')),
            substr($body, strpos($body, '.') + 1, strrpos($body, '.') - strpos($body, '.') - 1),
            substr($body, strrpos($body, '.') + 1),
        ];

        expect($sub)->toBe($this->owner->external_id)
            ->and((int) $exp)->toBe($expiresAt->getTimestamp())
            ->and($sig)->toMatch('/^[a-f0-9]{64}$/')
            ->and($sig)->toBe(hash_hmac('sha256', $sub.'.'.$exp, $secret));
    });

    it('encodes and decodes the owner back to the same user', function (): void {
        $token = $this->service->issueHmac($this->owner, $this->agent)['token'];

        $resolved = $this->service->verify($token, $this->agent);

        expect($resolved)->not->toBeNull()
            ->and($resolved->id)->toBe($this->owner->id)
            ->and($resolved->external_id)->toBe($this->owner->external_id);
    });

    it('encodes and decodes a member whose role grants this agent', function (): void {
        $team = $this->owner->primaryTeam();
        app(PermissionRegistrar::class)->setPermissionsTeamId($team->id);

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

        $token = $this->service->issueHmac($member, $this->agent)['token'];

        $resolved = $this->service->verify($token, $this->agent);

        expect($resolved)->not->toBeNull()
            ->and($resolved->id)->toBe($member->id);
    });

    it('accepts a credential minted by inline hash_hmac — cross-language parity', function (): void {
        // What a customer's PHP/JS snippet does, verbatim.
        $secret = $this->agent->ensureAuthSecret();
        $exp = now()->addMinutes(10)->getTimestamp();
        $sub = $this->owner->external_id;
        $sig = hash_hmac('sha256', $sub.'.'.$exp, $secret);

        $token = 'cvh1.'.$sub.'.'.$exp.'.'.$sig;

        expect($this->service->verify($token, $this->agent)?->id)->toBe($this->owner->id);
    });

    it('rejects a tampered signature', function (): void {
        $token = $this->service->issueHmac($this->owner, $this->agent)['token'];
        // Flip the last hex char.
        $tampered = substr($token, 0, -1).(substr($token, -1) === '0' ? '1' : '0');

        expect($this->service->verify($tampered, $this->agent))->toBeNull();
    });

    it('rejects a credential signed with the wrong secret', function (): void {
        $exp = now()->addMinutes(10)->getTimestamp();
        $sub = $this->owner->external_id;
        $sig = hash_hmac('sha256', $sub.'.'.$exp, 'not-the-real-secret');

        $token = 'cvh1.'.$sub.'.'.$exp.'.'.$sig;

        expect($this->service->verify($token, $this->agent))->toBeNull();
    });

    it('rejects a credential minted against a different agent (per-agent scoping)', function (): void {
        $other = createAgent($this->owner);

        $token = $this->service->issueHmac($this->owner, $other)['token'];

        // The signature is valid against `$other` but not against `$this->agent`,
        // because the secret is per-agent.
        expect($this->service->verify($token, $this->agent))->toBeNull();
    });

    it('rejects an expired credential', function (): void {
        $secret = $this->agent->ensureAuthSecret();
        $exp = now()->subSecond()->getTimestamp();
        $sub = $this->owner->external_id;
        $sig = hash_hmac('sha256', $sub.'.'.$exp, $secret);

        $token = 'cvh1.'.$sub.'.'.$exp.'.'.$sig;

        expect($this->service->verify($token, $this->agent))->toBeNull();
    });

    it('rejects a credential with a lifetime over the 24h cap', function (): void {
        $secret = $this->agent->ensureAuthSecret();
        $exp = now()->addSeconds(AgentSignInTokenService::MAX_TTL_SECONDS + 60)->getTimestamp();
        $sub = $this->owner->external_id;
        $sig = hash_hmac('sha256', $sub.'.'.$exp, $secret);

        $token = 'cvh1.'.$sub.'.'.$exp.'.'.$sig;

        expect($this->service->verify($token, $this->agent))->toBeNull();
    });

    it('rejects a managed user with no permission to the agent', function (): void {
        $stranger = User::factory()->employee($this->owner)->create([
            'current_team_id' => $this->owner->primaryTeam()->id,
        ]);

        $token = $this->service->issueHmac($stranger, $this->agent)['token'];

        expect($this->service->verify($token, $this->agent))->toBeNull();
    });

    it('rejects malformed credentials', function (string $token): void {
        expect($this->service->verify($token, $this->agent))->toBeNull();
    })->with([
        'missing segments' => 'cvh1.only-one-segment',
        'two segments' => 'cvh1.user_123.1234567890',
        'non-numeric exp' => fn (): string => 'cvh1.user_123.notanumber.'.str_repeat('a', 64),
        'non-hex sig' => fn (): string => 'cvh1.user_123.'.now()->addMinutes(5)->getTimestamp().'.'.str_repeat('z', 64),
        'wrong sig length' => fn (): string => 'cvh1.user_123.'.now()->addMinutes(5)->getTimestamp().'.deadbeef',
        'empty user_id' => fn (): string => 'cvh1..'.now()->addMinutes(5)->getTimestamp().'.'.str_repeat('a', 64),
    ]);
});

it('accepts both a JWT and an HMAC credential against the same agent (no scheme conflict)', function (): void {
    $jwt = $this->service->issue($this->owner, $this->agent)['token'];
    $hmac = $this->service->issueHmac($this->owner, $this->agent)['token'];

    expect($this->service->verify($jwt, $this->agent)?->id)->toBe($this->owner->id)
        ->and($this->service->verify($hmac, $this->agent)?->id)->toBe($this->owner->id);
});
