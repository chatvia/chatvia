<?php

declare(strict_types=1);

namespace Domain\Form\Listeners;

use Domain\Agent\Models\Agent;
use Domain\Form\Events\FormSubmitted;
use Domain\Form\Models\FormDefinition;
use Domain\Form\Notifications\LeadCapturedNotification;
use Domain\Notification\Services\NotificationRecipients;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;

/**
 * Tells the people who may see the agent's leads that a new one came in.
 * Only a submission that created a lead counts. Queued, off the chat path.
 */
class NotifyLeadCaptured implements ShouldQueue
{
    public function __construct(
        private readonly NotificationRecipients $recipients,
    ) {}

    public function handle(FormSubmitted $event): void
    {
        $submission = $event->submission;

        if (! $submission->created) {
            return;
        }

        $agent = Agent::query()->find($submission->agentId);
        $form = FormDefinition::query()
            ->where('agent_id', $submission->agentId)
            ->where('slug', $submission->formSlug)
            ->first();

        if (! $agent instanceof Agent || ! $form instanceof FormDefinition) {
            return;
        }

        Notification::send(
            $this->recipients->forAgent($agent, 'agent.leads.view'),
            new LeadCapturedNotification($form, $agent),
        );
    }
}
