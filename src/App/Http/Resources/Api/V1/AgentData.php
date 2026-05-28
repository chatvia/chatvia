<?php

namespace App\Http\Resources\Api\V1;

use Carbon\CarbonInterface;
use Domain\Agents\Models\Agent;
use Spatie\LaravelData\Data;
use Support\Enums\AgentType;
use Support\Enums\CreatedVia;

/**
 * The public shape of an agent.
 */
class AgentData extends Data
{
    public function __construct(
        /**
         * The agent's ID.
         */
        public string $id,
        /**
         * The team this agent belongs to.
         */
        public TeamRefData $team,
        /**
         * Display name of the agent.
         */
        public string $name,
        /**
         * Agent kind. Only `knowledge_base` agents can be created via the API.
         */
        public AgentType $type,
        /**
         * Whether the agent's chat is reachable publicly, without a sign-in link.
         */
        public bool $is_public,
        /**
         * Whether the agent asks visitors for their contact details in chat.
         */
        public bool $collect_leads,
        /**
         * Whether conversation analytics are recorded for this agent.
         */
        public bool $analytics_enabled,
        /**
         * Arbitrary key/value data you attach to the agent — for example your
         * own external ids.
         *
         * @var array<string, mixed>
         */
        public array $metadata,
        /**
         * Origin of the agent — `external` when created through the API,
         * `internal` otherwise.
         */
        public CreatedVia $created_via,
        /**
         * The system prompt that steers how the agent answers.
         */
        public ?string $system_instructions,
        /**
         * Subdomain the agent's chat is served from.
         */
        public ?string $sub_domain,
        /**
         * Custom domain mapped to the agent's chat, if one is set.
         */
        public ?string $domain,
        /**
         * Canonical URL of the agent's chat.
         */
        public string $url,
        /**
         * A permanent chat URL that keeps working even if the subdomain changes.
         */
        public string $permanent_url,
        /**
         * When the agent was created.
         */
        public ?CarbonInterface $created_at,
        /**
         * When the agent was last updated.
         */
        public ?CarbonInterface $updated_at,
    ) {}

    public static function fromModel(Agent $agent): self
    {
        return new self(
            id: $agent->external_id,
            team: TeamRefData::fromModel($agent->team),
            name: $agent->name,
            type: AgentType::from($agent->type),
            is_public: (bool) $agent->is_public,
            collect_leads: (bool) $agent->collect_leads,
            analytics_enabled: (bool) $agent->analytics_enabled,
            metadata: $agent->metadata ?? [],
            created_via: CreatedVia::from($agent->created_via),
            system_instructions: $agent->system_instructions,
            sub_domain: $agent->sub_domain,
            domain: $agent->domain,
            url: $agent->url,
            permanent_url: $agent->permanent_url,
            created_at: $agent->created_at,
            updated_at: $agent->updated_at,
        );
    }
}
