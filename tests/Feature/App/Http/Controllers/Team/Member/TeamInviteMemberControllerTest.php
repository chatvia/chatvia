<?php

use App\Identity\Chatter\Models\Chatter;
use App\Identity\Workspace\Models\User;
use Domain\Membership\Team\Notifications\TeamInvitationNotification;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;

/**
 * Inviting to a team (TeamInviteMemberController): a teammate of the same
 * workspace is added by id at once; anyone else gets an emailed invitation,
 * sent by the SendTeamInvitationEmail listener (registered by hand in
 * DomainServiceProvider). Another workspace's teammate id is answered exactly
 * like an unknown id, and a chatter's email is invited like any other email:
 * a chatter is not a staff account.
 */
beforeEach(function (): void {
    $this->owner = User::factory()->create();
    $this->team = ownerTeam($this->owner);
    // A new team has only the owner role, which nobody can be invited to.
    teamRole($this->team);
});

it('answers another workspace\'s teammate id exactly like an unknown id', function (): void {
    $foreign = User::factory()->employee(User::factory()->create())->create();

    $errorFor = function (int $userId): string {
        $this->actingAs($this->owner)
            ->from('/')
            ->post(route('teams.invite', $this->team), ['user_id' => $userId, 'role' => 'support'])
            ->assertSessionHasErrors('user_id');

        return session('errors')->first('user_id');
    };

    expect($errorFor($foreign->id))->toBe($errorFor(999_999_999))
        ->and($this->team->members()->whereKey($foreign->id)->exists())->toBeFalse();
});

it('adds a teammate of the same workspace by id', function (): void {
    $teammate = User::factory()->employee($this->owner)->create();

    $this->actingAs($this->owner)
        ->from('/')
        ->post(route('teams.invite', $this->team), ['user_id' => $teammate->id, 'role' => 'support'])
        ->assertSessionHasNoErrors();

    expect($this->team->members()->whereKey($teammate->id)->exists())->toBeTrue();
});

it('saves the invitation and emails the invitee through the registered listener', function (string $email): void {
    // Events are not faked: the invitation must reach SendTeamInvitationEmail.
    Notification::fake();
    Chatter::factory()->ofWorkspace($this->owner)->create(['email' => 'chatter@example.com']);

    $this->actingAs($this->owner)
        ->from('/')
        ->post(route('teams.invite', $this->team), ['email' => $email, 'role' => 'support'])
        ->assertRedirect('/')
        ->assertSessionHasNoErrors();

    expect($this->team->invitations()->where('email', $email)->sole()->role->code)->toBe('support');

    // The invitee has no staff account yet, so the mail is routed on demand.
    Notification::assertSentOnDemand(
        TeamInvitationNotification::class,
        fn (TeamInvitationNotification $notification, array $channels, AnonymousNotifiable $notifiable): bool => ($notifiable->routes['mail'] ?? null) === $email,
    );
})->with([
    'a new email' => 'invitee@example.com',
    'the email of a workspace chatter' => 'chatter@example.com',
]);
