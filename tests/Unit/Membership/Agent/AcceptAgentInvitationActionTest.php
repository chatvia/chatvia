<?php

use App\Identity\Workspace\Models\User;
use App\Identity\Workspace\Models\UserSocial;
use Domain\Agent\Models\Agent;
use Domain\Membership\Agent\Actions\AcceptAgentInvitationAction;
use Domain\Membership\Agent\Data\AcceptAgentInvitationData;
use Domain\Membership\Agent\Enums\InvitationCredentialKind;
use Domain\Membership\Agent\Exceptions\AgentChatterInvitationRejectedException;
use Illuminate\Support\Facades\Hash;
use Support\Enums\User\SocialProvider;
use Support\Enums\User\UserType;

/**
 * AcceptAgentInvitationAction is the account-binding step at the end of an
 * agent-invitation flow. The five security guards exercised below are the
 * load-bearing ones: a regression on any of them turns the email link into
 * an account-takeover vector.
 *  - Replay: a verified chatter cannot have their password silently reset
 *    via the same invite link.
 *  - Cross-workspace: a chatter owned by a different workspace cannot be
 *    claimed even if their email matches.
 *  - Revoked grant: a chatter whose agent_user pivot was already revoked
 *    cannot reactivate via this path.
 *  - Forwarded-link hijack (social): the OAuth account's email must match
 *    the invited email (hash_equals).
 *  - Happy paths: password sets a real password + verifies email; social
 *    creates a UserSocial row + verifies email without writing password.
 */
function aaiAgentAndChatter(string $email = 'invited@example.com'): array
{
    $workspace = User::factory()->create([
        'type' => UserType::Workspace,
        'email' => 'owner@example.com',
    ]);
    $agent = Agent::factory()->create(['user_id' => $workspace->id]);

    $chatter = User::factory()->create([
        'type' => UserType::Chatter,
        'workspace_id' => $workspace->id,
        'email' => $email,
        'email_verified_at' => null,
        'name' => 'Pending',
    ]);

    $agent->chatters()->attach($chatter->id);

    return [$agent, $chatter];
}

it('finalises the invitation on the password path: sets password, verifies email', function (): void {
    [$agent, $chatter] = aaiAgentAndChatter('invited@example.com');

    $returned = app(AcceptAgentInvitationAction::class)->execute(new AcceptAgentInvitationData(
        agent: $agent,
        invitedEmail: 'invited@example.com',
        name: 'Real Name',
        kind: InvitationCredentialKind::Password,
        password: 'a-real-password-1234',
    ));

    $chatter->refresh();
    expect($returned->id)->toBe($chatter->id)
        ->and($chatter->name)->toBe('Real Name')
        ->and($chatter->email_verified_at)->not->toBeNull()
        ->and(Hash::check('a-real-password-1234', $chatter->password))->toBeTrue();
});

it('finalises the invitation on the social path: creates UserSocial, verifies email, does NOT touch password', function (): void {
    [$agent, $chatter] = aaiAgentAndChatter('invited@example.com');
    $originalPassword = $chatter->password;

    app(AcceptAgentInvitationAction::class)->execute(new AcceptAgentInvitationData(
        agent: $agent,
        invitedEmail: 'invited@example.com',
        name: 'Social Name',
        kind: InvitationCredentialKind::Social,
        socialEmail: 'invited@example.com',
        socialProvider: SocialProvider::Google,
        socialProviderId: 'goog-123',
    ));

    $chatter->refresh();
    expect($chatter->name)->toBe('Social Name')
        ->and($chatter->email_verified_at)->not->toBeNull()
        ->and($chatter->password)->toBe($originalPassword);

    $social = UserSocial::query()
        ->where('user_id', $chatter->id)
        ->where('provider', SocialProvider::Google)
        ->first();
    expect($social)->not->toBeNull()
        ->and($social->provider_id)->toBe('goog-123');
});

it('REJECTS the social path when the OAuth email does not match the invited email', function (): void {
    [$agent] = aaiAgentAndChatter('invited@example.com');

    expect(fn () => app(AcceptAgentInvitationAction::class)->execute(new AcceptAgentInvitationData(
        agent: $agent,
        invitedEmail: 'invited@example.com',
        name: 'Different Person',
        kind: InvitationCredentialKind::Social,
        socialEmail: 'attacker@evil.example',
        socialProvider: SocialProvider::Google,
        socialProviderId: 'goog-999',
    )))->toThrow(AgentChatterInvitationRejectedException::class);
});

it('REJECTS replays: an already-verified chatter cannot reuse the invite', function (): void {
    [$agent, $chatter] = aaiAgentAndChatter('invited@example.com');
    $chatter->update(['email_verified_at' => now()]);

    expect(fn () => app(AcceptAgentInvitationAction::class)->execute(new AcceptAgentInvitationData(
        agent: $agent,
        invitedEmail: 'invited@example.com',
        name: 'Replay Attempt',
        kind: InvitationCredentialKind::Password,
        password: 'new-password-xxxx',
    )))->toThrow(AgentChatterInvitationRejectedException::class);
});

it('REJECTS a chatter from a different workspace even when the email matches', function (): void {
    [$agent] = aaiAgentAndChatter('invited@example.com');

    // Different workspace, same email, NOT attached to $agent.
    $otherWorkspace = User::factory()->create(['type' => UserType::Workspace]);
    User::factory()->create([
        'type' => UserType::Chatter,
        'workspace_id' => $otherWorkspace->id,
        'email' => 'foreign@example.com',
        'email_verified_at' => null,
    ]);

    expect(fn () => app(AcceptAgentInvitationAction::class)->execute(new AcceptAgentInvitationData(
        agent: $agent,
        invitedEmail: 'foreign@example.com',
        name: 'Foreign Chatter',
        kind: InvitationCredentialKind::Password,
        password: 'pw-1234-5678',
    )))->toThrow(AgentChatterInvitationRejectedException::class);
});

it('REJECTS when the agent_user pivot was revoked between invitation and accept', function (): void {
    [$agent, $chatter] = aaiAgentAndChatter('invited@example.com');
    $agent->chatters()->detach($chatter->id);

    expect(fn () => app(AcceptAgentInvitationAction::class)->execute(new AcceptAgentInvitationData(
        agent: $agent,
        invitedEmail: 'invited@example.com',
        name: 'Revoked',
        kind: InvitationCredentialKind::Password,
        password: 'pw-revoked-12',
    )))->toThrow(AgentChatterInvitationRejectedException::class);
});
