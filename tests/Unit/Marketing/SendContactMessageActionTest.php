<?php

use App\Marketing\Actions\SendContactMessageAction;
use App\Marketing\Data\ContactMessageData;
use App\Marketing\Mail\ContactMail;
use Illuminate\Support\Facades\Mail;

/**
 * Contact-form messages go to `chatvia.contact_email`, with the sender as the
 * reply-to address. Without an inbox nothing is sent.
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

    expect(app(SendContactMessageAction::class)->execute(contactMessage()))->toBeFalse();

    Mail::assertNothingSent();
})->with([null, '']);
