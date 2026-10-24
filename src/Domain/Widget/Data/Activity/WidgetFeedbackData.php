<?php

declare(strict_types=1);

namespace Domain\Widget\Data\Activity;

use Domain\Conversation\Enums\MessageFeedback;
use Spatie\LaravelData\Data;

/**
 * A visitor's thumbs-up or thumbs-down on one assistant reply in the widget,
 * with their optional note on what was wrong.
 */
class WidgetFeedbackData extends Data
{
    public function __construct(
        public string $messageId,
        public MessageFeedback $feedback,
        public ?string $comment = null,
    ) {}
}
