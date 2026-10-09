<?php

namespace App\Providers;

use App\Http\Widget\WidgetAgent;
use Domain\Widget\WidgetHeaders;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

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
        // a public agent's message quota (and trigger auto-recharge) by
        // multiplying sessions. Applies to /widget/stream and attachments.
        RateLimiter::for('widget-stream', function (Request $request) {
            $sessionKey = $request->header(WidgetHeaders::SESSION_ID) ?: $request->ip();

            $limits = [
                Limit::perMinute(20)->by('widget-stream|sess|'.$sessionKey),
            ];

            $agent = WidgetAgent::from($request);

            if ($agent) {
                $limits[] = Limit::perMinute((int) config('chatvia.widget.stream_per_agent_per_minute', 120))
                    ->by('widget-stream|agent|'.$agent->id);
                $limits[] = Limit::perHour((int) config('chatvia.widget.stream_per_agent_per_hour', 1500))
                    ->by('widget-stream|agent-hour|'.$agent->id);
            }

            return $limits;
        });

        // The auth exchange runs once per page load for protected widgets. A
        // tight per-IP cap removes JWT-verification brute force as a DoS
        // surface (defence in depth — verify() is already constant-time).
        RateLimiter::for(
            'widget-exchange',
            fn (Request $request) => Limit::perMinute(10)->by('widget-exchange|'.$request->ip()),
        );
    }

    private function configureApiLimiters(): void
    {
        RateLimiter::for('api', fn (Request $request) => $request->user()
            ? Limit::perMinute(300)->by($request->user()->getAuthIdentifier().'|'.$request->user()::class)
            : Limit::perMinute(60)->by($request->ip()));

        // Built-in MCP endpoints are only reachable by our own signed tool
        // loop; key the backstop by the calling agent so one runaway agent
        // loop can't exhaust the budget for every other agent.
        RateLimiter::for('builtin-mcp', fn (Request $request) => Limit::perMinute(120)
            ->by('builtin-mcp|'.((string) $request->header('X-Chatvia-Agent-Id') ?: $request->ip())));
    }

    private function configureChatLimiters(): void
    {
        // Hosted-chat LLM-cost endpoints (stream, transcription, regenerate,
        // attachment upload). Keyed by the authenticated chatter, IP fallback.
        RateLimiter::for('chat-stream', function (Request $request) {
            $key = $request->user()?->getAuthIdentifier() ?: $request->ip();

            return Limit::perMinute(30)->by('chat-stream|'.$key);
        });
    }
}
