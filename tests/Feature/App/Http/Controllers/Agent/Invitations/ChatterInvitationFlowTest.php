<?php

use App\Identity\Chatter\Models\Chatter;
use App\Identity\Workspace\Models\User;
use Domain\Membership\Agent\Events\AgentChatterInvited;
use Domain\Membership\Agent\Notifications\AgentChatterInvitationNotification;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;

/**
 * Inviting a chatter from an agent's Members tab (agents.chatters.store) up to
 * the email with the accept link: the AgentChatterInvited event reaches the
 * SendAgentChatterInvitationEmail listener (registered by hand in
 * DomainServiceProvider). An email known in another workspace becomes a
 * separate chatter, and the answer never says so. InviteAgentChatterAction's
 * test owns the rows it writes.
 */
beforeEach(function (): void {
    $this->owner = User::factory()->create();
    $this->agent = createAgent($this->owner);
});

it('invites a chatter to the agent and emails them the accept link', function (): void {
    Notification::fake();

    $this->actingAs($this->owner)
        ->from('/')
        ->post(route('agents.chatters.store', $this->agent), ['email' => 'Chatter@Example.com'])
        ->assertRedirect('/')
        ->assertSessionHasNoErrors()
        ->assertSessionHas('invitation.email', 'chatter@example.com');

    $chatter = $this->owner->workspace()->chatters()->where('email', 'chatter@example.com')->sole();

    expect($this->agent->chatters()->whereKey($chatter->id)->exists())->toBeTrue()
        ->and(User::query()->where('email', 'chatter@example.com')->exists())->toBeFalse();

    Notification::assertSentOnDemand(
        AgentChatterInvitationNotification::class,
        fn (AgentChatterInvitationNotification $notification, array $channels, AnonymousNotifiable $notifiable): bool => ($notifiable->routes['mail'] ?? null) === 'chatter@example.com',
    );
});

it('invites an email used in another workspace as a separate chatter, answering exactly as for a new email', function (): void {
    Event::fake([AgentChatterInvited::class]);

    $otherOwner = User::factory()->create();
    $foreign = Chatter::factory()->grantedTo(createAgent($otherOwner))->create(['email' => 'shared@example.com']);
    User::factory()->employee($otherOwner)->create(['email' => 'staff-elsewhere@example.com']);

    $flashFor = function (string $email): array {
        $this->actingAs($this->owner)
            ->from('/')
            ->post(route('agents.chatters.store', $this->agent), ['email' => $email])
            ->assertRedirect('/')
            ->assertSessionHasNoErrors()
            ->assertSessionHas('invitation');

        return session('invitation');
    };

    $known = $flashFor('shared@example.com');
    $staff = $flashFor('staff-elsewhere@example.com');
    $unknown = $flashFor('brand-new@example.com');

    expect(array_keys($known))->toBe(array_keys($unknown))
        ->and(array_keys($staff))->toBe(array_keys($unknown))
        ->and($known['email'])->toBe('shared@example.com');

    $local = $this->owner->workspace()->chatters()->where('email', 'shared@example.com')->sole();

    expect($local->id)->not->toBe($foreign->id)
        ->and($this->agent->chatters()->whereKey($local->id)->exists())->toBeTrue()
        ->and($this->agent->chatters()->whereKey($foreign->id)->exists())->toBeFalse();
});
