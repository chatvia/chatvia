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
 *    deleted when the account is activated; a rejected accept keeps it.
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
    return AgentChatterInvitation::factory()->forAgent($agent)->create(array_filter([
        'email' => $email,
        'expires_at' => $expiresAt,
    ]));
}

function aaiPasswordData(AgentChatterInvitation $invitation, string $password = 'a-real-password-1234'): AcceptAgentInvitationData
{
    return new AcceptAgentInvitationData(invitation: $invitation, name: 'Real Name', kind: InvitationCredentialKind::Password, password: $password);
}

it('activates only the agent workspace\'s chatter on the password path and uses up the invitation', function (): void {
    [$agent, $chatter] = aaiAgentAndChatter('shared@example.com');
    $twin = Chatter::factory()->grantedTo(Agent::factory()->forOwner(User::factory()->create())->create())->create([
        'email' => 'shared@example.com',
        'email_verified_at' => null,
        'password' => null,
    ]);
    $invitation = aaiInvitation($agent, 'shared@example.com');

    $returned = app(AcceptAgentInvitationAction::class)->execute(aaiPasswordData($invitation));

    expect($returned->id)->toBe($chatter->id)
        ->and($chatter->fresh()->name)->toBe('Real Name')
        ->and($chatter->fresh()->email_verified_at)->not->toBeNull()
        ->and(Hash::check('a-real-password-1234', $chatter->fresh()->password))->toBeTrue()
        ->and(AgentChatterInvitation::query()->whereKey($invitation->id)->exists())->toBeFalse()
        ->and($twin->fresh()->email_verified_at)->toBeNull()
        ->and($twin->fresh()->password)->toBeNull();
});

it('activates the chatter on the social path for an email differing only in case, without a password or social account', function (): void {
    [$agent, $chatter] = aaiAgentAndChatter('invited@example.com');
    $originalPassword = $chatter->password;

    app(AcceptAgentInvitationAction::class)->execute(new AcceptAgentInvitationData(
        invitation: aaiInvitation($agent, 'invited@example.com'),
        name: 'Social Name',
        kind: InvitationCredentialKind::Social,
        socialEmail: 'Invited@Example.COM',
        socialProvider: SocialProvider::Google,
        socialProviderId: 'goog-123',
    ));

    expect($chatter->fresh()->name)->toBe('Social Name')
        ->and($chatter->fresh()->email_verified_at)->not->toBeNull()
        ->and($chatter->fresh()->password)->toBe($originalPassword)
        ->and(UserSocial::query()->where('provider_id', 'goog-123')->exists())->toBeFalse();
});

it('rejects an accept that would take over or revive an account, and changes nothing', function (Closure $arrange): void {
    [$data, $unchanged] = $arrange();

    expect(fn () => app(AcceptAgentInvitationAction::class)->execute($data))->toThrow(AgentChatterInvitationRejectedException::class);

    $unchanged();
})->with([
    'a social email that is not the invited one' => [function (): array {
        [$agent, $chatter] = aaiAgentAndChatter('invited@example.com');

        return [new AcceptAgentInvitationData(
            invitation: aaiInvitation($agent, 'invited@example.com'),
            name: 'Different Person',
            kind: InvitationCredentialKind::Social,
            socialEmail: 'attacker@evil.example',
            socialProvider: SocialProvider::Google,
            socialProviderId: 'goog-999',
        ), fn () => expect($chatter->fresh()->email_verified_at)->toBeNull()];
    }],
    'a replay by an already-verified chatter' => [function (): array {
        [$agent, $chatter] = aaiAgentAndChatter('invited@example.com');
        $chatter->update(['email_verified_at' => now(), 'password' => Hash::make('original-pass')]);

        return [aaiPasswordData(aaiInvitation($agent, 'invited@example.com'), 'new-password-xxxx'), fn () => expect(Hash::check('original-pass', $chatter->fresh()->password))->toBeTrue()];
    }],
    'only a chatter of another workspace has the email' => [function (): array {
        [$agent] = aaiAgentAndChatter('invited@example.com');
        $foreign = Chatter::factory()->ofWorkspace(User::factory()->create())->create([
            'email' => 'foreign@example.com',
            'email_verified_at' => null,
            'password' => null,
        ]);

        return [aaiPasswordData(aaiInvitation($agent, 'foreign@example.com')), fn () => expect($foreign->fresh()->email_verified_at)->toBeNull()
            ->and($foreign->fresh()->password)->toBeNull()];
    }],
    'an expired invitation' => [function (): array {
        [$agent, $chatter] = aaiAgentAndChatter('invited@example.com');

        return [aaiPasswordData(aaiInvitation($agent, expiresAt: now()->subMinute())), fn () => expect($chatter->fresh()->email_verified_at)->toBeNull()];
    }],
    'an invitation already used, from a stale copy' => [function (): array {
        [$agent, $chatter] = aaiAgentAndChatter('invited@example.com');
        $invitation = aaiInvitation($agent);
        $staleCopy = clone $invitation;
        $invitation->delete();

        return [aaiPasswordData($staleCopy), fn () => expect($chatter->fresh()->email_verified_at)->toBeNull()];
    }],
    'a grant revoked after the invitation, which is kept' => [function (): array {
        [$agent, $chatter] = aaiAgentAndChatter('invited@example.com');
        $invitation = aaiInvitation($agent);
        $agent->chatters()->detach($chatter->id);

        return [aaiPasswordData($invitation), fn () => expect(AgentChatterInvitation::query()->whereKey($invitation->id)->exists())->toBeTrue()
            ->and($chatter->fresh()->email_verified_at)->toBeNull()];
    }],
]);
