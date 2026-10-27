<?php

declare(strict_types=1);

namespace Domain\Llm\Notifications;

use App\Identity\Workspace\Models\User;
use Domain\Llm\Models\Llm;
use Domain\Notification\Data\NotificationContentData;
use Domain\Notification\Data\NotificationMessageData;
use Domain\Notification\Enums\NotificationKind;
use Domain\Notification\Notifications\InboxNotification;

/**
 * A provider stopped serving one of the catalog's models, so the app's own
 * tasks that used it now use another model. It names the model and the
 * provider and opens Settings → AI models in the admin panel.
 */
class LlmUnavailableInboxNotification extends InboxNotification
{
    public function __construct(
        private readonly Llm $llm,
    ) {}

    public function kind(): NotificationKind
    {
        return NotificationKind::AiModelUnavailable;
    }

    public function toInbox(User $notifiable): NotificationMessageData
    {
        return $this->message(
            new NotificationContentData(
                url: route('filament.admin.settings.pages.ai-models', absolute: false),
                subject: $this->llm->name,
                detail: $this->llm->provider->name,
            ),
            "llm-unavailable:{$this->llm->id}",
        );
    }
}
