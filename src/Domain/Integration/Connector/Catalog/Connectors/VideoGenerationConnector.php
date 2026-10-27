<?php

declare(strict_types=1);

namespace Domain\Integration\Connector\Catalog\Connectors;

use Domain\Integration\Connector\Catalog\CapabilityConnector;
use Domain\Integration\Connector\Enums\ConnectorCategory;
use Domain\Llm\Contracts\VideoGenerator;

/**
 * Short videos the agent makes for the visitor, shown under its reply once
 * made (a few minutes). Only the hosted chat can show and keep them, so the
 * router withholds the tool on every other surface, and a voice call never
 * offers it. It works only while an admin has picked a video service
 * (Settings → AI models → Video generation).
 */
final class VideoGenerationConnector extends CapabilityConnector
{
    #[\Override]
    public string $slug { get => 'video-generation'; }

    #[\Override]
    public string $name { get => 'Video generation'; }

    #[\Override]
    public string $description { get => 'Let the agent make short videos in the hosted chat when a visitor asks: product clips, scenes, animated ideas and more.'; }

    #[\Override]
    public ConnectorCategory $category { get => ConnectorCategory::Utilities; }

    #[\Override]
    public string $brandColor { get => '8B5CF6'; }

    #[\Override]
    public int $priority { get => 56; }

    #[\Override]
    public ?string $activityLabel { get => 'Starting your video…'; }

    /** @var list<string>|null */
    #[\Override]
    public ?array $surfaces { get => ['hosted_chat']; }

    #[\Override]
    public bool $voice { get => false; }

    #[\Override]
    public function isAvailable(): bool
    {
        return parent::isAvailable() && app(VideoGenerator::class)->isEnabled();
    }
}
