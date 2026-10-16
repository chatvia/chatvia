<?php

declare(strict_types=1);

namespace Domain\Form\Notifications;

use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Form\Models\FormDefinition;
use Domain\Notification\Data\NotificationAgentData;
use Domain\Notification\Data\NotificationContentData;
use Domain\Notification\Data\NotificationMessageData;
use Domain\Notification\Enums\NotificationKind;
use Domain\Notification\Notifications\InboxNotification;

/**
 * A visitor submitted one of the agent's forms in chat: a new lead. It names
 * the form only, never the answers, and opens the form's submissions. New
 * leads gather per form while unread ("3 new leads").
 */
class LeadCapturedNotification extends InboxNotification
{
    public function __construct(
        private readonly FormDefinition $form,
        private readonly Agent $agent,
    ) {}

    public function kind(): NotificationKind
    {
        return NotificationKind::LeadCaptured;
    }

    public function toInbox(User $notifiable): NotificationMessageData
    {
        return $this->message(
            new NotificationContentData(
                url: route('agents.forms.submissions.index', ['agent' => $this->agent, 'form' => $this->form], absolute: false),
                agent: NotificationAgentData::fromAgent($this->agent),
                subject: $this->form->name,
            ),
            'leads:'.$this->form->external_id,
        );
    }
}
