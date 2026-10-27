<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Agent;

use App\Http\Resources\Api\V1\Team\TeamRefData;
use Carbon\CarbonInterface;
use Domain\Agent\Models\Agent;
use Spatie\LaravelData\Data;
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
         * Whether the agent's chat is reachable publicly, without a sign-in link.
         */
        public bool $is_public,
        /**
         * Arbitrary key/value data you attach to the agent — for example your
         * own external ids.
         *
         * @var array<string, mixed>
         */
        public array $metadata,
        /**
         * Origin of the agent — `api` when created through the API,
         * `dashboard` otherwise.
         */
        public CreatedVia $source,
        /**
         * The custom system prompt that steers how the agent answers. Null when
         * the agent uses a reusable library prompt (`prompt_id`) instead.
         */
        public ?string $system_instructions,
        /**
         * ID of the reusable library prompt attached to this agent, if any. When
         * set it provides the agent's instructions in place of
         * `system_instructions`. Pass `prompt_id` on create/update to attach one;
         * the two are mutually exclusive.
         */
        public ?string $prompt_id,
        /**
         * The agent's chat address slug: its chat lives at `/chat/{slug}`.
         */
        public ?string $slug,
        /**
         * Canonical URL of the agent's chat.
         */
        public string $url,
        /**
         * A permanent chat URL that keeps working even if the slug changes.
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
            is_public: (bool) $agent->is_public,
            metadata: $agent->metadata ?? [],
            source: $agent->source,
            system_instructions: $agent->system_instructions,
            prompt_id: $agent->agentPrompt?->external_id,
            slug: $agent->slug,
            url: $agent->url,
            permanent_url: $agent->permanent_url,
            created_at: $agent->created_at,
            updated_at: $agent->updated_at,
        );
    }
}
