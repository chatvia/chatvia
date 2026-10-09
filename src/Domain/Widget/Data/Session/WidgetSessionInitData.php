<?php

declare(strict_types=1);

namespace Domain\Widget\Data\Session;

use Spatie\LaravelData\Data;

/**
 * Input for InitializeWidgetSessionAction: the session and conversation ids
 * the widget remembered (if any), plus the request details stored on a newly
 * started session (IP, user agent, language, referrer and page URL).
 */
class WidgetSessionInitData extends Data
{
    public function __construct(
        public ?string $existingSessionId,
        public ?string $existingConversationId,
        public ?string $ipAddress,
        public ?string $userAgent,
        public ?string $acceptLanguage,
        public ?string $referrerUrl,
        public ?string $pageUrl,
    ) {}
}
