<?php

declare(strict_types=1);

namespace Domain\Evaluation\Notifications;

use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Evaluation\Models\EvalRun;
use Domain\Notification\Data\NotificationAgentData;
use Domain\Notification\Data\NotificationContentData;
use Domain\Notification\Data\NotificationMessageData;
use Domain\Notification\Enums\NotificationKind;
use Domain\Notification\Notifications\InboxNotification;

/**
 * An eval run failed cases that passed in the run before it. It carries how
 * many ("3") and the first case's name, and opens the run.
 */
class EvalRegressedNotification extends InboxNotification
{
    public function __construct(
        private readonly EvalRun $run,
        private readonly Agent $agent,
        private readonly int $count,
        private readonly string $firstCase,
    ) {}

    public function kind(): NotificationKind
    {
        return NotificationKind::EvalRegressed;
    }

    public function toInbox(User $notifiable): NotificationMessageData
    {
        return $this->message(new NotificationContentData(
            url: route('agents.evals.runs.show', ['agent' => $this->agent, 'run' => $this->run], absolute: false),
            agent: NotificationAgentData::fromAgent($this->agent),
            subject: (string) $this->count,
            detail: $this->firstCase,
        ));
    }
}
