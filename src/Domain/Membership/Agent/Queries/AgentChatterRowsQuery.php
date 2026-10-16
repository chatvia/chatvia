<?php

declare(strict_types=1);

namespace Domain\Membership\Agent\Queries;

use App\Identity\Chatter\Models\Chatter;
use Domain\Agent\Models\Agent;
use Domain\Membership\Agent\Data\Resources\AgentChatterRowData;
use Domain\Membership\Agent\Models\AgentChatterInvitation;
use Spatie\LaravelData\DataCollection;

/**
 * The rows of an agent's Members tab: every chatter the workspace owns —
 * not just the ones already granted to this agent — with a `has_access` flag,
 * rows already on the pivot first. A pending member's live sign-up link rides
 * along so the owner can copy it from the member sheet.
 */
class AgentChatterRowsQuery
{
    /**
     * @return DataCollection<int, AgentChatterRowData>
     */
    public function forAgent(Agent $agent): DataCollection
    {
        $agent->loadMissing('workspace');

        // All chatters owned by the workspace, alphabetised. We don't track
        // when the pivot row was attached — no timestamps on pivot tables —
        // so the rows just carry a boolean `has_access` for this agent.
        $chatters = $agent->workspace->chatters()->orderBy('name')->get();
        $grantedSet = array_flip($agent->chatters()->pluck('chatters.id')->all());

        // Outstanding invitations for this agent, keyed by lower-cased email.
        $invitations = AgentChatterInvitation::query()
            ->where('agent_id', $agent->id)
            ->get()
            ->keyBy(fn (AgentChatterInvitation $invitation): string => strtolower($invitation->email));

        $rows = $chatters
            ->map(function (Chatter $chatter) use ($grantedSet, $invitations): AgentChatterRowData {
                $invitationUrl = null;
                $invitationExpiresAt = null;

                if ($chatter->email_verified_at === null && $chatter->email !== null) {
                    $invitation = $invitations->get(strtolower($chatter->email));

                    if ($invitation !== null && ! $invitation->hasExpired()) {
                        $invitationUrl = route('agents.invitation.show', ['invitation' => $invitation->token]);
                        $invitationExpiresAt = $invitation->expires_at->toIso8601String();
                    }
                }

                return AgentChatterRowData::fromModelAndGrant(
                    $chatter,
                    isset($grantedSet[$chatter->id]),
                    $invitationUrl,
                    $invitationExpiresAt,
                );
            })
            ->sortBy(fn (AgentChatterRowData $row): array => [$row->has_access ? 0 : 1, strtolower($row->name)])
            ->values();

        return AgentChatterRowData::collect($rows, DataCollection::class);
    }
}
