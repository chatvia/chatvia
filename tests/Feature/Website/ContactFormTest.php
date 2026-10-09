<?php

use App\Marketing\Mail\ContactMail;
use Illuminate\Support\Facades\Mail;
use RyanChandler\LaravelCloudflareTurnstile\Facades\Turnstile;

/**
 * The contact form sends to `chatvia.contact_email`. When that is not set the
 * form is off and no mail is sent.
 */
function contactPayload(): array
{
    return [
        'name' => 'Jane',
        'email' => 'jane@example.com',
        'message' => 'Hello',
        'cf-turnstile-response' => Turnstile::fake()->dummy(),
    ];
}

it('sends the message to the configured inbox', function (): void {
    config(['chatvia.contact_email' => 'inbox@example.com']);
    Mail::fake();

    $this->post(route('contact.submit'), contactPayload())
        ->assertOk()
        ->assertSee('Thanks for reaching out!');

    Mail::assertSent(ContactMail::class, fn (ContactMail $mail): bool => $mail->hasTo('inbox@example.com'));
});

it('turns the form off when no inbox is configured', function (): void {
    config(['chatvia.contact_email' => null]);
    Mail::fake();

    $this->post(route('contact.submit'), contactPayload())
        ->assertOk()
        ->assertSee('The contact form is not available on this site right now.');

    Mail::assertNothingSent();
});

it('posts the form to the submit route, not the localized page URL', function (): void {
    config(['chatvia.contact_email' => 'inbox@example.com']);

    $this->get('/en/contact')
        ->assertOk()
        ->assertSee('action="'.route('contact.submit').'"', false);
});
