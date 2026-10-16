<?php

declare(strict_types=1);

namespace Domain\Security\Notifications;

use App\Identity\Workspace\Models\User;
use Domain\Notification\Data\NotificationContentData;
use Domain\Notification\Data\NotificationMessageData;
use Domain\Notification\Enums\NotificationKind;
use Domain\Notification\Notifications\InboxNotification;
use Domain\Security\Enums\SecurityAuditAction;

/**
 * Someone changed how the workspace is protected: an API token was created,
 * the security policy, IP allowlist or retention changed, or the audit
 * webhook was switched off after failing. It names the change and who made
 * it, and opens the security audit log.
 */
class SecurityChangeNotification extends InboxNotification
{
    public function __construct(
        private readonly SecurityAuditAction $action,
        private readonly ?string $actor,
        private readonly ?string $target,
    ) {}

    public function kind(): NotificationKind
    {
        return NotificationKind::SecuritySettingsChanged;
    }

    public function toInbox(User $notifiable): NotificationMessageData
    {
        return $this->message(new NotificationContentData(
            url: route('security.audit', absolute: false),
            subject: $this->target,
            detail: $this->action->value,
            actor: $this->actor,
        ));
    }
}
