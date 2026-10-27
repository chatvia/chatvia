<?php

declare(strict_types=1);

namespace Domain\Integration\Connector\Catalog\Connectors;

use Domain\Integration\Connector\Catalog\CapabilityConnector;
use Domain\Integration\Connector\Enums\ConnectorCategory;
use Domain\Llm\Contracts\ImageGenerator;
use Support\License\Entitlements;

/**
 * Pictures the agent makes for the visitor, shown under its reply. Only the
 * hosted chat can show and keep them, so the router withholds the tool on
 * every other surface, and a voice call never offers it. It works only while
 * an admin has picked an image service (Settings → AI models).
 */
final class ImageGenerationConnector extends CapabilityConnector
{
    public function __construct(
        Entitlements $entitlements,
        private readonly ImageGenerator $images,
    ) {
        parent::__construct($entitlements);
    }

    #[\Override]
    public string $slug { get => 'image-generation'; }

    #[\Override]
    public string $name { get => 'Image generation'; }

    #[\Override]
    public string $description { get => 'Let the agent make pictures in the hosted chat when a visitor asks: logos, illustrations, product ideas and more.'; }

    #[\Override]
    public ConnectorCategory $category { get => ConnectorCategory::Utilities; }

    #[\Override]
    public string $brandColor { get => 'EC4899'; }

    #[\Override]
    public int $priority { get => 50; }

    #[\Override]
    public ?string $activityLabel { get => 'Starting your picture…'; }

    /** @var list<string>|null */
    #[\Override]
    public ?array $surfaces { get => ['hosted_chat']; }

    #[\Override]
    public bool $voice { get => false; }

    #[\Override]
    public function isAvailable(): bool
    {
        return parent::isAvailable() && $this->images->isEnabled();
    }
}
