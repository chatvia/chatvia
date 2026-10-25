<?php

use App\Identity\Chatter\Models\Chatter;
use App\Identity\Workspace\Models\User;
use App\Identity\Workspace\Services\AgentSignInTokenService;
use Domain\Membership\Team\Services\TeamService;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Support\Str;

/**
 * The sign-in tokens a customer's site hands the widget or hosted chat: a JWT
 * or a compact HMAC credential, signed with the agent's own secret. Each
 * resolves to a chatter that may reach the agent; a staff account is accepted
 * only in a credential the server minted, never in one signed with the secret
 * a teammate can read. No malformed, tampered, expired or too-long-lived token
 * passes.
 */
beforeEach(function (): void {
    $this->owner = User::factory()->create();
    app(TeamService::class)->createDefaultTeam($this->owner);
    $this->agent = createAgent($this->owner);
    $this->chatter = Chatter::factory()->grantedTo($this->agent)->create();
    $this->service = app(AgentSignInTokenService::class);
});

/** A compact HMAC credential as a customer's own code signs one. */
function astsHmac(string $sub, int $exp, string $secret): string
{
    return 'cvh1.'.$sub.'.'.$exp.'.'.hash_hmac('sha256', $sub.'.'.$exp, $secret);
}

/** The server-only key staff credentials are signed with. */
function astsStaffKey(string $secret): string
{
    return hash_hmac('sha256', 'agent-sign-in-staff|'.$secret, (string) config('app.key'));
}

it('verifies a server-issued token back to the staff account it was issued for', function (string $issuer, string $who): void {
    $account = $who === 'owner'
        ? $this->owner
        : teammateWithPermissions($this->owner, [], allAgents: false, agentExternalIds: [$this->agent->external_id]);

    $token = $this->service->{$issuer}($account, $this->agent)['token'];

    expect($this->service->verify($token, $this->agent)?->external_id)->toBe($account->external_id);
})->with([
    'JWT' => 'issue',
    'HMAC' => 'issueHmac',
])->with([
    'the owner' => 'owner',
    'a teammate whose role names the agent' => 'teammate',
]);

it('refuses a validly signed token of a teammate whose role does not reach the agent', function (string $issuer): void {
    $stranger = User::factory()->employee($this->owner)->create([
        'current_team_id' => $this->owner->primaryTeam()->id,
    ]);

    $token = $this->service->{$issuer}($stranger, $this->agent)['token'];

    expect($this->service->verify($token, $this->agent))->toBeNull();
})->with([
    'JWT' => 'issue',
    'HMAC' => 'issueHmac',
]);

it('verifies a token back to the chatter it was issued for', function (string $issuer): void {
    $token = $this->service->{$issuer}($this->chatter, $this->agent)['token'];

    expect($this->service->verify($token, $this->agent))->toBeInstanceOf(Chatter::class)
        ->id->toBe($this->chatter->id);
})->with([
    'JWT' => 'issue',
    'HMAC' => 'issueHmac',
]);

it('refuses a staff account in a token signed with the agent secret', function (string $who): void {
    // A teammate who can read the secret on the Identity page must not be
    // able to sign themselves in to the chat as the owner (or anyone else).
    $account = $who === 'owner'
        ? $this->owner
        : teammateWithPermissions($this->owner, [], allAgents: false, agentExternalIds: [$this->agent->external_id]);
    $secret = $this->agent->ensureAuthSecret();
    $exp = now()->addMinutes(10)->getTimestamp();

    $jwt = JWT::encode(['sub' => $account->external_id, 'aud' => $this->agent->external_id, 'exp' => $exp], $secret, 'HS256');
    $hmac = astsHmac($account->external_id, $exp, $secret);

    expect($this->service->verify($jwt, $this->agent))->toBeNull()
        ->and($this->service->verify($hmac, $this->agent))->toBeNull()
        ->and($this->service->verify($this->service->issue($account, $this->agent)['token'], $this->agent)?->id)->toBe($account->id);
})->with([
    'the owner' => 'owner',
    'a teammate' => 'teammate',
]);

it('refuses a chatter in a token signed with the staff key', function (): void {
    // The two keys never cross: a server-minted staff token names staff only.
    $staffSigned = $this->service->issue($this->owner, $this->agent)['token'];
    $payload = JWT::decode($staffSigned, new Key(astsStaffKey($this->agent->ensureAuthSecret()), 'HS256'));
    $payload->sub = $this->chatter->external_id;

    $forged = JWT::encode((array) $payload, astsStaffKey($this->agent->ensureAuthSecret()), 'HS256');

    expect($this->service->verify($forged, $this->agent))->toBeNull();
});

it('signs the JWT with the expected sub/aud/exp claims', function (): void {
    // issue() signs a chatter's token with the per-agent secret.
    $secret = $this->agent->ensureAuthSecret();

    ['token' => $token, 'expires_at' => $expiresAt] = $this->service->issue($this->chatter, $this->agent);

    $payload = JWT::decode($token, new Key($secret, 'HS256'));

    expect($payload->sub)->toBe($this->chatter->external_id)
        ->and($payload->aud)->toBe($this->agent->external_id)
        ->and((int) $payload->exp)->toBe($expiresAt->getTimestamp());
});

it('accepts a customer-signed JWT that lives up to 24 hours and refuses a longer one', function (): void {
    $this->freezeTime();
    $secret = $this->agent->ensureAuthSecret();
    $jwt = fn (int $lifetime): string => JWT::encode([
        'sub' => $this->chatter->external_id,
        'aud' => $this->agent->external_id,
        'exp' => now()->addSeconds($lifetime)->getTimestamp(),
    ], $secret, 'HS256');

    expect($this->service->verify($jwt(AgentSignInTokenService::MAX_TTL_SECONDS), $this->agent)?->id)->toBe($this->chatter->id)
        ->and($this->service->verify($jwt(AgentSignInTokenService::MAX_TTL_SECONDS + 1), $this->agent))->toBeNull();
});

it('keeps the lifetime of an issued token between one minute and 24 hours', function (): void {
    $this->freezeTime();

    expect($this->service->issue($this->owner, $this->agent, ttlSeconds: 5)['expires_at']->getTimestamp())
        ->toBe(now()->addSeconds(AgentSignInTokenService::MIN_TTL_SECONDS)->getTimestamp())
        ->and($this->service->issue($this->owner, $this->agent, ttlSeconds: 999_999)['expires_at']->getTimestamp())
        ->toBe(now()->addSeconds(AgentSignInTokenService::MAX_TTL_SECONDS)->getTimestamp());
});

it('hands back the playground context tag of a token that carries one', function (): void {
    $tagged = $this->service->issue($this->owner, $this->agent, ctx: AgentSignInTokenService::CTX_PLAYGROUND)['token'];
    $plain = $this->service->issue($this->owner, $this->agent)['token'];
    $hmac = $this->service->issueHmac($this->owner, $this->agent)['token'];

    $claims = $this->service->verifyWithClaims($tagged, $this->agent);

    expect($claims['user']->id)->toBe($this->owner->id)
        ->and($claims['ctx'])->toBe('playground')
        ->and($this->service->verifyWithClaims($plain, $this->agent)['ctx'])->toBeNull()
        ->and($this->service->verifyWithClaims($hmac, $this->agent)['ctx'])->toBeNull();
});

describe('compact HMAC credential', function (): void {
    it('issues a token in the cvh1.<sub>.<exp>.<sig> shape with the expected signature', function (): void {
        // issueHmac() signs a chatter's credential with the per-agent secret.
        $secret = $this->agent->ensureAuthSecret();

        ['token' => $token, 'expires_at' => $expiresAt] = $this->service->issueHmac($this->chatter, $this->agent);

        expect($token)->toStartWith('cvh1.');

        $body = Str::after($token, 'cvh1.');
        [$sub, $exp, $sig] = [
            Str::before($body, '.'),
            Str::between($body, '.', '.'),
            Str::afterLast($body, '.'),
        ];

        expect($sub)->toBe($this->chatter->external_id)
            ->and((int) $exp)->toBe($expiresAt->getTimestamp())
            ->and($sig)->toMatch('/^[a-f0-9]{64}$/')
            ->and($sig)->toBe(hash_hmac('sha256', $sub.'.'.$exp, $secret));
    });

    it('accepts a credential minted by inline hash_hmac — cross-language parity', function (): void {
        // What a customer's PHP/JS snippet does, verbatim.
        $token = astsHmac($this->chatter->external_id, now()->addMinutes(10)->getTimestamp(), $this->agent->ensureAuthSecret());

        expect($this->service->verify($token, $this->agent)?->id)->toBe($this->chatter->id);
    });

    it('rejects a tampered signature', function (): void {
        $token = $this->service->issueHmac($this->owner, $this->agent)['token'];
        // Flip the last hex char.
        $tampered = Str::substr($token, 0, -1).(Str::endsWith($token, '0') ? '1' : '0');

        expect($this->service->verify($tampered, $this->agent))->toBeNull();
    });

    it('rejects a credential signed with the wrong secret', function (): void {
        $token = astsHmac($this->chatter->external_id, now()->addMinutes(10)->getTimestamp(), 'not-the-real-secret');

        expect($this->service->verify($token, $this->agent))->toBeNull();
    });

    it('rejects a credential minted against a different agent (per-agent scoping)', function (): void {
        $other = createAgent($this->owner);

        $this->chatter->agents()->attach($other->id, ['workspace_id' => $other->workspace_id]);

        $token = $this->service->issueHmac($this->chatter, $other)['token'];

        // The signature is valid against `$other` but not against `$this->agent`,
        // because the secret is per-agent.
        expect($this->service->verify($token, $this->agent))->toBeNull();
    });

    it('rejects an expired credential', function (): void {
        $token = astsHmac($this->chatter->external_id, now()->subSecond()->getTimestamp(), $this->agent->ensureAuthSecret());

        expect($this->service->verify($token, $this->agent))->toBeNull();
    });

    it('rejects a credential with a lifetime over the 24h cap', function (): void {
        $token = astsHmac($this->chatter->external_id, now()->addSeconds(AgentSignInTokenService::MAX_TTL_SECONDS + 60)->getTimestamp(), $this->agent->ensureAuthSecret());

        expect($this->service->verify($token, $this->agent))->toBeNull();
    });

    it('rejects malformed credentials', function (string $token): void {
        expect($this->service->verify($token, $this->agent))->toBeNull();
    })->with([
        'missing segments' => 'cvh1.only-one-segment',
        'two segments' => 'cvh1.user_123.1234567890',
        'non-numeric exp' => fn (): string => 'cvh1.user_123.notanumber.'.Str::repeat('a', 64),
        'non-hex sig' => fn (): string => 'cvh1.user_123.'.now()->addMinutes(5)->getTimestamp().'.'.Str::repeat('z', 64),
        'wrong sig length' => fn (): string => 'cvh1.user_123.'.now()->addMinutes(5)->getTimestamp().'.deadbeef',
        'empty user_id' => fn (): string => 'cvh1..'.now()->addMinutes(5)->getTimestamp().'.'.Str::repeat('a', 64),
    ]);
});

it('rejects a validly-signed token whose sub is not a UUID instead of throwing', function (): void {
    // A customer who signs their own internal user id ("2") rather than the
    // Chatvia external_id must get "not signed in", not a Postgres uuid cast
    // error from the managed-user lookup.
    $secret = $this->agent->ensureAuthSecret();
    $exp = now()->addMinutes(5)->getTimestamp();

    $jwt = JWT::encode(['sub' => '2', 'aud' => $this->agent->external_id, 'exp' => $exp], $secret, 'HS256');
    $hmac = astsHmac('2', $exp, $secret);

    expect($this->service->verify($jwt, $this->agent))->toBeNull()
        ->and($this->service->verify($hmac, $this->agent))->toBeNull();
});
