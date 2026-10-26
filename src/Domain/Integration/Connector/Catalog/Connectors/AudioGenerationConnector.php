<?php

declare(strict_types=1);

namespace Domain\Integration\Connector\Catalog\Connectors;

use Domain\Integration\Connector\Catalog\CapabilityConnector;
use Domain\Integration\Connector\Enums\ConnectorCategory;
use Domain\Llm\Contracts\Speaker;

/**
 * Audio files of a text read aloud, made by the agent for the visitor and
 * shown under its reply. Only the hosted chat can show and keep them, so the
 * router withholds the tool on every other surface, and a voice call never
 * offers it. It works only while an admin has set up Read aloud (Settings →
 * AI models).
 */
final class AudioGenerationConnector extends CapabilityConnector
{
    #[\Override]
    public string $slug { get => 'audio-generation'; }

    #[\Override]
    public string $name { get => 'Audio generation'; }

    #[\Override]
    public string $description { get => 'Let the agent make audio files in the hosted chat when a visitor asks: any text read aloud, ready to play or download.'; }

    #[\Override]
    public ConnectorCategory $category { get => ConnectorCategory::Utilities; }

    #[\Override]
    public string $brandColor { get => '14B8A6'; }

    #[\Override]
    public int $priority { get => 55; }

    #[\Override]
    public ?string $activityLabel { get => 'Starting your audio file…'; }

    /** @var list<string>|null */
    #[\Override]
    public ?array $surfaces { get => ['hosted_chat']; }

    #[\Override]
    public bool $voice { get => false; }

    #[\Override]
    public function isAvailable(): bool
    {
        return app(Speaker::class)->isEnabled();
    }
}
