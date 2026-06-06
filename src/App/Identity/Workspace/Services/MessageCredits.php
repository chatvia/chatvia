<?php

namespace App\Identity\Workspace\Services;

use App\Identity\Workspace\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Workspace-scoped message-credit metering: cycle usage, base quota, addons,
 * and the available/limit math. Extracted from {@see GetDashboardStatsAction}
 * where the same calculation was inlined twice (the dashboard "Messages This
 * Cycle" panel AND the usage stats list).
 *
 * Semantics preserved verbatim from the in-place implementation:
 *  - `used`: assistant messages logged to the analytics rollup since the active
 *     UserCredit's cycle start. 0 when no credit row exists.
 *  - `addons`: a SEPARATE pool that survives base-quota overrun — matches the
 *     long-standing UserCreditSystem::calculateAvailableMessages behaviour where
 *     extra-message-credit addons stay intact even when base usage > base quota.
 *  - `available = max(0, base - used) + addons` (base clamps; addons add on top).
 *  - `limit    = base + addons`.
 *
 * Routed through User::workspace() so a workspace employee reading the
 * dashboard sees the workspace owner's billing — mirrors the existing
 * `$owner = $user->workspace()` line that callers already write.
 */
readonly class MessageCredits
{
    private User $workspace;

    public function __construct(User $user)
    {
        $this->workspace = $user->workspace();
    }

    /** Assistant messages billed against this cycle. 0 when no UserCredit row. */
    public function used(): int
    {
        $credit = $this->workspace->credit;

        if (! $credit) {
            return 0;
        }

        return (int) $this->rollup()
            ->where('workspace_id', $this->workspace->id)
            ->where('bucket_hour', '>=', $credit->started_at)
            ->sum('assistant_messages');
    }

    /** Base messages quota from the active UserCredit row; 0 when none. */
    public function baseLimit(): int
    {
        return (int) ($this->workspace->credit?->messages ?? 0);
    }

    /** Sum of unexpired `extra_message_credits` addons. */
    public function addons(): int
    {
        return (int) $this->workspace->addons()
            ->where('type', 'extra_message_credits')
            ->where('ends_at', '>=', now())
            ->sum('amount');
    }

    /** Available messages = remaining base (clamped at 0) + addons (separate pool). */
    public function available(): int
    {
        return max(0, $this->baseLimit() - $this->used()) + $this->addons();
    }

    /** Total messages the user paid for this cycle: base quota + addons. */
    public function limit(): int
    {
        return $this->baseLimit() + $this->addons();
    }

    /**
     * `{started_at, ends_at}` for the active cycle, or null when no UserCredit.
     *
     * @return array{started_at: Carbon, ends_at: Carbon}|null
     */
    public function cycleBounds(): ?array
    {
        $credit = $this->workspace->credit;

        if (! $credit) {
            return null;
        }

        return [
            'started_at' => $credit->started_at,
            'ends_at' => $credit->ends_at,
        ];
    }

    /**
     * Single entry point for the analytics rollup query builder. Pinned to
     * the configured analytics connection so this reads from the isolated DB
     * when one's set up.
     */
    private function rollup(): Builder
    {
        return DB::connection(config('chatvia.analytics.connection'))
            ->table('analytics_agent_hourly_rollups');
    }
}
