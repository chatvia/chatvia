<?php

namespace Domain\Agent\Notifications;

use Domain\Agent\Models\Agent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Heads-up email sent to the workspace owner when one of their agents starts
 * hitting its per-minute chat rate limit — a signal of either a traffic spike
 * worth raising the limit for, or abuse worth investigating.
 */
class AgentRateLimitNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Agent $agent,
        public int $limitPerMinute,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('Your agent ":name" hit its rate limit', ['name' => $this->agent->name]))
            ->greeting(__('Heads up!'))
            ->line(__('Your agent ":name" reached its chat rate limit of :limit messages per minute, so some messages were turned away.', ['name' => $this->agent->name, 'limit' => $this->limitPerMinute]))
            ->line(__('If this is expected traffic, raise the limit on the agent\'s Security page. If it looks like abuse, you may want to make the agent protected.'))
            ->action(__('Open agent security settings'), url(route('agents.identity', $this->agent->external_id, absolute: true)))
            ->line(__('You can turn these alerts off from the same page.'));
    }
}
