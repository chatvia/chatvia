<?php

use App\Identity\Chatter\Enums\ChatterSource;
use App\Identity\Chatter\Models\Chatter;
use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Membership\Agent\Actions\InviteAgentChatterAction;
use Domain\Membership\Agent\Data\AgentChatterInvitationData;
use Domain\Membership\Agent\Data\InviteAgentChatterData;
use Domain\Membership\Agent\Events\AgentChatterInvited;
use Domain\Membership\Agent\Exceptions\AgentChatterInvitationRejectedException;
use Domain\Membership\Agent\Models\AgentChatterInvitation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;

/**
 * InviteAgentChatterAction is the FIRST line of defence in the agent-invitation
 * flow (AcceptAgentInvitationAction is the second). It only ever looks inside
 * the agent's own workspace: the same email in another workspace, or on a
 * staff account, is a different person, gets its own chatter row, and the
 * answer is the same as for a brand-new email, so it never reveals that the
 * email exists elsewhere. Its idempotency stops forwarded-link replay.
 */
function inviteWorkspaceAndAgent(): array
{
    $workspace = User::factory()->create();

    $agent = Agent::factory()->create(['workspace_id' => $workspace->workspace()->id, 'is_public' => false]);

    return [$workspace, $agent];
}

function invite(Agent $agent, string $email): AgentChatterInvitationData
{
    return app(InviteAgentChatterAction::class)->execute(new InviteAgentChatterData(
        agent: $agent,
        email: $email,
    ));
}

/** The chatters of the workspace with the email, compared without case. */
function workspaceChattersWithEmail(User $workspace, string $email): Builder
{
    return Chatter::query()
        ->where('workspace_id', $workspace->workspace()->id)
        ->whereRaw('lower(email) = ?', [strtolower($email)]);
}

it('provisions a new chatter, attaches the agent pivot, and dispatches AgentChatterInvited', function (): void {
    Event::fake([AgentChatterInvited::class]);
    [$workspace, $agent] = inviteWorkspaceAndAgent();

    $result = invite($agent, 'new@example.com');

    $chatter = workspaceChattersWithEmail($workspace, 'new@example.com')->first();

    expect($chatter)->toBeInstanceOf(Chatter::class)
        ->and($chatter->workspace_id)->toBe($workspace->workspace()->id)
        ->and($chatter->source)->toBe(ChatterSource::Dashboard)
        ->and($chatter->email_verified_at)->toBeNull()
        ->and($chatter->password)->toBeNull()
        ->and($agent->chatters()->whereKey($chatter->id)->exists())->toBeTrue()
        ->and(AgentChatterInvitation::query()->where('agent_id', $agent->id)->where('email', 'new@example.com')->count())->toBe(1)
        ->and($result->email)->toBe('new@example.com');

    Event::assertDispatched(AgentChatterInvited::class);
});

it('stores the invited email in lower case', function (): void {
    Event::fake([AgentChatterInvited::class]);
    [$workspace, $agent] = inviteWorkspaceAndAgent();

    $result = invite($agent, '  New.Person@Example.COM ');

    expect($result->email)->toBe('new.person@example.com')
        ->and(workspaceChattersWithEmail($workspace, 'new.person@example.com')->value('email'))->toBe('new.person@example.com');
});

it('rejects inviting to a public agent', function (): void {
    [$workspace, $agent] = inviteWorkspaceAndAgent();
    $agent->update(['is_public' => true]);

    expect(fn (): AgentChatterInvitationData => invite($agent, 'new@example.com'))
        ->toThrow(AgentChatterInvitationRejectedException::class);

    expect(workspaceChattersWithEmail($workspace, 'new@example.com')->exists())->toBeFalse();
});

it('invites the email of a staff account as a separate chatter and leaves the staff account alone', function (): void {
    Event::fake([AgentChatterInvited::class]);
    [$workspace, $agent] = inviteWorkspaceAndAgent();
    $staff = User::factory()->employee($workspace)->create(['email' => 'boss@example.com']);
    $staffPassword = $staff->password;

    $result = invite($agent, 'boss@example.com');

    $chatter = workspaceChattersWithEmail($workspace, 'boss@example.com')->first();

    expect($result->email)->toBe('boss@example.com')
        ->and($chatter)->toBeInstanceOf(Chatter::class)
        ->and($agent->chatters()->whereKey($chatter->id)->exists())->toBeTrue();

    $staff->refresh();
    expect($staff->isTeammate())->toBeTrue()
        ->and($staff->password)->toBe($staffPassword);

    Event::assertDispatched(AgentChatterInvited::class);
});

it('invites an email used by a chatter of another workspace as a new, separate chatter', function (): void {
    Event::fake([AgentChatterInvited::class]);
    [$workspace, $agent] = inviteWorkspaceAndAgent();

    $otherWorkspace = User::factory()->create();
    $foreign = Chatter::factory()->ofWorkspace($otherWorkspace)->create([
        'email' => 'foreign@example.com',
        'password' => Hash::make('foreign-pass'),
    ]);

    $result = invite($agent, 'foreign@example.com');

    $chatter = workspaceChattersWithEmail($workspace, 'foreign@example.com')->first();

    expect($result->email)->toBe('foreign@example.com')
        ->and($chatter)->toBeInstanceOf(Chatter::class)
        ->and($chatter->id)->not->toBe($foreign->id)
        ->and($chatter->email_verified_at)->toBeNull()
        ->and($chatter->password)->toBeNull()
        ->and($agent->chatters()->whereKey($chatter->id)->exists())->toBeTrue()
        // The other workspace's chatter is untouched and gets no grant here.
        ->and($agent->chatters()->whereKey($foreign->id)->exists())->toBeFalse()
        ->and(Hash::check('foreign-pass', $foreign->fresh()->password))->toBeTrue()
        ->and($foreign->fresh()->workspace_id)->toBe($otherWorkspace->workspace()->id);
});

it('answers the same way whether or not the email exists elsewhere', function (): void {
    Event::fake([AgentChatterInvited::class]);
    [, $agent] = inviteWorkspaceAndAgent();
    Chatter::factory()->create(['email' => 'known@example.com']);
    User::factory()->workspace()->create(['email' => 'known-staff@example.com']);

    $fresh = invite($agent, 'unknown@example.com');
    $knownChatter = invite($agent, 'known@example.com');
    $knownStaff = invite($agent, 'known-staff@example.com');

    // Same shape, no error, nothing hinting at the other accounts.
    foreach ([$fresh, $knownChatter, $knownStaff] as $result) {
        expect($result)->toBeInstanceOf(AgentChatterInvitationData::class)
            ->and(array_keys($result->toArray()))->toBe(array_keys($fresh->toArray()));
    }

    Event::assertDispatchedTimes(AgentChatterInvited::class, 3);
});

it('is idempotent: re-inviting the same email rotates the token and keeps one pending row + one chatter', function (): void {
    Event::fake([AgentChatterInvited::class]);
    [$workspace, $agent] = inviteWorkspaceAndAgent();

    invite($agent, 'repeat@example.com');
    $firstToken = AgentChatterInvitation::query()->where('agent_id', $agent->id)->where('email', 'repeat@example.com')->value('token');

    invite($agent, 'Repeat@Example.com');

    $rows = AgentChatterInvitation::query()->where('agent_id', $agent->id)->where('email', 'repeat@example.com');

    expect($rows->count())->toBe(1)
        ->and($rows->value('token'))->not->toBe($firstToken)
        ->and(workspaceChattersWithEmail($workspace, 'repeat@example.com')->count())->toBe(1);
});

it('reuses an existing chatter already under the same workspace, without case', function (): void {
    Event::fake([AgentChatterInvited::class]);
    [$workspace, $agent] = inviteWorkspaceAndAgent();

    $existing = Chatter::factory()->ofWorkspace($workspace)->create(['email' => 'existing@example.com']);

    invite($agent, 'EXISTING@example.com');

    expect(workspaceChattersWithEmail($workspace, 'existing@example.com')->count())->toBe(1)
        ->and($agent->chatters()->whereKey($existing->id)->exists())->toBeTrue();
});
