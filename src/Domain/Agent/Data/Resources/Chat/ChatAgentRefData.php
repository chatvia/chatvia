<?php

namespace Domain\Agent\Data\Resources\Chat;

use Domain\Agent\Models\Agent;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * An agent in the hosted chat's agent switcher — just what the dialog needs,
 * addressed by external id.
 */
#[TypeScript]
class ChatAgentRefData extends Data
{
    public function __construct(
        public string $id,
        public string $name,
        public ?string $slug,
    ) {}

    public static function fromModel(Agent $agent): self
    {
        return new self(
            id: $agent->external_id,
            name: $agent->name,
            slug: $agent->slug,
        );
    }
}
