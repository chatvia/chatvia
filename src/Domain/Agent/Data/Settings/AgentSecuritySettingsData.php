<?php

declare(strict_types=1);

namespace Domain\Agent\Data\Settings;

use Spatie\LaravelData\Data;

/**
 * The per-agent chat-security configuration (the `security` model-settings
 * group). Built from SaveAgentSecuritySettingsRequest; `toSettings()` is the
 * key→value map persisted via Agent::setSetting.
 */
class AgentSecuritySettingsData extends Data
{
    public function __construct(
        public int $chat_rate_limit_per_minute,
        public bool $notify_owner_on_rate_limit,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toSettings(): array
    {
        return [
            'chat_rate_limit_per_minute' => $this->chat_rate_limit_per_minute,
            'notify_owner_on_rate_limit' => $this->notify_owner_on_rate_limit,
        ];
    }
}
