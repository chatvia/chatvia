<?php

namespace Domain\Agent\Data\Resources\Account;

use Carbon\CarbonInterface;
use Domain\Agent\Models\Agent;
use Illuminate\Support\Collection;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * An agent as the dashboard shows it: identity, addresses and persona, plus
 * the list-only counts when built for the agents list.
 */
#[TypeScript]
class AgentResourceData extends Data
{
    public function __construct(
        public int $id,
        public string $external_id,
        public string $name,
        public ?string $slug,
        public ?string $system_instructions,
        public ?string $prompt_id,
        public bool $is_public,
        public string $url,
        public string $permanent_url,
        public string $chat_url,
        public string $chat_embed_url,
        public CarbonInterface $created_at,
        public CarbonInterface $updated_at,
        public ?int $total_messages = null,
        public ?int $total_members = null,
        public bool $forms_enabled = false,
    ) {}

    public static function fromModel(Agent $agent): self
    {
        return new self(
            id: $agent->id,
            external_id: $agent->external_id,
            name: $agent->name,
            slug: $agent->slug,
            system_instructions: $agent->system_instructions,
            prompt_id: $agent->agentPrompt?->external_id,
            is_public: $agent->is_public,
            url: $agent->url,
            permanent_url: $agent->permanent_url,
            chat_url: $agent->chat_url,
            chat_embed_url: $agent->chat_embed_url,
            created_at: $agent->created_at,
            updated_at: $agent->updated_at,
            forms_enabled: $agent->isFormsEnabled(),
        );
    }

    /**
     * For the agents list: adds the all-time message total (from the analytics
     * rollups) and the workspace member count (teammates plus chatters, the
     * same for every agent of the workspace, so counted once by the caller).
     */
    public static function fromModelWithStats(Agent $agent, int $totalMessages, int $totalMembers): self
    {
        return new self(
            id: $agent->id,
            external_id: $agent->external_id,
            name: $agent->name,
            slug: $agent->slug,
            system_instructions: $agent->system_instructions,
            prompt_id: $agent->agentPrompt?->external_id,
            is_public: $agent->is_public,
            url: $agent->url,
            permanent_url: $agent->permanent_url,
            chat_url: $agent->chat_url,
            chat_embed_url: $agent->chat_embed_url,
            created_at: $agent->created_at,
            updated_at: $agent->updated_at,
            total_messages: $totalMessages,
            total_members: $totalMembers,
        );
    }

    /**
     * @param  Collection<int, Agent>  $agents
     * @param  array<int, int>  $messageTotals  agent id → all-time messages
     * @param  int  $totalMembers  the workspace's teammates plus its chatters
     * @return Collection<int, self>
     */
    public static function listWithStats(Collection $agents, array $messageTotals, int $totalMembers): Collection
    {
        return $agents->map(fn (Agent $agent): self => self::fromModelWithStats($agent, $messageTotals[$agent->id] ?? 0, $totalMembers));
    }
}
