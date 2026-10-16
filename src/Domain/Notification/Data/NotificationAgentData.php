<?php

declare(strict_types=1);

namespace Domain\Notification\Data;

use Domain\Agent\Models\Agent;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The agent a notification is about, as it was named when the notification
 * was sent: its public id and name.
 */
#[TypeScript]
class NotificationAgentData extends Data
{
    public function __construct(
        public string $id,
        public string $name,
    ) {}

    public static function fromAgent(Agent $agent): self
    {
        return new self(id: $agent->external_id, name: $agent->name);
    }
}
