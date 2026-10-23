<?php

declare(strict_types=1);

namespace App\Http\Resources\Inbox;

use Domain\Agent\Models\Agent;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The agent an Inbox chat belongs to. `id` is the public uuid the per-agent
 * chat-log and handoff routes take.
 */
#[TypeScript]
class InboxAgentData extends Data
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
