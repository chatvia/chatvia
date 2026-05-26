<?php

namespace App\Http\Resources\Api\V1;

use Domain\Agents\Models\Agent;

/**
 * Shapes an Agent into the v1 API JSON representation.
 *
 * Single source of truth for the public agent shape across every endpoint
 * that returns one. Keeping it here (rather than spreading inline arrays
 * across controllers) means a field rename or new field needs one edit.
 */
class AgentTransformer
{
    /**
     * @return array{
     *     id: string,
     *     name: string,
     *     type: string,
     *     is_public: bool,
     *     collect_leads: bool,
     *     analytics_enabled: bool,
     *     metadata: array<string, mixed>,
     *     created_via: string,
     *     system_instructions: string|null,
     *     sub_domain: string|null,
     *     domain: string|null,
     *     url: string,
     *     permanent_url: string,
     *     chat_url: string,
     *     embed_url: string,
     *     created_at: string|null,
     *     updated_at: string|null,
     * }
     */
    public static function toArray(Agent $agent): array
    {
        return [
            'id' => $agent->external_id,
            'name' => $agent->name,
            'type' => $agent->type,
            'is_public' => (bool) $agent->is_public,
            'collect_leads' => (bool) $agent->collect_leads,
            'analytics_enabled' => (bool) $agent->analytics_enabled,
            'metadata' => $agent->metadata ?? [],
            'created_via' => $agent->created_via,
            'system_instructions' => $agent->system_instructions,
            'sub_domain' => $agent->sub_domain,
            'domain' => $agent->domain,
            'url' => $agent->url,
            'permanent_url' => $agent->permanent_url,
            'chat_url' => $agent->chat_url,
            'embed_url' => $agent->chat_embed_url,
            'created_at' => $agent->created_at?->toIso8601String(),
            'updated_at' => $agent->updated_at?->toIso8601String(),
        ];
    }
}
