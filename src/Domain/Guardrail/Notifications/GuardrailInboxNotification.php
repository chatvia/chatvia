<?php

declare(strict_types=1);

namespace Domain\Guardrail\Notifications;

use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Guardrail\Enums\GuardAlert;
use Domain\Notification\Data\NotificationAgentData;
use Domain\Notification\Data\NotificationContentData;
use Domain\Notification\Data\NotificationMessageData;
use Domain\Notification\Enums\NotificationKind;
use Domain\Notification\Notifications\InboxNotification;

/**
 * The guardrails blocked an attack on the agent (a prompt injection, or an
 * answer that would have leaked the system prompt), or a check could not run.
 * It names the guard (or, for a skipped check, why it could not run), never
 * the message, and opens the agent's Guardrails page. Repeats gather per agent and kind of alert while unread, so a burst
 * of attacks is one line with a count.
 */
class GuardrailInboxNotification extends InboxNotification
{
    public function __construct(
        private readonly Agent $agent,
        private readonly GuardAlert $alert,
        private readonly string $guardType,
        private readonly ?string $failure = null,
    ) {}

    public function kind(): NotificationKind
    {
        return match ($this->alert) {
            GuardAlert::Attack => NotificationKind::AttackBlocked,
            GuardAlert::Skipped => NotificationKind::GuardrailSkipped,
        };
    }

    public function toInbox(User $notifiable): NotificationMessageData
    {
        return $this->message(
            new NotificationContentData(
                url: route('agents.guardrails', ['agent' => $this->agent], absolute: false),
                agent: NotificationAgentData::fromAgent($this->agent),
                detail: $this->alert === GuardAlert::Skipped ? $this->failure : $this->guardType,
            ),
            "guardrail:{$this->agent->external_id}:{$this->alert->value}",
        );
    }
}
