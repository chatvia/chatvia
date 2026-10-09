<?php

declare(strict_types=1);

namespace App\Marketing\Actions;

use App\Marketing\Data\ContactMessageData;
use App\Marketing\Events\ContactMessageSubmitted;
use App\Marketing\Listeners\SendContactEmail;
use App\Marketing\Services\ContactInbox;

/**
 * Accepts a contact-form message for the install's contact inbox. The email
 * itself is a side effect, sent by {@see SendContactEmail} when
 * {@see ContactMessageSubmitted} fires. Nothing is sent when the install has
 * no inbox configured.
 */
class SendContactMessageAction
{
    public function __construct(private readonly ContactInbox $inbox) {}

    /**
     * @return bool whether the message was accepted (false when the form is off)
     */
    public function execute(ContactMessageData $contact): bool
    {
        $recipient = $this->inbox->address();

        if ($recipient === null) {
            return false;
        }

        ContactMessageSubmitted::dispatch($contact, $recipient);

        return true;
    }
}
