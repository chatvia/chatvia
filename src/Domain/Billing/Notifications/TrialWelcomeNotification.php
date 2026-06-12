<?php

namespace Domain\Billing\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TrialWelcomeNotification extends Notification
{
    use Queueable;

    public function __construct(
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
     *
     * The account's password is never included here — passwords don't travel
     * by email. The owner chooses one on the set-password page they are
     * redirected to right after checkout.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $loginUrl = route('login', absolute: true);

        return (new MailMessage)
            ->subject('Welcome to ChatVia - Your Account is Ready!')
            ->greeting('Welcome to ChatVia!')
            ->line('Thank you for signing up. Your account has been created and is ready to use.')
            ->line('**Email:** '.$notifiable->email)
            ->line('**Package:** '.ucfirst($this->package))
            ->line('For your security, we never send passwords by email. You choose your own password on the page that opens right after your payment.')
            ->action('Log In to Your Account', $loginUrl)
            ->line('Signed out before choosing a password? Use the magic link option on the login page and we will email you a sign-in link.')
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
