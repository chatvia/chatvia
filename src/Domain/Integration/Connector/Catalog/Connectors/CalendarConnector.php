<?php

declare(strict_types=1);

namespace Domain\Integration\Connector\Catalog\Connectors;

use Domain\Integration\Connector\Catalog\CapabilityConnector;
use Domain\Integration\Connector\Enums\ConnectorCategory;

/**
 * A booking calendar in the chat: the visitor picks a free time and books it
 * in the workspace's Calendly, and the booking is stored as a lead. It works
 * through the Calendly connection, so it needs Calendly connected first. A
 * time is picked on screen, so a voice call never offers it; a messaging
 * channel gets the booking link instead.
 */
final class CalendarConnector extends CapabilityConnector
{
    #[\Override]
    public string $slug { get => 'calendar'; }

    #[\Override]
    public string $name { get => 'Calendar'; }

    #[\Override]
    public string $description { get => 'Let visitors book a meeting in the chat: they pick a free time from your Calendly and book it without leaving the conversation. Each booking is saved as a lead.'; }

    #[\Override]
    public ConnectorCategory $category { get => ConnectorCategory::Sales; }

    #[\Override]
    public string $brandColor { get => '0EA5E9'; }

    #[\Override]
    public int $priority { get => 45; }

    #[\Override]
    public ?string $activityLabel { get => 'Checking available times…'; }

    #[\Override]
    public bool $voice { get => false; }

    #[\Override]
    public ?string $requiresConnector { get => 'calendly'; }
}
