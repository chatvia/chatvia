<?php

namespace App\Identity\Workspace\Data\Resources\My\Usage;

use App\Identity\Workspace\Models\User;
use Domain\Billing\Contracts\Entitlements;
use Domain\Billing\Models\UserCredit;
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
        // True when billing is off: there are no limits, so the UI shows what
        // is used without a cap, and hides the message credit figures.
        public bool $unlimited = false,
    ) {}

    public static function fromUser(User $user): self
    {
        /** @var UserCredit|null $credit */
        $credit = $user->credit;

        $totalSentMessage = $user->creditSystem()->getTotalSentMessages();

        $totalAgents = $user->agents()->count();

        $agentsLimit = $user->totalAgentsLimit();
        $pagesLimit = $user->quotaLimit('pages_limit');
        $minutesLimit = $user->quotaLimit('minutes_limit');
        $linksLimit = $user->quotaLimit('links_limit');
        $teamsLimit = $user->quotaLimit('teams_limit');
        $membersLimit = $user->totalWorkspaceUsersLimit();

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
            subscription: new UsageSubscriptionData(plan: $user->plan, started_at: $credit?->started_at, ends_at: $credit?->ends_at),
            agents: new UsageStatsData(limit: $agentsLimit, used: $totalAgents, remaining: max(0, $agentsLimit - $totalAgents)),
            pages: new UsageStatsData(limit: $pagesLimit, used: $pagesUsed, remaining: max(0, $pagesLimit - $pagesUsed)),
            minutes: new UsageStatsData(limit: $minutesLimit, used: $minutesUsed, remaining: max(0, $minutesLimit - $minutesUsed)),
            links: new UsageStatsData(limit: $linksLimit, used: $linksUsed, remaining: max(0, $linksLimit - $linksUsed)),
            messages: new UsageStatsData(limit: $consumableMessages, used: $totalSentMessage, remaining: max(0, $consumableMessages - $totalSentMessage)),
            tokens: new UsageStatsData(limit: 0, used: $tokensUsed, remaining: 0),
            teams: new UsageStatsData(limit: $teamsLimit, used: $teamsUsed, remaining: max(0, $teamsLimit - $teamsUsed)),
            members: new UsageStatsData(limit: $membersLimit, used: $workspaceUsersUsed, remaining: max(0, $membersLimit - $workspaceUsersUsed)),
            unlimited: ! app(Entitlements::class)->enforced(),
        );
    }
}
