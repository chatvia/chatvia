<?php

declare(strict_types=1);

namespace App\Admin\Settings;

use Spatie\LaravelSettings\Attributes\ShouldBeEncrypted;

/**
 * How knowledge sources come in: the file types workspaces may upload, the
 * optional AI enhancement of their chunks, and the service that imports
 * websites (Firecrawl). How text is split into chunks is in
 * {@see ChunkingSettings}, how agents search it in {@see SearchSettings}.
 */
class IngestionSettings extends AdminSettings
{
    /** @var list<string>|null KnowledgeFileType values. */
    #[ConfigKey('chatvia.knowledge.file_types')]
    public ?array $file_types = null;

    #[ConfigKey('chatvia.ai.chunker.enhance')]
    public ?bool $enhance_chunks = null;

    #[ConfigKey('chatvia.ai.chunker.enhance_text')]
    public ?bool $enhance_text_chunks = null;

    #[ConfigKey('services.firecrawl.key'), ShouldBeEncrypted]
    public ?string $firecrawl_api_key = null;

    #[ConfigKey('services.firecrawl.endpoint')]
    public ?string $firecrawl_endpoint = null;

    #[ConfigKey('services.firecrawl.rate_per_minute')]
    public ?int $firecrawl_rate_per_minute = null;

    public static function group(): string
    {
        return 'ingestion';
    }
}
