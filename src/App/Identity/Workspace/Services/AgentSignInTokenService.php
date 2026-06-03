<?php

namespace App\Identity\Workspace\Services;

use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Mints and verifies the self-contained sign-in tokens that log an agent's
 * end-user into its chat (the `?token=` link) or its protected widget (the
 * `data-auth` attribute).
 *
 * The token is a standard JSON Web Token signed with HS256 (HMAC-SHA256) using
 * the agent's `auth_secret` as the shared key (≥256 bits of entropy — see
 * {@see Agent::regenerateAuthSecret()}), so customers can mint their own with
 * any off-the-shelf JWT library (jsonwebtoken, firebase/php-jwt, PyJWT, …) —
 * see the docs on the agent's Identity Verification tab. The payload uses
 * registered claims plus one optional custom claim:
 *   - sub: the external user's external_id (who to log in)
 *   - aud: the agent's external_id (which agent the token is good for)
 *   - exp: absolute Unix expiry
 *   - ctx: optional context tag the dashboard preview uses to mark a token as
 *          coming from the Live Preview iframe ("playground"). Customer-minted
 *          tokens never carry this; the verifier surfaces it to callers so
 *          downstream code can branch without inspecting raw HTTP signals
 *          (Referer, etc.).
 *
 * Verification is stateless: the JWT library checks the HS256 signature and the
 * `exp` claim; we additionally pin the audience to the agent and cap the
 * lifetime so a customer-signed token can't become a long-lived bearer
 * credential. Nothing is consumed, so a token works on repeat opens until it
 * expires.
 */
class AgentSignInTokenService
{
    public const DEFAULT_TTL_SECONDS = 3600;

    public const MIN_TTL_SECONDS = 60;

    // 24 hours. Long enough for a chat widget to stay signed in across a
    // workday and for the dashboard's Live Preview to keep a single cached
    // token without re-minting per request. Beyond that the embed script is
    // expected to refresh via a new mint on the customer's backend.
    public const MAX_TTL_SECONDS = 86400;

    public const CTX_PLAYGROUND = 'playground';

    private const ALGORITHM = 'HS256';

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
        User $user,
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

        $token = JWT::encode($payload, $agent->ensureAuthSecret(), self::ALGORITHM);

        return ['token' => $token, 'expires_at' => $expiresAt];
    }

    /**
     * Verify a token against an agent and resolve it to the user it was minted
     * for — the agent's owner, or a managed user with permission to reach the
     * agent — or null when the signature, audience, expiry, or access check
     * fails (or the account has no signing secret yet).
     */
    public function verify(string $token, Agent $agent): ?User
    {
        return $this->verifyWithClaims($token, $agent)['user'] ?? null;
    }

    /**
     * Same as {@see verify()} but returns the resolved user alongside the
     * decoded custom claims (currently just `ctx`). Returns null on any of
     * the same failures verify() rejects on.
     *
     * @return array{user: User, ctx: ?string}|null
     */
    public function verifyWithClaims(string $token, Agent $agent): ?array
    {
        $secret = $agent->auth_secret;

        if (blank($token) || blank($secret)) {
            return null;
        }

        try {
            // Pinning the key to HS256 makes the library reject any other
            // algorithm (including "none"), closing the algorithm-confusion
            // hole. This also verifies the signature and the `exp` claim.
            $payload = JWT::decode($token, new Key($secret, self::ALGORITHM));
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

        $owner = $agent->user;
        $ctx = isset($payload->ctx) ? (string) $payload->ctx : null;

        // The owner always has access to their own agent. (They are not part
        // of their own users() set — their workspace_id is null — so match
        // them explicitly.)
        if (hash_equals($owner->external_id, (string) $payload->sub)) {
            return ['user' => $owner, 'ctx' => $ctx];
        }

        // Any other subject must be a user this account manages AND hold
        // permission to reach THIS specific agent. Access is role-based: a
        // role grants either all agents or an explicit list
        // (User::canAccessAgent → TeamService). Account membership alone is
        // NOT sufficient.
        //
        // `users()` is a BelongsToMany on User → the related model is User
        // by construction, but the relation's static type erases to
        // `Model|null`. The `@var` brings the concrete type back so the
        // `canAccessAgent()` call and array return both typecheck.
        /** @var User|null $user */
        $user = $owner->users()->where('external_id', $payload->sub)->first();

        if (! $user?->canAccessAgent($agent)) {
            return null;
        }

        return ['user' => $user, 'ctx' => $ctx];
    }
}
