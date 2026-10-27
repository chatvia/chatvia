<?php

declare(strict_types=1);

namespace Domain\Agent\Actions\Settings;

use Domain\Agent\Models\Agent;
use Domain\Agent\Services\OfferedMediaModels;
use Domain\Conversation\Enums\GeneratedMediaKind;

/**
 * Saves whether chatters may pick the image or video model in the agent's
 * hosted chat (Capabilities). It counts only while the admin lets agents
 * offer a choice ({@see OfferedMediaModels}).
 */
class UpdateAgentMediaModelChoiceAction
{
    public function execute(Agent $agent, GeneratedMediaKind $kind, bool $enabled): void
    {
        $agent->setSetting(key: $kind->value, value: $enabled, group: OfferedMediaModels::SETTINGS_GROUP);
    }
}
