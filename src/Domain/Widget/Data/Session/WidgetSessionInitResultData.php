<?php

declare(strict_types=1);

namespace Domain\Widget\Data\Session;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Result of bootstrapping a widget session — the (possibly-new) session and
 * conversation identifiers the widget needs to send on subsequent requests,
 * plus flags so the page can react (e.g. clear local storage on a new session).
 */
#[TypeScript]
class WidgetSessionInitResultData extends Data
{
    public function __construct(
        public string $session_id,
        public string $conversation_id,
        public bool $is_new_session,
        public bool $is_new_conversation,
    ) {}
}
