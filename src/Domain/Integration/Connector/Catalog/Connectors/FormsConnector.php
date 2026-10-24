<?php

declare(strict_types=1);

namespace Domain\Integration\Connector\Catalog\Connectors;

use Domain\Integration\Connector\Catalog\CapabilityConnector;
use Domain\Integration\Connector\Enums\ConnectorCategory;

/**
 * Fillable forms in the chat, from the forms the workspace defines on the Forms page; a
 * submission is stored as a `FormSubmission`. A form is filled on screen, so a voice call never
 * offers it.
 */
final class FormsConnector extends CapabilityConnector
{
    #[\Override]
    public string $slug { get => 'forms'; }

    #[\Override]
    public string $name { get => 'Forms'; }

    #[\Override]
    public string $description { get => 'Capture leads with a real, fillable form in the chat — plus booking and qualification forms. Validated and stored automatically; falls back to conversational capture on channels that can\'t show a form.'; }

    #[\Override]
    public ConnectorCategory $category { get => ConnectorCategory::Sales; }

    #[\Override]
    public string $brandColor { get => '6366F1'; }

    #[\Override]
    public int $priority { get => 40; }

    #[\Override]
    public ?string $activityLabel { get => 'Preparing your form…'; }

    #[\Override]
    public bool $voice { get => false; }
}
