<?php

namespace App\Marketing\Actions;

use App\Marketing\Data\ContactMessageData;
use App\Marketing\Mail\ContactMail;
use App\Marketing\Services\ContactInbox;
use Illuminate\Support\Facades\Mail;

/**
 * Delivers a contact-form message to the install's contact inbox. Sending
 * the email is the whole operation, so it happens here rather than in a
 * listener. Nothing is sent when the install has no inbox configured.
 */
class SendContactMessageAction
{
    public function __construct(private readonly ContactInbox $inbox) {}

    /**
     * @return bool whether the message was sent (false when the form is off)
     */
    public function execute(ContactMessageData $contact): bool
    {
        $recipient = $this->inbox->address();

        if ($recipient === null) {
            return false;
        }

        Mail::to($recipient)->send(new ContactMail($contact));

        return true;
    }
}
