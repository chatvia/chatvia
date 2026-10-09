<?php

declare(strict_types=1);

namespace App\Http\Controllers\Agent\Security;

use App\Http\Requests\Agent\Security\SaveAgentSecuritySettingsRequest;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Services\AgentChatRateLimiter;
use Illuminate\Http\RedirectResponse;

/**
 * Persists the per-agent chat rate-limit settings (the `security` group) from
 * the agent's Security page. Mirrors the guardrails save flow — a single thin
 * write that loops the validated DTO into Agent::setSetting.
 */
class UpdateAgentSecuritySettingsController
{
    public function __construct(private readonly AgentChatRateLimiter $rateLimiter) {}

    public function __invoke(SaveAgentSecuritySettingsRequest $request, Agent $agent): RedirectResponse
    {
        foreach ($request->toData()->toSettings() as $key => $value) {
            $agent->setSetting(key: $key, value: $value, group: 'security');
        }

        // The limiter caches this config on the hot chat path — drop it so the
        // new cap applies immediately instead of waiting out the cache TTL.
        $this->rateLimiter->forget($agent);

        return back();
    }
}
