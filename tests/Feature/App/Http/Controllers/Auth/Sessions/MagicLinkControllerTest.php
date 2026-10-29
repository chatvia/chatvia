<?php

use App\Identity\Workspace\Models\User;
use App\Identity\Workspace\Notifications\MagicLinkNotification;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;

/**
 * The dashboard's magic sign-in link: the form answers the same for a known
 * and an unknown email, and only a known one gets a link.
 */
beforeEach(function (): void {
    Notification::fake();
});

it('emails a known address a link that signs it in to the dashboard', function (): void {
    $user = User::factory()->create(['email' => 'known-link@example.test']);

    $this->from(route('login'))
        ->post(route('magic-link.send'), ['email' => 'known-link@example.test'])
        ->assertRedirect(route('login'))
        ->assertSessionHas('status');

    $url = null;
    Notification::assertSentOnDemand(
        MagicLinkNotification::class,
        function (MagicLinkNotification $notification, array $channels, AnonymousNotifiable $notifiable) use (&$url): bool {
            $url = $notification->url;

            return $notifiable->routes['mail'] === 'known-link@example.test';
        },
    );
    expect(auth()->check())->toBeFalse();

    $this->get($url)->assertRedirect(route('dashboard'));

    expect(auth()->user()?->is($user))->toBeTrue();
});

it('answers an unknown address the same way as a known one and sends nothing', function (): void {
    User::factory()->create(['email' => 'known-link@example.test']);
    $this->from(route('login'))->post(route('magic-link.send'), ['email' => 'known-link@example.test']);
    $known = session('status');
    Notification::fake();

    $this->from(route('login'))
        ->post(route('magic-link.send'), ['email' => 'nobody-link@example.test'])
        ->assertRedirect(route('login'))
        ->assertSessionHas('status', $known);

    Notification::assertNothingSent();
});
