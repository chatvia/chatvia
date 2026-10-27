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
 * An eval run someone started has finished. It carries how many cases passed
 * out of those scored ("8/10") and the run's status, and opens the run.
 */
class EvalRunFinishedNotification extends InboxNotification
{
    public function __construct(
        private readonly EvalRun $run,
        private readonly Agent $agent,
    ) {}

    public function kind(): NotificationKind
    {
        return NotificationKind::EvalRunFinished;
    }

    public function toInbox(User $notifiable): NotificationMessageData
    {
        return $this->message(new NotificationContentData(
            url: route('agents.evals.runs.show', ['agent' => $this->agent, 'run' => $this->run], absolute: false),
            agent: NotificationAgentData::fromAgent($this->agent),
            subject: "{$this->run->passed_count}/".($this->run->passed_count + $this->run->failed_count),
            detail: $this->run->status->value,
        ));
    }
}
