<?php

namespace Domain\Agents\Data\Resources;

use Carbon\CarbonInterface;
use Domain\Agents\Models\Agent;
use Illuminate\Support\Collection;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class AgentResourceData extends Data
{
    public function __construct(
        public int $id,
        public string $external_id,
        public string $type,
        public string $name,
        public ?string $sub_domain,
        public ?string $domain,
        public ?string $system_instructions,
        public ?string $prompt_id,
        public bool $is_public,
        public bool $collect_leads,
        public string $url,
        public string $permanent_url,
        public string $chat_url,
        public string $chat_embed_url,
        public CarbonInterface $created_at,
        public CarbonInterface $updated_at,
        public ?int $total_messages = null,
        public ?int $unique_users = null,
        public ?int $total_members = null,
    ) {}

    public static function fromModel(Agent $agent): self
    {
        return new self(
            id: $agent->id,
            external_id: $agent->external_id,
            type: $agent->type,
            name: $agent->name,
            sub_domain: $agent->sub_domain,
            domain: $agent->domain,
            system_instructions: $agent->system_instructions,
            prompt_id: $agent->agentPrompt?->external_id,
            is_public: $agent->is_public,
            collect_leads: $agent->collect_leads,
            url: $agent->url,
            permanent_url: $agent->permanent_url,
            chat_url: $agent->chat_url,
            chat_embed_url: $agent->chat_embed_url,
            created_at: $agent->created_at,
            updated_at: $agent->updated_at,
        );
    }

    /**
     * Create from model with stats (total_messages, unique_users, total_members).
     * Use with Agent::query()->withListStats() to load stats efficiently.
     */
    public static function fromModelWithStats(Agent $agent): self
    {
        return new self(
            id: $agent->id,
            external_id: $agent->external_id,
            type: $agent->type,
            name: $agent->name,
            sub_domain: $agent->sub_domain,
            domain: $agent->domain,
            system_instructions: $agent->system_instructions,
            prompt_id: $agent->agentPrompt?->external_id,
            is_public: $agent->is_public,
            collect_leads: $agent->collect_leads,
            url: $agent->url,
            permanent_url: $agent->permanent_url,
            chat_url: $agent->chat_url,
            chat_embed_url: $agent->chat_embed_url,
            created_at: $agent->created_at,
            updated_at: $agent->updated_at,
            total_messages: $agent->total_messages ?? 0,
            unique_users: $agent->unique_users ?? 0,
            total_members: $agent->total_members ?? 0,
        );
    }

    /**
     * Create a collection of AgentResourceData from a collection of Agent models.
     */
    public static function collection(Collection $agents): Collection
    {
        return $agents->map(fn (Agent $agent): AgentResourceData => self::fromModelWithStats($agent));
    }
}
