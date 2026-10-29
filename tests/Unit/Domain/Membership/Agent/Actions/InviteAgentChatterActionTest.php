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
use Illuminate\Support\Str;

/**
 * InviteAgentChatterAction is the FIRST line of defence in the agent-invitation
 * flow (AcceptAgentInvitationAction is the second). It only ever looks inside
 * the agent's own workspace: the same email in another workspace, or on a
 * staff account, is a different person, gets its own chatter row, and the
 * answer is the same as for a brand-new email, so it never reveals that the
 * email exists elsewhere. Its idempotency stops forwarded-link replay.
 */
function iacWorkspaceAndAgent(): array
{
    $workspace = User::factory()->create();

    $agent = Agent::factory()->create(['workspace_id' => $workspace->workspace()->id, 'is_public' => false]);

    return [$workspace, $agent];
}

function iacInvite(Agent $agent, string $email): AgentChatterInvitationData
{
    return app(InviteAgentChatterAction::class)->execute(new InviteAgentChatterData(
        agent: $agent,
        email: $email,
    ));
}

/** The chatters of the workspace with the email, compared without case. */
function iacWorkspaceChatters(User $workspace, string $email): Builder
{
    return Chatter::query()
        ->where('workspace_id', $workspace->workspace()->id)
        ->whereRaw('lower(email) = ?', [Str::lower($email)]);
}

it('provisions or reuses one pending chatter per lower-cased email and rotates the token on a re-invite', function (): void {
    Event::fake([AgentChatterInvited::class]);
    [$workspace, $agent] = iacWorkspaceAndAgent();
    $existing = Chatter::factory()->ofWorkspace($workspace)->create(['email' => 'existing@example.com']);

    $result = iacInvite($agent, '  New.Person@Example.COM ');
    $firstToken = AgentChatterInvitation::query()->where('agent_id', $agent->id)->where('email', 'new.person@example.com')->value('token');
    iacInvite($agent, 'New.Person@example.com');
    iacInvite($agent, 'EXISTING@example.com');

    $chatter = iacWorkspaceChatters($workspace, 'new.person@example.com')->sole();
    $rows = AgentChatterInvitation::query()->where('agent_id', $agent->id)->where('email', 'new.person@example.com');

    expect($result->email)->toBe('new.person@example.com')
        ->and($chatter->email)->toBe('new.person@example.com')
        ->and($chatter->source)->toBe(ChatterSource::Dashboard)
        ->and($chatter->email_verified_at)->toBeNull()
        ->and($chatter->password)->toBeNull()
        ->and($agent->chatters()->whereKey($chatter->id)->exists())->toBeTrue()
        ->and($rows->count())->toBe(1)
        ->and($rows->value('token'))->not->toBe($firstToken)
        ->and(iacWorkspaceChatters($workspace, 'existing@example.com')->count())->toBe(1)
        ->and($agent->chatters()->whereKey($existing->id)->exists())->toBeTrue();
    Event::assertDispatched(AgentChatterInvited::class);
});

it('rejects inviting to a public agent', function (): void {
    [$workspace, $agent] = iacWorkspaceAndAgent();
    $agent->update(['is_public' => true]);

    expect(fn (): AgentChatterInvitationData => iacInvite($agent, 'new@example.com'))
        ->toThrow(AgentChatterInvitationRejectedException::class);

    expect(iacWorkspaceChatters($workspace, 'new@example.com')->exists())->toBeFalse();
});

/** An account with the email outside this workspace's chatters. */
function iacAccountElsewhere(string $kind, User $workspace, string $email): Chatter|User
{
    return match ($kind) {
        'chatter' => Chatter::factory()->create(['email' => $email, 'password' => Hash::make('foreign-pass')]),
        'teammate' => User::factory()->employee($workspace)->create(['email' => $email]),
        'staff' => User::factory()->workspace()->create(['email' => $email]),
    };
}

it('invites an email known elsewhere as a new, pending chatter of this workspace and leaves that account alone', function (string $kind): void {
    Event::fake([AgentChatterInvited::class]);
    [$workspace, $agent] = iacWorkspaceAndAgent();
    $account = iacAccountElsewhere($kind, $workspace, 'known@example.com');
    $before = $account->fresh()->getAttributes();

    $result = iacInvite($agent, 'known@example.com');

    $chatter = iacWorkspaceChatters($workspace, 'known@example.com')->sole();

    expect($result->email)->toBe('known@example.com')
        ->and($chatter->is($account))->toBeFalse()
        ->and($chatter->password)->toBeNull()
        ->and($chatter->email_verified_at)->toBeNull()
        ->and($agent->chatters()->whereKey($chatter->id)->exists())->toBeTrue()
        ->and($account->fresh()->getAttributes())->toBe($before);

    Event::assertDispatched(AgentChatterInvited::class);
})->with([
    'a chatter of another workspace' => 'chatter',
    'a teammate of this workspace' => 'teammate',
    'the owner of another workspace' => 'staff',
]);
