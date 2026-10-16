<?php

use App\Identity\Chatter\Models\Chatter;
use App\Identity\Workspace\Enums\SocialProvider;
use App\Identity\Workspace\Models\User;
use App\Identity\Workspace\Models\UserSocial;
use Carbon\CarbonInterface;
use Domain\Agent\Models\Agent;
use Domain\Membership\Agent\Actions\AcceptAgentInvitationAction;
use Domain\Membership\Agent\Data\AcceptAgentInvitationData;
use Domain\Membership\Agent\Enums\InvitationCredentialKind;
use Domain\Membership\Agent\Exceptions\AgentChatterInvitationRejectedException;
use Domain\Membership\Agent\Models\AgentChatterInvitation;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * AcceptAgentInvitationAction is the account-binding step at the end of an
 * agent-invitation flow. The security guards exercised below are the
 * load-bearing ones: a regression on any of them turns the email link into
 * an account-takeover vector.
 *  - Replay: a verified chatter cannot have their password silently reset
 *    via the same invite link.
 *  - Cross-workspace: only the chatter of the agent's own workspace is
 *    activated; a chatter of another workspace with the same email is a
 *    different person and is never touched.
 *  - Revoked grant: a chatter whose agent_chatter grant was already revoked
 *    cannot reactivate via this path.
 *  - Forwarded-link hijack (social): the OAuth account's email must match
 *    the invited email (hash_equals).
 *  - Happy paths: password sets a real password + verifies email; social
 *    verifies email without writing a password or linking a social account.
 *  - Single use: the invitation row is checked (exists, not expired) and
 *    deleted inside the same transaction as the account update.
 */
function aaiAgentAndChatter(string $email = 'invited@example.com'): array
{
    $workspace = User::factory()->create();
    $agent = Agent::factory()->create(['workspace_id' => $workspace->workspace()->id]);

    $chatter = Chatter::factory()->grantedTo($agent)->create([
        'email' => $email,
        'email_verified_at' => null,
        'password' => null,
        'name' => 'Pending',
    ]);

    return [$agent, $chatter];
}

function aaiInvitation(Agent $agent, string $email = 'invited@example.com', ?CarbonInterface $expiresAt = null): AgentChatterInvitation
{
    return AgentChatterInvitation::create([
        'agent_id' => $agent->id,
        'email' => $email,
        'token' => Str::random(64),
        'expires_at' => $expiresAt ?? now()->addDays(7),
    ]);
}

it('finalises the invitation on the password path: sets password, verifies email', function (): void {
    [$agent, $chatter] = aaiAgentAndChatter('invited@example.com');

    $returned = app(AcceptAgentInvitationAction::class)->execute(new AcceptAgentInvitationData(
        invitation: aaiInvitation($agent, 'invited@example.com'),
        name: 'Real Name',
        kind: InvitationCredentialKind::Password,
        password: 'a-real-password-1234',
    ));

    $chatter->refresh();
    expect($returned)->toBeInstanceOf(Chatter::class)
        ->and($returned->id)->toBe($chatter->id)
        ->and($chatter->name)->toBe('Real Name')
        ->and($chatter->email_verified_at)->not->toBeNull()
        ->and(Hash::check('a-real-password-1234', $chatter->password))->toBeTrue();
});

it('finalises the invitation on the social path: verifies email, does NOT touch password or link a social account', function (): void {
    [$agent, $chatter] = aaiAgentAndChatter('invited@example.com');
    $originalPassword = $chatter->password;

    app(AcceptAgentInvitationAction::class)->execute(new AcceptAgentInvitationData(
        invitation: aaiInvitation($agent, 'invited@example.com'),
        name: 'Social Name',
        kind: InvitationCredentialKind::Social,
        socialEmail: 'invited@example.com',
        socialProvider: SocialProvider::Google,
        socialProviderId: 'goog-123',
    ));

    $chatter->refresh();
    expect($chatter->name)->toBe('Social Name')
        ->and($chatter->email_verified_at)->not->toBeNull()
        ->and($chatter->password)->toBe($originalPassword)
        // Chatters are not staff users, so nothing is written to user_socials.
        ->and(UserSocial::query()->where('provider_id', 'goog-123')->exists())->toBeFalse();
});

it('accepts the social path when the OAuth email differs only in case', function (): void {
    [$agent, $chatter] = aaiAgentAndChatter('invited@example.com');

    app(AcceptAgentInvitationAction::class)->execute(new AcceptAgentInvitationData(
        invitation: aaiInvitation($agent, 'invited@example.com'),
        name: 'Social Name',
        kind: InvitationCredentialKind::Social,
        socialEmail: 'Invited@Example.COM',
        socialProvider: SocialProvider::Google,
        socialProviderId: 'goog-123',
    ));

    expect($chatter->fresh()->email_verified_at)->not->toBeNull();
});

it('REJECTS the social path when the OAuth email does not match the invited email', function (): void {
    [$agent, $chatter] = aaiAgentAndChatter('invited@example.com');

    expect(fn () => app(AcceptAgentInvitationAction::class)->execute(new AcceptAgentInvitationData(
        invitation: aaiInvitation($agent, 'invited@example.com'),
        name: 'Different Person',
        kind: InvitationCredentialKind::Social,
        socialEmail: 'attacker@evil.example',
        socialProvider: SocialProvider::Google,
        socialProviderId: 'goog-999',
    )))->toThrow(AgentChatterInvitationRejectedException::class);

    expect($chatter->fresh()->email_verified_at)->toBeNull();
});

it('REJECTS replays: an already-verified chatter cannot reuse the invite', function (): void {
    [$agent, $chatter] = aaiAgentAndChatter('invited@example.com');
    $chatter->update(['email_verified_at' => now(), 'password' => Hash::make('original-pass')]);

    expect(fn () => app(AcceptAgentInvitationAction::class)->execute(new AcceptAgentInvitationData(
        invitation: aaiInvitation($agent, 'invited@example.com'),
        name: 'Replay Attempt',
        kind: InvitationCredentialKind::Password,
        password: 'new-password-xxxx',
    )))->toThrow(AgentChatterInvitationRejectedException::class);

    expect(Hash::check('original-pass', $chatter->fresh()->password))->toBeTrue();
});

it('REJECTS when only a chatter of another workspace has the invited email, and leaves that chatter alone', function (): void {
    [$agent] = aaiAgentAndChatter('invited@example.com');

    // Different workspace, same email, NOT attached to $agent.
    $otherWorkspace = User::factory()->create();
    $foreign = Chatter::factory()->ofWorkspace($otherWorkspace)->create([
        'email' => 'foreign@example.com',
        'email_verified_at' => null,
        'password' => null,
    ]);

    expect(fn () => app(AcceptAgentInvitationAction::class)->execute(new AcceptAgentInvitationData(
        invitation: aaiInvitation($agent, 'foreign@example.com'),
        name: 'Foreign Chatter',
        kind: InvitationCredentialKind::Password,
        password: 'pw-1234-5678',
    )))->toThrow(AgentChatterInvitationRejectedException::class);

    $foreign->refresh();
    expect($foreign->email_verified_at)->toBeNull()
        ->and($foreign->password)->toBeNull();
});

it('activates only the chatter of the agent workspace when another workspace has the same email', function (): void {
    [$agent, $chatter] = aaiAgentAndChatter('shared@example.com');

    $otherAgent = Agent::factory()->forOwner(User::factory()->create())->create();
    $twin = Chatter::factory()->grantedTo($otherAgent)->create([
        'email' => 'shared@example.com',
        'email_verified_at' => null,
        'password' => null,
    ]);

    $returned = app(AcceptAgentInvitationAction::class)->execute(new AcceptAgentInvitationData(
        invitation: aaiInvitation($agent, 'shared@example.com'),
        name: 'Real Name',
        kind: InvitationCredentialKind::Password,
        password: 'a-real-password-1234',
    ));

    $twin->refresh();
    expect($returned->id)->toBe($chatter->id)
        ->and($chatter->fresh()->email_verified_at)->not->toBeNull()
        ->and($twin->email_verified_at)->toBeNull()
        ->and($twin->password)->toBeNull();
});

it('REJECTS when the agent_chatter grant was revoked between invitation and accept', function (): void {
    [$agent, $chatter] = aaiAgentAndChatter('invited@example.com');
    $agent->chatters()->detach($chatter->id);

    expect(fn () => app(AcceptAgentInvitationAction::class)->execute(new AcceptAgentInvitationData(
        invitation: aaiInvitation($agent, 'invited@example.com'),
        name: 'Revoked',
        kind: InvitationCredentialKind::Password,
        password: 'pw-revoked-12',
    )))->toThrow(AgentChatterInvitationRejectedException::class);
});

it('deletes the invitation in the same transaction that activates the chatter', function (): void {
    [$agent, $chatter] = aaiAgentAndChatter('invited@example.com');
    $invitation = aaiInvitation($agent);

    app(AcceptAgentInvitationAction::class)->execute(new AcceptAgentInvitationData(
        invitation: $invitation,
        name: 'Real Name',
        kind: InvitationCredentialKind::Password,
        password: 'a-real-password-1234',
    ));

    expect(AgentChatterInvitation::query()->whereKey($invitation->id)->exists())->toBeFalse()
        ->and($chatter->fresh()->email_verified_at)->not->toBeNull();
});

it('REJECTS an expired invitation and leaves the chatter pending', function (): void {
    [$agent, $chatter] = aaiAgentAndChatter('invited@example.com');
    $invitation = aaiInvitation($agent, expiresAt: now()->subMinute());

    expect(fn () => app(AcceptAgentInvitationAction::class)->execute(new AcceptAgentInvitationData(
        invitation: $invitation,
        name: 'Too Late',
        kind: InvitationCredentialKind::Password,
        password: 'pw-too-late-123',
    )))->toThrow(AgentChatterInvitationRejectedException::class);

    expect($chatter->fresh()->email_verified_at)->toBeNull();
});

it('REJECTS an invitation that was already used (deleted) even with a stale copy in hand', function (): void {
    [$agent, $chatter] = aaiAgentAndChatter('invited@example.com');
    $invitation = aaiInvitation($agent);
    $staleCopy = clone $invitation;

    // Another request accepted (and deleted) it first.
    $invitation->delete();

    expect(fn () => app(AcceptAgentInvitationAction::class)->execute(new AcceptAgentInvitationData(
        invitation: $staleCopy,
        name: 'Racer',
        kind: InvitationCredentialKind::Password,
        password: 'pw-racer-12345',
    )))->toThrow(AgentChatterInvitationRejectedException::class);

    expect($chatter->fresh()->email_verified_at)->toBeNull();
});

it('keeps the invitation when the accept is rejected, so the owner can fix access and it still works', function (): void {
    [$agent, $chatter] = aaiAgentAndChatter('invited@example.com');
    $invitation = aaiInvitation($agent);
    $agent->chatters()->detach($chatter->id);

    expect(fn () => app(AcceptAgentInvitationAction::class)->execute(new AcceptAgentInvitationData(
        invitation: $invitation,
        name: 'Revoked',
        kind: InvitationCredentialKind::Password,
        password: 'pw-revoked-12',
    )))->toThrow(AgentChatterInvitationRejectedException::class);

    expect(AgentChatterInvitation::query()->whereKey($invitation->id)->exists())->toBeTrue();
});
