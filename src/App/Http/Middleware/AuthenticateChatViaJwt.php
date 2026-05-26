<?php

namespace App\Http\Middleware;

use App\Auth\Models\User;
use App\Auth\Services\AgentJwtVerifier;
use Closure;
use Domain\Agents\Models\Agent;
use Domain\Agents\Services\Agent as AgentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Verifies a JWT in `?token=` against the owning account's `jwt_secret`.
 *
 * Sits in the chat middleware group AFTER the agent is resolved from the
 * subdomain. The verifier looks up `agent->user->jwt_secret` — one shared
 * secret per account signs JWTs for every agent that account owns.
 *
 * On a valid token we:
 *   1. Upsert a User row representing the external identity (keyed by the
 *      account owner + the JWT `sub` claim) so the existing chat middleware
 *      stack — which gates on `Auth::guard('web')->check()` — sees an
 *      authenticated user. These users live under `managed_by_user_id` =
 *      the account owner and are never attached to a team, so they don't
 *      leak into the customer's staff list.
 *   2. Log that user in on the `web` guard.
 *   3. Stash the raw JWT claims on the session so downstream
 *      ChatSession / lead-capture code can read the original sub / metadata.
 *
 * On a present-but-invalid token we 401 — a signature mismatch means
 * tampering or a rotated secret; silent fallthrough would be a security hole.
 * No token at all is fine; anonymous chat continues if the agent is public.
 */
class AuthenticateChatViaJwt
{
    public function __construct(
        private readonly AgentJwtVerifier $verifier,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->query('token');

        if (! is_string($token) || $token === '') {
            return $next($request);
        }

        $agent = $this->resolveAgent($request);

        if (! $agent) {
            return $next($request);
        }

        $identity = $this->verifier->verify($agent, $token);

        if (! $identity) {
            abort(401, 'Invalid or expired chat token.');
        }

        $user = $this->upsertJwtUser($agent, $identity);

        Auth::guard('web')->loginUsingId($user->id);

        $request->session()->put('chat.identity', [
            'agent_id' => $agent->id,
            'sub' => $identity['sub'],
            'email' => $identity['email'],
            'name' => $identity['name'],
            'metadata' => $identity['metadata'] ?? [],
            'verified_at' => now()->toIso8601String(),
        ]);

        // Redirect to the same URL without the token — keeps the bearer
        // out of browser history / referer headers. The session cookie
        // now carries the auth state.
        if ($request->isMethod('GET')) {
            return redirect()->to($request->url());
        }

        return $next($request);
    }

    /**
     * Find or create the User row that represents this JWT identity. Keyed by
     * `(account owner, jwt.sub)` via a deterministic synthetic email so repeat
     * visits resolve to the same User and conversation history is continuous.
     */
    private function upsertJwtUser(Agent $agent, array $identity): User
    {
        $ownerId = $agent->user_id;
        $sub = $identity['sub'];

        // Synthetic, namespace-collision-proof email. Using a non-routable
        // suffix so it can't be confused with a real user account.
        $email = sprintf('jwt+%d+%s@external.chatvia.invalid', $ownerId, hash('sha256', $sub));

        $user = User::firstOrNew(['email' => $email]);

        if (! $user->exists) {
            $user->forceFill([
                'name' => $identity['name'] ?? ('user-'.Str::limit($sub, 20, '')),
                'email' => $email,
                'password' => Hash::make(Str::random(64)),
                'type' => 'staff',
                'managed_by_user_id' => $ownerId,
                'email_verified_at' => now(),
            ])->save();
        } elseif ($identity['name'] && $user->name !== $identity['name']) {
            // Keep the display name fresh on subsequent visits.
            $user->forceFill(['name' => $identity['name']])->save();
        }

        return $user;
    }

    private function resolveAgent(Request $request): ?Agent
    {
        $subdomain = $request->subdomain();

        if (! $subdomain) {
            return null;
        }

        return AgentService::resolveAgentFromSubdomain($subdomain);
    }
}
