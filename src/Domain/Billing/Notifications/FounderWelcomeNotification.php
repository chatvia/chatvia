<?php

namespace Domain\Billing\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * A personal welcome note from the founder, sent to brand-new trial sign-ups
 * alongside the transactional TrialWelcomeNotification. Kept as its own
 * notification so it reads like a human email — Reply-To points at the founder
 * so replies reach him directly — and can be tuned or removed without touching
 * the account-ready email.
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

    public function toMail(object $notifiable): MailMessage
    {
        $founderName = (string) config('chatvia.founder.name');
        $founderEmail = (string) config('chatvia.founder.email');

        $firstName = trim((string) str($notifiable->name)->before(' '));
        $greeting = $firstName !== ''
            ? __('Hi :name,', ['name' => $firstName])
            : __('Hi there,');

        return (new MailMessage)
            // Keep the verified sender address (DKIM/SPF) but show the founder's
            // name, then route replies to his personal inbox.
            ->from(config('mail.from.address'), __(':name from Chatvia', ['name' => $founderName]))
            ->replyTo($founderEmail, $founderName)
            ->subject(__('A quick hello from Chatvia'))
            ->greeting($greeting)
            ->line(__('Thank you for starting your Chatvia trial. It truly means a lot to me that you are giving us a try.'))
            ->line(__('By choosing Chatvia, you are helping bring trusted, EU-based AI to more teams. We keep your data in Europe and put privacy first, and that is the reason we built Chatvia.'))
            ->line(__('I would love to hear what you are working on. What is the main use case you have in mind for your AI agents?'))
            ->line(__('Just reply to this email and it comes straight to me. I am always happy to help you get started.'))
            ->salutation(__('Warm regards, :name — Founder of Chatvia', ['name' => $founderName]));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [];
    }
}
