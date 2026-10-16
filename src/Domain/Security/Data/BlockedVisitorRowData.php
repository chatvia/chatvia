<?php

namespace Domain\Security\Data;

use Domain\Security\Models\BlockedVisitor;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One visitor block on the Blocked lists (Security page and the Guardrails
 * "Blocked" tab). Only a short prefix of the fingerprint is shown; the
 * relations are eager-loaded by the caller.
 */
#[TypeScript]
class BlockedVisitorRowData extends Data
{
    public function __construct(
        public string $external_id,
        public string $visitor,
        public ?string $browser,
        public ?string $os,
        public ?string $country,
        public ?string $agent_name,
        public ?string $reason,
        public ?string $blocked_by,
        public ?string $created_at,
        public ?string $expires_at,
    ) {}

    public static function fromModel(BlockedVisitor $block): self
    {
        return new self(
            external_id: $block->external_id,
            visitor: substr($block->visitor_key, 0, 6),
            browser: $block->browser,
            os: $block->os,
            country: $block->country?->localizedName(),
            agent_name: $block->agent?->name,
            reason: $block->reason,
            blocked_by: $block->blockedBy?->name,
            created_at: $block->created_at?->toIso8601String(),
            expires_at: $block->expires_at?->toIso8601String(),
        );
    }
}
