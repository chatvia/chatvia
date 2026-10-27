<?php

namespace App\Identity\Workspace\Services;

use App\Identity\Chatter\Models\Chatter;
use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Throwable;

/**
 * Mints and verifies the self-contained sign-in credentials that log an agent's
 * end-user into its chat (the `?token=` link) or its protected widget (the
 * `data-auth` attribute).
 *
 * Two interchangeable schemes are accepted in the same slot; the customer picks
 * whichever fits their backend. Both are signed with the agent's `auth_secret`
 * (≥256 bits — see {@see Agent::regenerateAuthSecret()}), both are stateless,
 * and both go through the same downstream access checks. A credential signed
 * with the secret names a chatter only; staff credentials (the Live Preview,
 * an owner's API link) are minted here with the server-only staff key.
 *
 *  1. **JWT (HS256)** — the default. Standard JSON Web Token with three
 *     registered claims plus one optional custom claim:
 *       - sub: the chatter's external_id (who to log in). A staff account
 *              (owner or teammate) is accepted only in a credential the
 *              server minted, signed with the staff key ({@see staffKey()}).
 *       - aud: the agent's external_id (which agent the token is good for)
 *       - exp: absolute Unix expiry
 *       - ctx: optional context tag the dashboard preview uses to mark a token
 *              as coming from the Live Preview iframe ("playground").
 *              Customer-minted tokens never carry this; the verifier surfaces
 *              it to callers so downstream code can branch without inspecting
 *              easily-spoofed HTTP signals.
 *     Any off-the-shelf library (jsonwebtoken, firebase/php-jwt, PyJWT, …) can
 *     mint it.
 *
 *  2. **Compact HMAC credential** — for customers without a JWT library. A
 *     four-segment string of the form `cvh1.<user_id>.<exp>.<sig>` where
 *     `<sig> = hmac_sha256("<user_id>.<exp>", auth_secret)` (lowercase hex).
 *     The audience is implicit: the secret is per-agent, so a credential
 *     signed against agent A cannot validate under agent B. Mint it with the
 *     language's standard library (`crypto.createHmac` / `hash_hmac`).
 *
 * Verification is stateless: the signature is checked, the expiry is enforced,
 * and the lifetime is capped at {@see MAX_TTL_SECONDS} so a customer-signed
 * credential can't become a long-lived bearer secret. Nothing is consumed, so
 * a credential works on repeat opens until it expires.
 */
class AgentSignInTokens
{
    public const int DEFAULT_TTL_SECONDS = 3600;

    public const int MIN_TTL_SECONDS = 60;

    // 24 hours. Long enough for a chat widget to stay signed in across a
    // workday and for the dashboard's Live Preview to keep a single cached
    // token without re-minting per request. Beyond that the embed script is
    // expected to refresh via a new mint on the customer's backend.
    public const int MAX_TTL_SECONDS = 86400;

    public const string CTX_PLAYGROUND = 'playground';

    /**
     * Scheme tag for the compact HMAC credential, used to disambiguate it from
     * a JWT in the same `data-auth` / `?token=` slot. A JWT always starts with
     * `eyJ` (the base64url of `{"`), so there's no ambiguity.
     */
    public const string HMAC_PREFIX = 'cvh1.';

    private const string ALGORITHM = 'HS256';

    public function __construct()
    {
        // Pin clock skew tolerance to zero so a future jwt-lib upgrade can't
        // widen acceptance silently. The `exp` claim is already integer
        // seconds, so leeway:0 is correct for our use case.
        JWT::$leeway = 0;
    }

    /**
     * Mint a token for an external user against an agent. Used by the dashboard
     * link generator; customers mint their own with the same algorithm.
     *
     * @return array{token: string, expires_at: Carbon}
     */
    public function issue(
        Chatter|User $user,
        Agent $agent,
        int $ttlSeconds = self::DEFAULT_TTL_SECONDS,
        ?string $ctx = null,
    ): array {
        $ttlSeconds = max(self::MIN_TTL_SECONDS, min(self::MAX_TTL_SECONDS, $ttlSeconds));
        $expiresAt = Carbon::now()->addSeconds($ttlSeconds);

        $payload = [
            'sub' => $user->external_id,
            'aud' => $agent->external_id,
            'exp' => $expiresAt->getTimestamp(),
        ];

        if ($ctx !== null) {
            $payload['ctx'] = $ctx;
        }

        $token = JWT::encode($payload, $this->signingKey($user, $agent), self::ALGORITHM);

        return ['token' => $token, 'expires_at' => $expiresAt];
    }

    /**
     * Mint a compact HMAC credential — the dependency-free alternative to
     * {@see issue()} for customers without a JWT library. Same TTL bounds,
     * same per-agent secret, same downstream verification.
     *
     * @return array{token: string, expires_at: Carbon}
     */
    public function issueHmac(
        Chatter|User $user,
        Agent $agent,
        int $ttlSeconds = self::DEFAULT_TTL_SECONDS,
    ): array {
        $ttlSeconds = max(self::MIN_TTL_SECONDS, min(self::MAX_TTL_SECONDS, $ttlSeconds));
        $expiresAt = Carbon::now()->addSeconds($ttlSeconds);

        $userId = $user->external_id;
        $exp = $expiresAt->getTimestamp();
        $sig = hash_hmac('sha256', "{$userId}.{$exp}", $this->signingKey($user, $agent));

        return [
            'token' => self::HMAC_PREFIX."{$userId}.{$exp}.{$sig}",
            'expires_at' => $expiresAt,
        ];
    }

    /**
     * Verify a credential against an agent and resolve it to the account it was
     * minted for — a chatter of the agent's workspace granted the agent, or
     * (for a server-minted credential) the agent's owner or a teammate with
     * permission to reach the agent — or
     * null when the signature, audience, expiry, or access check fails (or
     * the account has no signing secret yet).
     *
     * Accepts both schemes: a JWT (default) or a compact HMAC credential
     * (prefix `cvh1.`). See the class docblock.
     */
    public function verify(string $token, Agent $agent): Chatter|User|null
    {
        return $this->verifyWithClaims($token, $agent)['user'] ?? null;
    }

    /**
     * Same as {@see verify()} but returns the resolved user alongside the
     * decoded custom claims (currently just `ctx`). Returns null on any of
     * the same failures verify() rejects on.
     *
     * @return array{user: Chatter|User, ctx: ?string}|null
     */
    public function verifyWithClaims(string $token, Agent $agent): ?array
    {
        $secret = $agent->auth_secret;

        if (blank($token) || blank($secret)) {
            return null;
        }

        // The agent's own secret signs chatters only; a staff account needs
        // the staff key, which only the server can derive.
        return $this->verifySigned($token, $agent, $secret, Chatter::class)
            ?? $this->verifySigned($token, $agent, $this->staffKey($secret), User::class);
    }

    /**
     * The key a credential for this account is signed with: the agent's
     * `auth_secret` for a chatter, the server-only staff key for staff.
     */
    private function signingKey(Chatter|User $user, Agent $agent): string
    {
        $secret = $agent->ensureAuthSecret();

        return $user instanceof User ? $this->staffKey($secret) : $secret;
    }

    /**
     * Teammates with `agent.identity.manage` can read the agent's secret, so a
     * credential signed with it must never log anyone in as staff: they could
     * otherwise sign in to the chat as the owner. Staff credentials are signed
     * with this key instead, derived from the secret and the app key.
     */
    private function staffKey(string $secret): string
    {
        return hash_hmac('sha256', "agent-sign-in-staff|{$secret}", (string) config('app.key'));
    }

    /**
     * Verify a credential of either scheme with one key and resolve its
     * subject, which must be an account of the given class.
     *
     * @param  class-string<Chatter|User>  $accountClass
     * @return array{user: Chatter|User, ctx: ?string}|null
     */
    private function verifySigned(string $token, Agent $agent, string $key, string $accountClass): ?array
    {
        // Dispatch on the scheme tag. A JWT always starts with `eyJ`, so the
        // `cvh1.` prefix is unambiguous.
        if (Str::startsWith($token, self::HMAC_PREFIX)) {
            $sub = $this->verifyHmacSubject($token, $key);

            if ($sub === null) {
                return null;
            }

            // HMAC credentials carry no `ctx` — the playground tag is a
            // JWT-only, dashboard-minted concern.
            return $this->resolveSubject($agent, $sub, null, $accountClass);
        }

        try {
            // Pinning the key to HS256 makes the library reject any other
            // algorithm (including "none"), closing the algorithm-confusion
            // hole. This also verifies the signature and the `exp` claim.
            $payload = JWT::decode($token, new Key($key, self::ALGORITHM));
        } catch (Throwable) {
            // Fail closed: any malformed, tampered, expired, or wrong-secret
            // token resolves to "not signed in".
            return null;
        }

        if (! isset($payload->sub, $payload->aud, $payload->exp)) {
            return null;
        }

        if (! hash_equals($agent->external_id, (string) $payload->aud)) {
            return null;
        }

        // Reject a token minted with a longer life than we permit, so a
        // customer-signed token can't become a long-lived bearer credential.
        // (Expiry itself is already enforced by JWT::decode above.)
        if ((int) $payload->exp - Carbon::now()->getTimestamp() > self::MAX_TTL_SECONDS) {
            return null;
        }

        $ctx = isset($payload->ctx) ? (string) $payload->ctx : null;

        return $this->resolveSubject($agent, (string) $payload->sub, $ctx, $accountClass);
    }

    /**
     * Verify a `cvh1.<user_id>.<exp>.<sig>` credential against the agent's
     * secret and return its subject (the user's external_id) on success, or
     * null on any mismatch — malformed, tampered, expired, or over-cap.
     *
     * Per-agent audience binding is implicit: the secret is per-agent, so a
     * credential signed against agent A cannot validate under agent B.
     */
    private function verifyHmacSubject(string $token, string $secret): ?string
    {
        // Byte offsets: the HMAC covers the raw bytes of the token.
        $body = substr($token, strlen(self::HMAC_PREFIX));

        // The sig (hex) and exp (digits) carry no dots, so split from the
        // right — that leaves the user_id free to itself contain dots
        // (e.g. an email address).
        $lastDot = strrpos($body, '.');
        if ($lastDot === false) {
            return null;
        }
        $sig = substr($body, $lastDot + 1);
        $head = substr($body, 0, $lastDot);

        $prevDot = strrpos($head, '.');
        if ($prevDot === false) {
            return null;
        }
        $exp = substr($head, $prevDot + 1);
        $userId = substr($head, 0, $prevDot);

        if ($userId === '' || $exp === '' || ! ctype_digit($exp) || ! Str::isMatch('/^[a-f0-9]{64}$/', $sig)) {
            return null;
        }

        $expected = hash_hmac('sha256', "{$userId}.{$exp}", $secret);

        if (! hash_equals($expected, $sig)) {
            return null;
        }

        $now = Carbon::now()->getTimestamp();
        $expInt = (int) $exp;

        // Reject expired credentials, and credentials minted with a longer
        // life than we permit, so a customer-signed credential can't become a
        // long-lived bearer secret.
        if ($expInt <= $now || $expInt - $now > self::MAX_TTL_SECONDS) {
            return null;
        }

        return $userId;
    }

    /**
     * Resolve an authenticated subject (`sub`) against the agent and apply
     * the shared access checks — workspace lookup, `canAccessAgent`, and
     * deactivation gate. Shared by both schemes so authorization stays
     * identical; only authentication differs.
     *
     * @param  class-string<Chatter|User>  $accountClass
     * @return array{user: Chatter|User, ctx: ?string}|null
     */
    private function resolveSubject(Agent $agent, string $sub, ?string $ctx, string $accountClass): ?array
    {
        // `external_id` is a Postgres uuid column, so a non-UUID `sub` (a
        // customer signing their own internal id like "2" instead of the
        // Chatvia external_id) would make the driver throw. Treat it as an
        // unknown subject instead — fail closed, don't 500.
        if (! Str::isUuid($sub)) {
            return null;
        }

        // The subject is a chatter or a staff account (owner or teammate) of
        // THIS workspace, and must hold access to THIS agent: a chatter through
        // its agent grants, staff through ownership or their team role
        // (User::canAccessAgent → TeamAgentAccess). Membership alone is NOT
        // sufficient.
        $account = $accountClass === Chatter::class
            ? Chatter::query()->where('workspace_id', $agent->workspace_id)->where('external_id', $sub)->first()
            : $agent->workspace->members()->where('users.external_id', $sub)->first();

        if (! $account?->canAccessAgent($agent)) {
            return null;
        }

        // A deactivated chatter/teammate loses access immediately — this gates
        // both the widget bootstrap exchange and the hosted-chat sign-in link.
        if ($account->isDeactivated()) {
            return null;
        }

        return ['user' => $account, 'ctx' => $ctx];
    }
}
