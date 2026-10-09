<?php

declare(strict_types=1);

namespace App\Marketing\Listeners;

use App\Marketing\Events\ContactMessageSubmitted;
use App\Marketing\Mail\ContactMail;
use Illuminate\Support\Facades\Mail;

/**
 * Emails a contact-form message to the install's contact inbox, with the
 * sender as the reply-to address.
 */
class SendContactEmail
{
    public function handle(ContactMessageSubmitted $event): void
    {
        Mail::to($event->inbox)->send(new ContactMail($event->contact));
    }
}
