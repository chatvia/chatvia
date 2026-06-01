<?php

namespace Domain\Billing\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TrialWelcomeNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $temporaryPassword,
        private readonly string $package,
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $loginUrl = route('login', absolute: true);

        return (new MailMessage)
            ->subject('Welcome to ChatVia - Your Account is Ready!')
            ->greeting('Welcome to ChatVia!')
            ->line('Thank you for signing up for your trial. Your account has been successfully created and is ready to use.')
            ->line('**Your Login Credentials:**')
            ->line('**Email:** '.$notifiable->email)
            ->line('**Temporary Password:** '.$this->temporaryPassword)
            ->line('**Package:** '.ucfirst($this->package))
            ->line('Please use these credentials to log in to your account. We recommend changing your password after your first login for security purposes.')
            ->action('Log In to Your Account', $loginUrl)
            ->line('If you have any questions or need assistance, please don\'t hesitate to reach out to our support team.')
            ->line('Thank you for choosing ChatVia!');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            //
        ];
    }
}
