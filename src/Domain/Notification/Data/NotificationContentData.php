<?php

declare(strict_types=1);

namespace Domain\Notification\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * What the dashboard needs to write one notification in the reader's own
 * language: the agent, a name to show (a source, a form, a run), a code the
 * sentence depends on (a guard, a security change, a share of the limit), who
 * did it, and the dashboard path it opens. Never personal data such as a
 * lead's answers or a visitor's message.
 */
#[TypeScript]
class NotificationContentData extends Data
{
    public function __construct(
        public string $url,
        public ?NotificationAgentData $agent = null,
        public ?string $subject = null,
        public ?string $detail = null,
        public ?string $actor = null,
    ) {}
}
