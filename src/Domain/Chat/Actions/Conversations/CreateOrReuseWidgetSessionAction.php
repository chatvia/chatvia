<?php

namespace Domain\Chat\Actions\Conversations;

use App\Widget\WidgetVerification;
use Domain\Agent\Models\Agent;
use Domain\Analytics\Services\UserAgentParser;

class CreateOrReuseWidgetSessionAction
{
    public function __construct(
        private readonly WidgetVerification $verification,
    ) {}

    /**
     * @param  array<string, mixed>  $context  Carries ip, user_agent.
     */
    public function execute(Agent $agent, ?string $existingSessionId, array $context): string
    {
        if ($existingSessionId) {
            $session = $this->verification->verifySessionExternalId($existingSessionId);
            if ($session) {
                return $existingSessionId;
            }
        }

        $userAgentData = UserAgentParser::parse($context['user_agent'] ?? null);

        $session = $agent
            ->chatSessions()
            ->create([
                'ip_address' => $context['ip'] ?? null,
                'user_agent' => $context['user_agent'] ?? null,
                'device_type' => $userAgentData['device_type'] ?? null,
                'browser' => $userAgentData['browser'] ?? null,
                'os' => $userAgentData['os'] ?? null,
            ]);

        return $session->external_id;
    }
}
