<?php

declare(strict_types=1);

namespace App\Identity\Chatter\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Email to a chatter whose access a workspace turned off for abuse. Plain and
 * fixed: it says what happened and what to do, never the admin's internal
 * reason. Sent in the chatter's language (Chatter::preferredLocale()).
 */
class ChatterBlockedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $workspaceName,
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
        $name = ['name' => $this->workspaceName];

        return (new MailMessage)
            ->subject(__('Your chat access has been turned off'))
            ->line(__('The team at :name turned off your access to chat with their assistants.', $name))
            ->line(__('This happens when messages try to make an assistant break its rules or show its instructions.'))
            ->line(__('If you think this is a mistake, please contact :name.', $name));
    }
}
