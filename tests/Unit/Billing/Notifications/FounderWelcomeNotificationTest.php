<?php

use App\Identity\Workspace\Models\User;
use Domain\Billing\Notifications\FounderWelcomeNotification;
use Illuminate\Support\Facades\Notification;

function enableFounderWelcome(array $overrides = []): void
{
    config(array_merge([
        'chatvia.founder.welcome_email' => true,
        'chatvia.founder.name' => 'Jane Founder',
        'chatvia.founder.email' => 'founder@example.com',
        'chatvia.brand.name' => 'Acme Chat',
    ], $overrides));
}

it('is off by default so an install never mails in someone else\'s name', function (): void {
    expect(config('chatvia.founder.welcome_email'))->toBeFalse()
        ->and(config('chatvia.founder.name'))->toBeNull()
        ->and(config('chatvia.founder.email'))->toBeNull()
        ->and(FounderWelcomeNotification::isEnabled())->toBeFalse();
});

it('is skipped unless enabled with both a name and an email', function (array $overrides): void {
    enableFounderWelcome($overrides);
    Notification::fake();

    $user = User::factory()->create();
    $user->notify(new FounderWelcomeNotification);

    Notification::assertNotSentTo($user, FounderWelcomeNotification::class);
})->with([
    'flag off' => [['chatvia.founder.welcome_email' => false]],
    'no name' => [['chatvia.founder.name' => null]],
    'no email' => [['chatvia.founder.email' => '']],
]);

it('is sent when enabled and signs with the founder and the brand name', function (): void {
    enableFounderWelcome();
    Notification::fake();

    $user = User::factory()->create(['name' => 'Sam Customer']);
    $user->notify(new FounderWelcomeNotification);

    Notification::assertSentTo($user, FounderWelcomeNotification::class, function (FounderWelcomeNotification $notification) use ($user): bool {
        $mail = $notification->toMail($user);
        $text = collect($mail->introLines)->merge($mail->outroLines)->push($mail->subject, $mail->salutation)->implode(' ');

        return $mail->greeting === 'Hi Sam,'
            && $mail->subject === 'A quick hello from Acme Chat'
            && $mail->from[1] === 'Jane Founder from Acme Chat'
            && $mail->replyTo[0] === ['founder@example.com', 'Jane Founder']
            && str_contains($text, 'Founder of Acme Chat')
            && ! str_contains($text, 'Chatvia')
            && ! str_contains($text, 'EU-based');
    });
});
