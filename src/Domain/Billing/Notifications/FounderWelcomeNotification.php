<?php

namespace Domain\Billing\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * A personal welcome note from the founder, sent to brand-new trial sign-ups
 * alongside the transactional TrialWelcomeNotification. Kept as its own
 * notification so it reads like a human email — Reply-To points at the founder
 * so replies reach them directly — and can be tuned or removed without touching
 * the account-ready email.
 *
 * Off unless `chatvia.founder.welcome_email` is on and both the founder's name
 * and email are set, so an install never signs mail with someone else's name.
 */
class FounderWelcomeNotification extends Notification
{
    use Queueable;

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Laravel checks this before each channel; returning false skips the mail.
     */
    public function shouldSend(object $notifiable, string $channel): bool
    {
        return self::isEnabled();
    }

    public static function isEnabled(): bool
    {
        return config('chatvia.founder.welcome_email') === true
            && filled(config('chatvia.founder.name'))
            && filled(config('chatvia.founder.email'));
    }

    public function toMail(object $notifiable): MailMessage
    {
        $founderName = (string) config('chatvia.founder.name');
        $founderEmail = (string) config('chatvia.founder.email');
        $brand = (string) config('chatvia.brand.name');

        $firstName = trim((string) str($notifiable->name)->before(' '));
        $greeting = $firstName !== ''
            ? __('Hi :name,', ['name' => $firstName])
            : __('Hi there,');

        return (new MailMessage)
            // Keep the verified sender address (DKIM/SPF) but show the founder's
            // name, then route replies to their own inbox.
            ->from(config('mail.from.address'), __(':name from :brand', ['name' => $founderName, 'brand' => $brand]))
            ->replyTo($founderEmail, $founderName)
            ->subject(__('A quick hello from :brand', ['brand' => $brand]))
            ->greeting($greeting)
            ->line(__('Thank you for starting your :brand trial. It truly means a lot to me that you are giving us a try.', ['brand' => $brand]))
            ->line(__('I would love to hear what you are working on. What is the main use case you have in mind for your AI agents?'))
            ->line(__('Just reply to this email and it comes straight to me. I am always happy to help you get started.'))
            ->salutation(__('Warm regards, :name — Founder of :brand', ['name' => $founderName, 'brand' => $brand]));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [];
    }
}
