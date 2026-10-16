<?php

declare(strict_types=1);

namespace Domain\Notification\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * What every dashboard page knows about the signed-in person's notifications:
 * the unread count for the bell and their switches for desktop alerts and
 * sound.
 */
#[TypeScript]
class NotificationSummaryData extends Data
{
    public function __construct(
        public int $unread,
        public NotificationPreferencesData $preferences,
    ) {}
}
