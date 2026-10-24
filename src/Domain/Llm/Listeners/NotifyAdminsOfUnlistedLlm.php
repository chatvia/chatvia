<?php

declare(strict_types=1);

namespace Domain\Llm\Listeners;

use Domain\Llm\Events\LlmUnlisted;
use Domain\Llm\Models\Llm;
use Domain\Llm\Notifications\LlmUnavailableInboxNotification;
use Domain\Notification\Services\NotificationRecipients;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;

/**
 * Tells the admins, who pick the models in the admin panel, that a provider
 * stopped serving one of them. Queued, off the job that found it.
 */
class NotifyAdminsOfUnlistedLlm implements ShouldQueue
{
    public function __construct(
        private readonly NotificationRecipients $recipients,
    ) {}

    public function handle(LlmUnlisted $event): void
    {
        $llm = Llm::query()->with('provider')->find($event->llmId);

        if ($llm instanceof Llm) {
            Notification::send($this->recipients->admins(), new LlmUnavailableInboxNotification($llm));
        }
    }
}
