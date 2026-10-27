<?php

namespace App\Providers;

use App\Http\Chat\HostedChatAgent;
use App\Http\Widget\WidgetAgent;
use Domain\Agent\Models\Agent;
use Domain\Widget\WidgetHeaders;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

/**
 * Named rate limiters used by route `throttle:<name>` middleware. The login,
 * two-factor and passkey limiters belong to Fortify and live in
 * {@see FortifyServiceProvider}.
 */
class RateLimitServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->configureWidgetLimiters();
        $this->configureApiLimiters();
        $this->configureChatLimiters();
    }

    private function configureWidgetLimiters(): void
    {
        // Bulk gate for every /widget/* endpoint. Per-IP because widget
        // traffic is unauthenticated for public agents and we want a single
        // ceiling regardless of how many sessions an IP spins up.
        RateLimiter::for('widgets', fn (Request $request) => Limit::perMinute(90)->by($request->ip()));

        // Every stream call costs an LLM invocation billed to the agent's
        // OWNING workspace, so throttling protects that tenant's spend as well
        // as the platform. The per-session key alone is forgeable (a client
        // mints unlimited session ids and can proxy across IPs), so a ceiling
        // per server-resolved agent is stacked on top: an attacker cannot drain
        // a public agent's monthly message limit by multiplying sessions. Applies to /widget/stream and attachments.
        RateLimiter::for('widget-stream', function (Request $request): array {
            $sessionKey = $request->header(WidgetHeaders::SESSION_ID) ?: $request->ip();

            $limits = [
                Limit::perMinute(20)->by("widget-stream|sess|{$sessionKey}"),
            ];

            $agent = WidgetAgent::from($request);

            if ($agent instanceof Agent) {
                $limits[] = Limit::perMinute((int) config('chatvia.widget.stream_per_agent_per_minute', 120))
                    ->by("widget-stream|agent|{$agent->id}");
                $limits[] = Limit::perHour((int) config('chatvia.widget.stream_per_agent_per_hour', 1500))
                    ->by("widget-stream|agent-hour|{$agent->id}");
            }

            return $limits;
        });

        // Starting a voice call: each call holds a worker and costs far more
        // than a message, so a session may start only a few a minute, and an
        // agent only so many across all its visitors.
        RateLimiter::for('widget-voice', function (Request $request): array {
            $sessionKey = $request->header(WidgetHeaders::SESSION_ID) ?: $request->ip();
            $limits = [Limit::perMinute(5)->by("widget-voice|sess|{$sessionKey}")];
            $agent = WidgetAgent::from($request);

            if ($agent instanceof Agent) {
                $limits[] = Limit::perMinute(30)->by("widget-voice|agent|{$agent->id}");
            }

            return $limits;
        });

        // The auth exchange runs once per page load for protected widgets. A
        // tight per-IP cap removes JWT-verification brute force as a DoS
        // surface (defence in depth — verify() is already constant-time).
        RateLimiter::for(
            'widget-exchange',
            fn (Request $request) => Limit::perMinute(10)->by("widget-exchange|{$request->ip()}"),
        );
    }

    private function configureApiLimiters(): void
    {
        RateLimiter::for('api', fn (Request $request) => $request->user()
            ? Limit::perMinute(300)->by("{$request->user()->getAuthIdentifier()}|".$request->user()::class)
            : Limit::perMinute(60)->by($request->ip()));

        // The public API's message endpoint costs an LLM turn, so it gets the
        // hosted chat's cap, not the plain `api` one. Keyed by the token's
        // account (API callers are always staff users).
        RateLimiter::for('api-chat', fn (Request $request) => Limit::perMinute(30)
            ->by('api-chat|'.($request->user()?->getAuthIdentifier() ?? $request->ip())));

        // Built-in MCP endpoints are only reachable by our own signed tool
        // loop; key the backstop by the calling agent so one runaway agent
        // loop can't exhaust the budget for every other agent.
        RateLimiter::for('builtin-mcp', fn (Request $request) => Limit::perMinute(120)
            ->by('builtin-mcp|'.((string) $request->header('X-Chatvia-Agent-Id') ?: $request->ip())));
    }

    private function configureChatLimiters(): void
    {
        // Chatter password sign-in. Two layers: a per-IP ceiling, and a
        // tighter counter per workspace + email + IP, so hammering one
        // mailbox locks only that form while the workspace next door stays
        // reachable. The route's `chat.agent` middleware has already resolved
        // the agent when the limiter runs.
        RateLimiter::for('chat-login', function (Request $request): array {
            $agent = HostedChatAgent::from($request);

            return [
                Limit::perMinute(15)->by("chat-login-ip|{$request->ip()}"),
                Limit::perMinute(5)->by(Str::transliterate(
                    'chat-login|'.($agent?->workspace_id ?? 'none').'|'.Str::lower($request->string('email')->toString())."|{$request->ip()}",
                )),
            ];
        });

        // Bulk gate for every /chat/{agentSlug}/* call, like `widgets` for the
        // widget: one per-IP ceiling, however many sessions an IP opens. A
        // little above the widget's, as the hosted chat is a whole app.
        RateLimiter::for('chat', fn (Request $request) => Limit::perMinute(120)->by("chat|ip|{$request->ip()}"));

        // Hosted-chat LLM-cost endpoints (stream, transcription, regenerate,
        // attachment upload). Keyed by the authenticated chatter, IP fallback.
        // Both are forgeable for a public agent (new sessions, many IPs), so a
        // ceiling per server-resolved agent is stacked on top, with the
        // widget's numbers (see `widget-stream`).
        RateLimiter::for('chat-stream', function (Request $request): array {
            $limits = [Limit::perMinute(30)->by("chat-stream|account|{$this->chatAccountKey($request)}")];
            $agent = HostedChatAgent::from($request);

            if ($agent instanceof Agent) {
                $limits[] = Limit::perMinute((int) config('chatvia.widget.stream_per_agent_per_minute', 120))
                    ->by("chat-stream|agent|{$agent->id}");
                $limits[] = Limit::perHour((int) config('chatvia.widget.stream_per_agent_per_hour', 1500))
                    ->by("chat-stream|agent-hour|{$agent->id}");
            }

            return $limits;
        });

        // Reading a hosted-chat reply aloud; a saved reply costs nothing, a
        // new one is a paid call to the speech service.
        RateLimiter::for('chat-speech', function (Request $request): array {
            $limits = [Limit::perMinute(20)->by("chat-speech|account|{$this->chatAccountKey($request)}")];
            $agent = HostedChatAgent::from($request);

            if ($agent instanceof Agent) {
                $limits[] = Limit::perMinute(120)->by("chat-speech|agent|{$agent->id}");
            }

            return $limits;
        });

        // Starting a hosted-chat voice call (see `widget-voice`).
        RateLimiter::for('chat-voice', function (Request $request): array {
            $limits = [Limit::perMinute(5)->by("chat-voice|account|{$this->chatAccountKey($request)}")];
            $agent = HostedChatAgent::from($request);

            if ($agent instanceof Agent) {
                $limits[] = Limit::perMinute(30)->by("chat-voice|agent|{$agent->id}");
            }

            return $limits;
        });
    }

    /**
     * The hosted-chat caller: the signed-in chatter or staff member, or the IP.
     * Chatters and staff have separate id sequences, so the key carries the
     * account kind (`chatter:5` / `user:5`).
     */
    private function chatAccountKey(Request $request): string
    {
        $account = $request->user();

        return $account instanceof Model ? "{$account->getMorphClass()}:{$account->getKey()}" : (string) $request->ip();
    }
}
