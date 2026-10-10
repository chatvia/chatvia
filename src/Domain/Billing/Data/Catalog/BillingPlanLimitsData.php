<?php

declare(strict_types=1);

namespace Domain\Billing\Data\Catalog;

use Domain\Billing\Models\BillingPlan;
use Spatie\LaravelData\Data;

/**
 * The limits of a plan as the admin panel edits them: messages per billing
 * cycle, and the fixed limits that do not depend on the cycle. Converts to
 * and from the `quota_limits` column of {@see BillingPlan}, whose `fixed`
 * keys are the `user_quotas` columns.
 */
class BillingPlanLimitsData extends Data
{
    public function __construct(
        public int $monthlyMessages = 0,
        public int $yearlyMessages = 0,
        public int $agents = 0,
        public int $pages = 0,
        public int $minutes = 0,
        public int $links = 0,
        public int $customDomains = 0,
        public int $workspaceUsers = 0,
        public int $teams = 0,
    ) {}

    /**
     * @param  array<string, array<string, int>>  $quotaLimits  a plan's `quota_limits`
     */
    public static function fromQuotaLimits(array $quotaLimits): self
    {
        $fixed = $quotaLimits['fixed'] ?? [];

        return new self(
            monthlyMessages: (int) ($quotaLimits['monthly']['messages_limit'] ?? 0),
            yearlyMessages: (int) ($quotaLimits['yearly']['messages_limit'] ?? 0),
            agents: (int) ($fixed['agents_limit'] ?? 0),
            pages: (int) ($fixed['pages_limit'] ?? 0),
            minutes: (int) ($fixed['minutes_limit'] ?? 0),
            links: (int) ($fixed['links_limit'] ?? 0),
            customDomains: (int) ($fixed['custom_domains_limit'] ?? 0),
            workspaceUsers: (int) ($fixed['workspace_users_limit'] ?? 0),
            teams: (int) ($fixed['teams_limit'] ?? 0),
        );
    }

    /**
     * The limits in the shape of the `quota_limits` column.
     *
     * @return array{monthly: array{messages_limit: int}, yearly: array{messages_limit: int}, fixed: array<string, int>}
     */
    public function toQuotaLimits(): array
    {
        return [
            'monthly' => ['messages_limit' => $this->monthlyMessages],
            'yearly' => ['messages_limit' => $this->yearlyMessages],
            'fixed' => [
                'agents_limit' => $this->agents,
                'pages_limit' => $this->pages,
                'minutes_limit' => $this->minutes,
                'links_limit' => $this->links,
                'custom_domains_limit' => $this->customDomains,
                'workspace_users_limit' => $this->workspaceUsers,
                'teams_limit' => $this->teams,
            ],
        ];
    }
}
