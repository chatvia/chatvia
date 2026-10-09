<?php

use App\Marketing\Actions\SendContactMessageAction;
use App\Marketing\Data\ContactMessageData;
use App\Marketing\Events\ContactMessageSubmitted;
use App\Marketing\Listeners\SendContactEmail;
use App\Marketing\Mail\ContactMail;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;

/**
 * Contact-form messages go to `chatvia.contact_email`, with the sender as the
 * reply-to address. The action fires an event and a listener sends the mail.
 * Without an inbox nothing is sent.
 */
function contactMessage(?string $company = 'Acme'): ContactMessageData
{
    return new ContactMessageData(name: 'Jane', email: 'jane@example.com', company: $company, message: "Hello\nthere");
}

it('mails the message to the inbox with the sender as reply-to', function (): void {
    config(['chatvia.contact_email' => 'inbox@example.com']);
    Mail::fake();

    expect(app(SendContactMessageAction::class)->execute(contactMessage()))->toBeTrue();

    Mail::assertSent(ContactMail::class, fn (ContactMail $mail): bool => $mail->hasTo('inbox@example.com')
        && $mail->hasReplyTo('jane@example.com', 'Jane')
        && $mail->hasSubject('Contact form: Jane'));
});

it('fires the event for the inbox and leaves the mail to its listener', function (): void {
    config(['chatvia.contact_email' => 'inbox@example.com']);
    Event::fake([ContactMessageSubmitted::class]);

    expect(app(SendContactMessageAction::class)->execute(contactMessage()))->toBeTrue();

    Event::assertDispatched(ContactMessageSubmitted::class, fn (ContactMessageSubmitted $event): bool => $event->inbox === 'inbox@example.com'
        && $event->contact->email === 'jane@example.com');
    Event::assertListening(ContactMessageSubmitted::class, SendContactEmail::class);
});

it('renders every field of the message', function (): void {
    $html = new ContactMail(contactMessage())->render();

    expect($html)->toContain('Jane')
        ->toContain('jane@example.com')
        ->toContain('Acme')
        ->toContain("Hello\nthere");
});

it('leaves the company line out when none was given', function (): void {
    expect(new ContactMail(contactMessage(company: null))->render())->not->toContain('Company:');
});

it('sends nothing when no inbox is configured', function (?string $inbox): void {
    config(['chatvia.contact_email' => $inbox]);
    Mail::fake();
    Event::fake([ContactMessageSubmitted::class]);

    expect(app(SendContactMessageAction::class)->execute(contactMessage()))->toBeFalse();

    Mail::assertNothingSent();
    Event::assertNotDispatched(ContactMessageSubmitted::class);
})->with([null, '']);
