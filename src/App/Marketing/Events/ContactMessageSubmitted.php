<?php

declare(strict_types=1);

namespace App\Marketing\Events;

use App\Marketing\Data\ContactMessageData;
use App\Marketing\Listeners\SendContactEmail;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A visitor sent the public contact form and the install has an inbox for it.
 * Nothing has been emailed yet: {@see SendContactEmail} delivers the message
 * to the inbox address carried here.
 */
class ContactMessageSubmitted
{
    use Dispatchable;

    public function __construct(
        public ContactMessageData $contact,
        public string $inbox,
    ) {}
}
