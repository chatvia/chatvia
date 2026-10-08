<?php

namespace Domain\Conversation\Services;

use Domain\Agent\Models\Agent;
use Domain\Channel\Services\ChannelReplyRunner;
use Domain\Conversation\Events\AgentRateLimitExceeded;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Per-agent message cap per fixed 60-second window (the `security` settings
 * group). The window starts with the first message and resets 60 seconds
 * later. Every message counts toward the agent's bucket; once the count passes
 * the cap the turn is refused and, if the owner opted in, an alert event is
 * dispatched. A limit of 0 disables the cap.
 *
 * Shared by every chat surface so the cap applies uniformly regardless of where
 * a message arrives: the web SSE stream ({@see ChatStreamService}) and the
 * messaging channels ({@see ChannelReplyRunner} —
 * Telegram, Slack, Messenger, WhatsApp, Instagram, Teams).
 *
 * The cap itself rides on the cache store (via {@see RateLimiter}); the agent's
 * configured limit is also cached so this hot path never SELECTs model_settings
 * per turn. {@see forget()} busts that cache when the Security page is saved.
 */
class AgentChatRateLimiter
{
    /** How long the per-agent security config is cached (seconds). */
    private const CONFIG_TTL = 300;

    /** Length of one counting window (seconds). */
    private const WINDOW_SECONDS = 60;

    /**
     * Record this turn against the agent's bucket and report whether it is now
     * over the cap. Dispatches {@see AgentRateLimitExceeded} for every refused
     * turn when the owner enabled alerts (the listener throttles the email).
     */
    public function exceeded(Agent $agent): bool
    {
        ['limit' => $limit, 'notify' => $notify] = $this->config($agent);

        if ($limit <= 0) {
            return false;
        }

        // One atomic increment decides: each concurrent request gets its own
        // count, so a burst can't slip past the cap between a separate
        // "check" and "hit". Refused turns count too, but the window is fixed,
        // so that never extends it.
        $hits = RateLimiter::increment('agent-chat-rate:'.$agent->id, self::WINDOW_SECONDS);

        if ($hits <= $limit) {
            return false;
        }

        if ($notify) {
            AgentRateLimitExceeded::dispatch($agent, $limit);
        }

        return true;
    }

    /**
     * Drop the cached config for an agent — call after the security settings are
     * saved so a changed cap takes effect immediately instead of on TTL expiry.
     */
    public function forget(Agent $agent): void
    {
        Cache::forget($this->configKey($agent));
    }

    /**
     * The agent's cap + alert flag from the `security` settings group, cached so
     * the chat hot path doesn't query model_settings on every turn.
     *
     * @return array{limit: int, notify: bool}
     */
    private function config(Agent $agent): array
    {
        return Cache::remember($this->configKey($agent), self::CONFIG_TTL, function () use ($agent): array {
            $security = $agent->settings('security');

            return [
                'limit' => (int) $security->get('chat_rate_limit_per_minute', 20),
                'notify' => (bool) $security->get('notify_owner_on_rate_limit', false),
            ];
        });
    }

    private function configKey(Agent $agent): string
    {
        return 'agent-chat-rate-config:'.$agent->id;
    }
}
