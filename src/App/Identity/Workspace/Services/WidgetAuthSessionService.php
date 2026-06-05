<?php

namespace App\Identity\Workspace\Services;

use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Issues and resolves the opaque, cache-backed session tokens a protected widget
 * carries in the `X-AUTH-TOKEN` header.
 *
 * Flow: the embedding site signs a short-lived bootstrap token (the embed's
 * `data-auth`, verified by {@see AgentSignInTokenService}); the widget exchanges
 * it once, on load, for one of these session tokens, then sends the session
 * token on every subsequent widget request. Decoupling the working credential
 * from the bootstrap token lets a long-open page outlive the bootstrap token's
 * brief expiry. Each token is bound to the (user, agent) it was minted for,
 * fingerprinted to the IP + User-Agent it was issued from, and is invalidated
 * only by its TTL.
 *
 * The IP/UA fingerprint is best-effort defence in depth: if a token is
 * exfiltrated (XSS on the embedding page, console.log leak, etc.) it can't be
 * trivially replayed from a different machine. NAT roaming will produce
 * mismatches occasionally — the widget handles that by re-running the exchange.
 */
class WidgetAuthSessionService
{
    private const string PREFIX = 'user:{userId}:widget:auth:';

    // Four hours. Long enough for a typical chat session; short enough that an
    // exfiltrated token has a small useful life. The customer's backend
    // re-issues the bootstrap JWT per page load anyway.
    public const int TTL_SECONDS = 14400;

    /**
     * @param  array<string, mixed>  $meta  optional context attached to the
     *                                      session — e.g. `['is_playground' => true]`
     *                                      set by the dashboard Live Preview path.
     * @return array{token: string, expires_at: Carbon}
     */
    public function issue(User $user, Agent $agent, Request $request, array $meta = []): array
    {
        $token = Str::random(64);
        $expiresAt = Carbon::now()->addSeconds(self::TTL_SECONDS);

        Cache::put(template(self::PREFIX.$token, ['userId' => $user->id]), [
            'user_id' => $user->id,
            'agent_id' => $agent->id,
            'fingerprint' => $this->fingerprint($request),
            'meta' => $meta,
        ], $expiresAt);

        return ['token' => $token, 'expires_at' => $expiresAt];
    }

    /**
     * Resolve a session token to the user it was minted for on this agent, or
     * null when unknown, expired, minted for a different agent, or coming from
     * a different IP/User-Agent than the issuing request.
     *
     * No `source` filter: the exchange mints these via
     * {@see AgentSignInTokenService::verify()}, whose valid subject is the agent
     * owner OR a managed user entitled to the agent — not external users alone.
     * Access was checked at mint time; the token is then bound to (user, agent,
     * fingerprint) and good until its TTL.
     *
     * @return array{user: User, meta: array<string, mixed>}|null
     */
    public function resolveWithMeta(string $token, Agent $agent, Request $request): ?array
    {
        $payload = Cache::get(template(self::PREFIX.$token, ['userId' => $agent->user_id]));

        if (! is_array($payload) || ! isset($payload['user_id'], $payload['agent_id'])) {
            return null;
        }

        if ((int) $payload['agent_id'] !== $agent->id) {
            return null;
        }

        // Best-effort fingerprint match. A token issued from one (IP, UA)
        // tuple shouldn't suddenly carry a different one — that's the
        // exfiltrated-token replay signature. Constant-time compare.
        $expectedFp = (string) ($payload['fingerprint'] ?? '');
        if ($expectedFp !== '' && ! hash_equals($expectedFp, $this->fingerprint($request))) {
            return null;
        }

        $user = User::query()->whereKey($payload['user_id'])->first();

        if (! $user) {
            return null;
        }

        return [
            'user' => $user,
            'meta' => is_array($payload['meta'] ?? null) ? $payload['meta'] : [],
        ];
    }

    /**
     * Backwards-compatible shape — returns the user only. Use
     * {@see resolveWithMeta()} when you need the playground / context flags.
     */
    public function resolve(string $token, Agent $agent, Request $request): ?User
    {
        return $this->resolveWithMeta($token, $agent, $request)['user'] ?? null;
    }

    /**
     * Tear down a single widget session — used when an owner revokes a stolen
     * token from the dashboard, or when the user explicitly logs out.
     */
    public function revoke(string $token): void
    {
        Cache::forget(self::PREFIX.$token);
    }

    /**
     * IP + User-Agent hash — captured at issue time, checked on every
     * subsequent request. We hash both so the cached payload doesn't carry
     * raw client data, and use HMAC so a leaked cache snapshot can't be used
     * to forge a matching fingerprint.
     */
    private function fingerprint(Request $request): string
    {
        $material = ($request->ip() ?? '').'|'.($request->userAgent() ?? '');

        return hash_hmac('sha256', $material, (string) config('app.key'));
    }
}
