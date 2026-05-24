<?php

use App\Auth\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

describe('Email Verification', function () {
    it('shows email verification notice to unverified users', function () {
        $user = User::factory()->unverified()->create();

        $response = $this->actingAs($user)->get(route('verification.notice'));

        $response->assertOk();
    });

    it('redirects verified users away from verification notice', function () {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $response = $this->actingAs($user)->get(route('verification.notice'));

        $response->assertRedirect(route('dashboard'));
    });

    it('requires authentication to access verification notice', function () {
        $response = $this->get(route('verification.notice'));

        $response->assertRedirect(route('login'));
    });

    it('verifies email with valid signed URL', function () {
        Event::fake();

        $user = User::factory()->unverified()->create();

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)]
        );

        $response = $this->actingAs($user)->get($verificationUrl);

        Event::assertDispatched(Verified::class);

        expect($user->fresh()->hasVerifiedEmail())->toBeTrue();

        $response->assertRedirect(route('dashboard', ['verified' => 1]));
    });

    it('rejects verification with invalid signature', function () {
        $user = User::factory()->unverified()->create();

        $invalidUrl = route('verification.verify', [
            'id' => $user->id,
            'hash' => sha1($user->email),
        ]);

        $response = $this->actingAs($user)->get($invalidUrl);

        $response->assertStatus(403);

        expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
    });

    it('rejects verification with wrong user id', function () {
        $user = User::factory()->unverified()->create();
        $otherUser = User::factory()->unverified()->create();

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $otherUser->id, 'hash' => sha1($user->email)]
        );

        $response = $this->actingAs($user)->get($verificationUrl);

        $response->assertStatus(403);
    });

    it('rejects verification with wrong email hash', function () {
        $user = User::factory()->unverified()->create();

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1('wrong@email.com')]
        );

        $response = $this->actingAs($user)->get($verificationUrl);

        $response->assertStatus(403);

        expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
    });

    it('rejects verification with expired signature', function () {
        $user = User::factory()->unverified()->create();

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->subMinutes(1), // Expired
            ['id' => $user->id, 'hash' => sha1($user->email)]
        );

        $response = $this->actingAs($user)->get($verificationUrl);

        $response->assertStatus(403);

        expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
    });

    it('does not verify already verified email', function () {
        Event::fake();

        $user = User::factory()->create(['email_verified_at' => now()]);

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)]
        );

        $response = $this->actingAs($user)->get($verificationUrl);

        Event::assertNotDispatched(Verified::class);

        $response->assertRedirect(route('dashboard', ['verified' => 1]));
    });

    it('throttles verification attempts', function () {
        $user = User::factory()->unverified()->create();

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)]
        );

        // Make multiple requests
        for ($i = 0; $i < 7; $i++) {
            $this->actingAs($user)->get($verificationUrl);
        }

        // Next request should be throttled
        $response = $this->actingAs($user)->get($verificationUrl);

        $response->assertStatus(429); // Too Many Requests
    });
});

describe('Email Verification Notification', function () {
    beforeEach(function () {
        Notification::fake();
    });

    it('sends verification notification to unverified users', function () {
        $user = User::factory()->unverified()->create();

        $response = $this->actingAs($user)->post(route('verification.send'));

        Notification::assertSentTo($user, VerifyEmail::class);

        $response->assertSessionHas('status');
    });

    it('does not send notification to verified users', function () {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $response = $this->actingAs($user)->post(route('verification.send'));

        Notification::assertNothingSent();
    });

    it('requires authentication to send verification notification', function () {
        $response = $this->post(route('verification.send'));

        $response->assertRedirect(route('login'));
    });

    it('throttles verification notification requests', function () {
        $user = User::factory()->unverified()->create();

        // Make multiple requests
        for ($i = 0; $i < 7; $i++) {
            $this->actingAs($user)->post(route('verification.send'));
        }

        // Next request should be throttled
        $response = $this->actingAs($user)->post(route('verification.send'));

        $response->assertStatus(429); // Too Many Requests
    });

    it('generates unique verification URL for each user', function () {
        $user1 = User::factory()->unverified()->create();
        $user2 = User::factory()->unverified()->create();

        $this->actingAs($user1)->post(route('verification.send'));
        $this->actingAs($user2)->post(route('verification.send'));

        Notification::assertSentTo($user1, VerifyEmail::class);
        Notification::assertSentTo($user2, VerifyEmail::class);
    });
});

describe('Protected Routes', function () {
    it('allows verified users to access dashboard', function () {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
    });

    it('redirects unverified users to verification notice', function () {
        $user = User::factory()->unverified()->create();

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertRedirect(route('verification.notice'));
    });
});
