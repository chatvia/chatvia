<?php

namespace Domain\Analytics\Data\Agent\Usage;

use Illuminate\Support\Carbon;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One knowledge source on the dashboard's source-usage analytics page. Every
 * field — including the denormalised name/mime — comes from the durable
 * analytics rollup, so a DELETED source still shows with its real label; the
 * generic fallback only covers legacy rows recorded before the name column
 * existed.
 */
#[TypeScript]
class KnowledgeUsageRowData extends Data
{
    public function __construct(
        public string $name,
        public string $sub_kind,
        public ?string $mime_type,
        public int $count,
        public ?string $last_used_at,
    ) {}

    public static function fromUsage(SourceUsageData $source): self
    {
        return new self(
            name: $source->name ?? self::deletedLabel($source->kind),
            sub_kind: $source->kind,
            mime_type: $source->mimeType,
            count: $source->retrievals,
            // The rollup timestamps come back from DB::table without a tz
            // suffix; stamp them as UTC so the browser parses them correctly.
            last_used_at: $source->lastUsedOn === null ? null : Carbon::parse($source->lastUsedOn, 'UTC')->toIso8601ZuluString(),
        );
    }

    private static function deletedLabel(string $subKind): string
    {
        return match ($subKind) {
            'source_file' => __('Deleted file'),
            'source_website' => __('Deleted website'),
            'source_text' => __('Deleted text snippet'),
            'source_youtube_video' => __('Deleted YouTube video'),
            'agent_website_url' => __('Deleted web page'),
            default => __('Deleted source'),
        };
    }
}
