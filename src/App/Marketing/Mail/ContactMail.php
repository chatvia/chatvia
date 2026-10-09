<?php

declare(strict_types=1);

namespace App\Marketing\Mail;

use App\Marketing\Data\ContactMessageData;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * A contact-form message, delivered to the install's contact inbox. Replies
 * go straight to the person who wrote.
 */
class ContactMail extends Mailable
{
    public function __construct(public ContactMessageData $contact) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            replyTo: [new Address($this->contact->email, $this->contact->name)],
            subject: 'Contact form: '.$this->contact->name,
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.contact');
    }
}
