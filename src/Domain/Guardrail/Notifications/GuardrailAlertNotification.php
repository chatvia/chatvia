<?php

declare(strict_types=1);

namespace Domain\Guardrail\Notifications;

use Domain\Agent\Models\Agent;
use Domain\Guardrail\Enums\GuardAlert;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Email to the workspace owner when an agent's guardrails blocked an attack
 * or could not check messages, sent only when the owner turned alerts on for
 * the agent. Links to the agent's Guardrails Activity, filtered to the cause.
 */
class GuardrailAlertNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Agent $agent,
        public GuardAlert $alert,
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
        $name = ['name' => $this->agent->name];

        $message = (new MailMessage)
            ->subject(__('Guardrails on ":name" need your attention', $name))
            ->greeting(__('Heads up!'));

        $message = $this->alert === GuardAlert::Attack
            ? $message->line(__('Your agent ":name" blocked an attempt to override its instructions or reveal them.', $name))
                ->line(__('One attempt is usually nothing to worry about. Many in a short time may be someone testing your agent.'))
            : $message->line(__('The safety check of your agent ":name" could not run, so some messages were answered without it.', $name))
                ->line(__('This happens when the AI model for guardrails is not reachable. Check the model in the admin panel if it keeps happening.'));

        return $message
            ->action(__('Open the guardrails activity'), url(route('agents.guardrails', [
                'agent' => $this->agent->external_id,
                'outcome' => $this->alert === GuardAlert::Skipped ? 'skipped' : 'blocked',
            ], absolute: true)))
            ->line(__('You get at most one such email per hour. You can turn these alerts off on the Guardrails page.'));
    }
}
