<?php

use App\Identity\Chatter\Models\Chatter;
use App\Identity\Workspace\Models\User;
use App\Identity\Workspace\Services\AgentSignInTokens;
use Domain\Membership\Team\Actions\Team\CreateDefaultTeamAction;
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
    app(CreateDefaultTeamAction::class)->execute($this->owner);
    $this->agent = createAgent($this->owner);
    $this->chatter = Chatter::factory()->grantedTo($this->agent)->create();
    $this->service = app(AgentSignInTokens::class);
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

it('verifies issued and customer-signed tokens back to the chatter or staff account that reaches the agent', function (string $issuer): void {
    $teammate = teammateWithPermissions($this->owner, [], allAgents: false, agentExternalIds: [$this->agent->external_id]);
    $stranger = User::factory()->employee($this->owner)->create(['current_team_id' => $this->owner->primaryTeam()->id]);
    $this->freezeTime();
    $secret = $this->agent->ensureAuthSecret();
    $customerSigned = $issuer === 'issue'
        ? JWT::encode(['sub' => $this->chatter->external_id, 'aud' => $this->agent->external_id, 'exp' => now()->addSeconds(AgentSignInTokens::MAX_TTL_SECONDS)->getTimestamp()], $secret, 'HS256')
        : astsHmac($this->chatter->external_id, now()->addMinutes(10)->getTimestamp(), $secret);

    $verify = fn (Chatter|User $account): Chatter|User|null => $this->service->verify($this->service->{$issuer}($account, $this->agent)['token'], $this->agent);

    expect($verify($this->owner)?->external_id)->toBe($this->owner->external_id)
        ->and($verify($teammate)?->external_id)->toBe($teammate->external_id)
        ->and($verify($this->chatter))->toBeInstanceOf(Chatter::class)->id->toBe($this->chatter->id)
        ->and($verify($stranger))->toBeNull()
        ->and($this->service->verify($customerSigned, $this->agent)?->id)->toBe($this->chatter->id);
})->with([
    'JWT' => 'issue',
    'HMAC' => 'issueHmac',
]);

it('never lets the two keys cross: no staff in an agent-secret token, no chatter in a staff-key token', function (): void {
    $teammate = teammateWithPermissions($this->owner, [], allAgents: false, agentExternalIds: [$this->agent->external_id]);
    $secret = $this->agent->ensureAuthSecret();
    $exp = now()->addMinutes(10)->getTimestamp();
    $staffSigned = $this->service->issue($this->owner, $this->agent)['token'];
    $payload = JWT::decode($staffSigned, new Key(astsStaffKey($secret), 'HS256'));
    $payload->sub = $this->chatter->external_id;

    $forgedChatter = JWT::encode((array) $payload, astsStaffKey($secret), 'HS256');

    foreach ([$this->owner, $teammate] as $account) {
        $jwt = JWT::encode(['sub' => $account->external_id, 'aud' => $this->agent->external_id, 'exp' => $exp], $secret, 'HS256');

        expect($this->service->verify($jwt, $this->agent))->toBeNull()
            ->and($this->service->verify(astsHmac($account->external_id, $exp, $secret), $this->agent))->toBeNull()
            ->and($this->service->verify($this->service->issue($account, $this->agent)['token'], $this->agent)?->id)->toBe($account->id);
    }
    expect($this->service->verify($forgedChatter, $this->agent))->toBeNull();
});

it('refuses a tampered, foreign, expired, too-long-lived or malformed token without throwing', function (string $token): void {
    expect($this->service->verify($token, $this->agent))->toBeNull();
})->with([
    'a tampered signature' => function (): string {
        $token = $this->service->issueHmac($this->owner, $this->agent)['token'];

        return Str::substr($token, 0, -1).(Str::endsWith($token, '0') ? '1' : '0');
    },
    'the wrong secret' => fn (): string => astsHmac($this->chatter->external_id, now()->addMinutes(10)->getTimestamp(), 'not-the-real-secret'),
    'another agent\'s credential' => function (): string {
        $other = createAgent($this->owner);
        $this->chatter->agents()->attach($other->id, ['workspace_id' => $other->workspace_id]);

        return $this->service->issueHmac($this->chatter, $other)['token'];
    },
    'an expired credential' => fn (): string => astsHmac($this->chatter->external_id, now()->subSecond()->getTimestamp(), $this->agent->ensureAuthSecret()),
    'a credential over the 24h cap' => fn (): string => astsHmac($this->chatter->external_id, now()->addSeconds(AgentSignInTokens::MAX_TTL_SECONDS + 60)->getTimestamp(), $this->agent->ensureAuthSecret()),
    'a JWT over the 24h cap' => fn (): string => JWT::encode(['sub' => $this->chatter->external_id, 'aud' => $this->agent->external_id, 'exp' => now()->addSeconds(AgentSignInTokens::MAX_TTL_SECONDS + 60)->getTimestamp()], $this->agent->ensureAuthSecret(), 'HS256'),
    'missing segments' => 'cvh1.only-one-segment',
    'two segments' => 'cvh1.user_123.1234567890',
    'a non-numeric exp' => fn (): string => 'cvh1.user_123.notanumber.'.Str::repeat('a', 64),
    'a non-hex signature' => fn (): string => 'cvh1.user_123.'.now()->addMinutes(5)->getTimestamp().'.'.Str::repeat('z', 64),
    'an empty user id' => fn (): string => 'cvh1..'.now()->addMinutes(5)->getTimestamp().'.'.Str::repeat('a', 64),
    'a JWT whose sub is not a UUID' => fn (): string => JWT::encode(['sub' => '2', 'aud' => $this->agent->external_id, 'exp' => now()->addMinutes(5)->getTimestamp()], $this->agent->ensureAuthSecret(), 'HS256'),
    'a credential whose sub is not a UUID' => fn (): string => astsHmac('2', now()->addMinutes(5)->getTimestamp(), $this->agent->ensureAuthSecret()),
]);

it('issues tokens signed with the agent secret, living one minute to 24 hours, tagged playground only when asked', function (): void {
    $this->freezeTime();
    $secret = $this->agent->ensureAuthSecret();

    ['token' => $jwt, 'expires_at' => $jwtExpiry] = $this->service->issue($this->chatter, $this->agent);
    ['token' => $hmac, 'expires_at' => $hmacExpiry] = $this->service->issueHmac($this->chatter, $this->agent);
    $shortest = $this->service->issue($this->owner, $this->agent, ttlSeconds: 5)['expires_at'];
    $longest = $this->service->issue($this->owner, $this->agent, ttlSeconds: 999_999)['expires_at'];
    $tagged = $this->service->verifyWithClaims($this->service->issue($this->owner, $this->agent, ctx: AgentSignInTokens::CTX_PLAYGROUND)['token'], $this->agent);
    $untagged = $this->service->verifyWithClaims($this->service->issueHmac($this->owner, $this->agent)['token'], $this->agent);

    $payload = JWT::decode($jwt, new Key($secret, 'HS256'));
    $exp = (string) $hmacExpiry->getTimestamp();
    expect($payload->sub)->toBe($this->chatter->external_id)
        ->and($payload->aud)->toBe($this->agent->external_id)
        ->and((int) $payload->exp)->toBe($jwtExpiry->getTimestamp())
        ->and($hmac)->toBe('cvh1.'.$this->chatter->external_id.'.'.$exp.'.'.hash_hmac('sha256', $this->chatter->external_id.'.'.$exp, $secret))
        ->and($shortest->getTimestamp())->toBe(now()->addSeconds(AgentSignInTokens::MIN_TTL_SECONDS)->getTimestamp())
        ->and($longest->getTimestamp())->toBe(now()->addSeconds(AgentSignInTokens::MAX_TTL_SECONDS)->getTimestamp())
        ->and($tagged['user']->id)->toBe($this->owner->id)
        ->and($tagged['ctx'])->toBe('playground')
        ->and($untagged['ctx'])->toBeNull();
});
