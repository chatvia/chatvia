<?php

namespace App\Identity\Workspace\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sign-in email containing a single-use magic link. Opening the link logs the
 * recipient in via the cesargb/laravel-magiclink action and redirects them to
 * the destination wired into the action by the dispatcher.
 */
class MagicLinkNotification extends Notification
{
    public function __construct(
        public string $url,
        public ?string $contextName = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $subject = $this->contextName !== null
            ? __('Sign in to :context', ['context' => $this->contextName])
            : __('Your sign-in link');

        $intro = $this->contextName !== null
            ? __('Click the button below to sign in to **:context**.', ['context' => $this->contextName])
            : __('Click the button below to sign in.');

        return (new MailMessage)
            ->subject($subject)
            ->greeting(__('Hello!'))
            ->line($intro)
            ->action(__('Sign in'), $this->url)
            ->line(__('This link will expire in 15 minutes and can only be used once.'))
            ->line(__("If you didn't request this email, you can safely ignore it."));
    }
}
