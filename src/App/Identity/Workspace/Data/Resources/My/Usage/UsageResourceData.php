<?php

namespace App\Identity\Workspace\Data\Resources\My\Usage;

use App\Identity\Workspace\Models\User;
use Domain\Billing\Models\UserCredit;
use Domain\Billing\Models\UserQuota;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class UsageResourceData extends Data
{
    public function __construct(
        public UsageSubscriptionData $subscription,
        public UsageStatsData $agents,
        public UsageStatsData $pages,
        public UsageStatsData $minutes,
        public UsageStatsData $links,
        public UsageStatsData $messages,
        public UsageStatsData $tokens,
        public UsageStatsData $teams,
        public UsageStatsData $members,
    ) {}

    public static function fromUser(User $user): self
    {
        /** @var UserCredit $credit */
        $credit = $user->credit;

        $totalSentMessage = $user->creditSystem()->getTotalSentMessages();

        $totalAgents = $user->agents()->count();

        /** @var UserQuota $quota */
        $quota = $user->quota;

        $agentsLimit = $quota->totalAgentsLimit();

        $consumableMessages = $user->consumableMessages();

        // Tokens are ANALYTICS only (billing is by messages): the real count
        // consumed this cycle, read off the usage row. No cap.
        $tokensUsed = (int) ($credit?->usage?->tokens ?? 0);

        $consumption = $user->consumption();
        $pagesUsed = $consumption->pages();
        $minutesUsed = $consumption->minutes();
        $linksUsed = $consumption->links();
        $teamsUsed = $user->teamsCreatedCount();
        $workspaceUsersUsed = $user->workspaceUsersCount();

        return new self(
            subscription: new UsageSubscriptionData(plan: $user->plan, started_at: $credit->started_at, ends_at: $credit->ends_at),
            agents: new UsageStatsData(limit: $agentsLimit, used: $totalAgents, remaining: max(0, $agentsLimit - $totalAgents)),
            pages: new UsageStatsData(limit: $quota->pages_limit, used: $pagesUsed, remaining: max(0, $quota->pages_limit - $pagesUsed)),
            minutes: new UsageStatsData(limit: $quota->minutes_limit, used: $minutesUsed, remaining: max(0, $quota->minutes_limit - $minutesUsed)),
            links: new UsageStatsData(limit: $quota->links_limit, used: $linksUsed, remaining: max(0, $quota->links_limit - $linksUsed)),
            messages: new UsageStatsData(limit: $consumableMessages, used: $totalSentMessage, remaining: max(0, $consumableMessages - $totalSentMessage)),
            tokens: new UsageStatsData(limit: 0, used: $tokensUsed, remaining: 0),
            teams: new UsageStatsData(limit: $quota->teams_limit, used: $teamsUsed, remaining: max(0, $quota->teams_limit - $teamsUsed)),
            members: new UsageStatsData(limit: $quota->totalWorkspaceUsersLimit(), used: $workspaceUsersUsed, remaining: max(0, $quota->totalWorkspaceUsersLimit() - $workspaceUsersUsed)),
        );
    }
}
