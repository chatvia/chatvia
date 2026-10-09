<?php

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
            subject: 'Contact form: '.$this->contact->name,
            replyTo: [new Address($this->contact->email, $this->contact->name)],
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.contact');
    }
}
