<?php

namespace Domain\Widget\Data\Session;

use Spatie\LaravelData\Data;

/**
 * Input for InitializeWidgetSessionAction — carries the optional client-supplied
 * session/thread identifiers and the request metadata we capture when minting a
 * brand-new session (IP, user agent, locale, etc.).
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
