<?php

namespace App\Notifications;

use App\Models\Agent;
use App\Models\Member;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MemberInvitationNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly Agent $agent,
        private readonly Member $member,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Invitation to join "'.$this->agent->name.'" agent')
            ->line('This is an invitation to join "'.$this->agent->name.'" agent.')
            ->when(is_null($this->member->email_verified_at), function (MailMessage $message) {
                $message
                    ->line('You can accept it by clicking the button below:')
                    ->action('Accept Invitation', route('agents.invitations', [
                        'agent' => $this->agent->id,
                        'token' => $this->member->verification_token,
                    ]))
                    ->line('Please ignore this email if you did not request this invitation.');
            })
            ->line('Thank you for using our application!');
    }

    public function toArray(object $notifiable): array
    {
        return [

        ];
    }
}
