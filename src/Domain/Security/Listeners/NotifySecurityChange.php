<?php

declare(strict_types=1);

namespace Domain\Security\Listeners;

use App\Identity\Workspace\Models\User;
use Domain\Notification\Services\NotificationRecipients;
use Domain\Security\Enums\SecurityAuditAction;
use Domain\Security\Events\SecurityEventOccurred;
use Domain\Security\Notifications\SecurityChangeNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;

/**
 * Tells the people who look after the workspace's security when its
 * protection changes ({@see NOTIFIED}). The person who made the change is not
 * told about their own change. Queued, like the audit log write.
 */
class NotifySecurityChange implements ShouldQueue
{
    /** Run only after the dispatching transaction has committed. */
    public bool $afterCommit = true;

    /** The audited changes worth a notification. */
    private const array NOTIFIED = [
        SecurityAuditAction::ApiKeyCreated,
        SecurityAuditAction::PolicyChanged,
        SecurityAuditAction::PolicyIpAllowlistChanged,
        SecurityAuditAction::PolicyRetentionChanged,
        SecurityAuditAction::PolicyWebhookDisabled,
    ];

    public function __construct(
        private readonly NotificationRecipients $recipients,
    ) {}

    public function handle(SecurityEventOccurred $event): void
    {
        $action = SecurityAuditAction::tryFrom($event->action);

        if (! $action instanceof SecurityAuditAction || ! in_array($action, self::NOTIFIED, true)) {
            return;
        }

        $recipients = $this->recipients
            ->forWorkspace($event->workspaceId, 'account.security')
            ->reject(fn (User $user): bool => $user->id === $event->userId);

        Notification::send($recipients, new SecurityChangeNotification($action, $event->userName, $event->targetLabel));
    }
}
