<?php

use App\Identity\Workspace\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\URL;

/**
 * Accepting a team invitation (AcceptTeamInvitationController): a new teammate
 * is signed straight in; an existing teammate with two-factor sign-in goes to
 * the challenge first. The guards are AcceptTeamInvitationAction's.
 */
beforeEach(function (): void {
    $this->owner = User::factory()->create();
    $this->team = ownerTeam($this->owner);
    $this->role = teamRole($this->team);
});

function atiAcceptUrl(int $invitationId): string
{
    return URL::signedRoute('teams.invitation.accept', ['invitation' => $invitationId]);
}

it('sends an existing teammate with two-factor to the challenge instead of signing them in', function (): void {
    $teammate = User::factory()->employee($this->owner)->create([
        'email' => 'twofa@example.com',
        'password' => Hash::make('correct-horse-battery'),
        'two_factor_secret' => encrypt('JBSWY3DPEHPK3PXP'),
        'two_factor_confirmed_at' => now(),
    ]);
    $invitation = $this->team->invitations()->create(['email' => 'twofa@example.com', 'role_id' => $this->role->id]);

    $this->post(atiAcceptUrl($invitation->id), ['password' => 'correct-horse-battery'])
        ->assertRedirect(route('two-factor.login'));

    expect(Auth::guard('web')->check())->toBeFalse()
        ->and(session('login.id'))->toBe($teammate->id);
});

it('signs a new teammate straight in', function (): void {
    $invitation = $this->team->invitations()->create(['email' => 'new@example.com', 'role_id' => $this->role->id]);

    $this->post(atiAcceptUrl($invitation->id), [
        'name' => 'New Person',
        'password' => 'a-strong-Password-123!',
        'password_confirmation' => 'a-strong-Password-123!',
        'policy' => '1',
        'terms' => '1',
    ])->assertRedirect(route('dashboard'));

    expect(Auth::guard('web')->user()?->email)->toBe('new@example.com');
});
