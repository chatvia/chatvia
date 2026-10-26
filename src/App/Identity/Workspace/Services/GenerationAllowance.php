<?php

declare(strict_types=1);

namespace App\Identity\Workspace\Services;

use App\Identity\Workspace\Models\GenerationLimit;
use Carbon\CarbonInterface;

/**
 * Counts the pictures and audio files a workspace's agents make against its
 * monthly cap ({@see GenerationLimit}). A slot is taken when a file is asked
 * for ({@see reserve()}) and given back when making it fails
 * ({@see release()}), so a failed file costs the workspace nothing.
 *
 * Each step is one UPDATE that also restarts the count when a new month has
 * begun: the database decides from the stored period, so two requests at
 * once never pass the cap together or lose a count. A workspace without a
 * cap always may.
 */
class GenerationAllowance
{
    /** Takes one file of the workspace's month; false when its cap is reached. */
    public function reserve(int $workspaceId): bool
    {
        $now = now();
        $monthStart = $now->copy()->startOfMonth();

        $taken = GenerationLimit::query()->getConnection()->update(<<<'SQL'
            UPDATE generation_limits SET
                generations_used = CASE WHEN period_starts_at >= ? THEN generations_used + 1 ELSE 1 END,
                period_starts_at = GREATEST(period_starts_at, ?),
                updated_at = ?
            WHERE workspace_id = ?
                AND CASE WHEN period_starts_at >= ? THEN generations_used ELSE 0 END < monthly_limit
        SQL, [$monthStart, $monthStart, $now, $workspaceId, $monthStart]);

        return $taken > 0 || ! GenerationLimit::query()->where('workspace_id', $workspaceId)->exists();
    }

    /**
     * Gives back the file reserved at $reservedAt, when making it failed. A
     * file reserved in an earlier month is not given back: that month's count
     * is gone.
     */
    public function release(int $workspaceId, CarbonInterface $reservedAt): void
    {
        $monthStart = now()->startOfMonth();

        if ($reservedAt->lessThan($monthStart)) {
            return;
        }

        GenerationLimit::query()
            ->where('workspace_id', $workspaceId)
            ->where('period_starts_at', '>=', $monthStart)
            ->where('generations_used', '>', 0)
            ->update([
                'generations_used' => GenerationLimit::query()->getConnection()->raw('generations_used - 1'),
                'updated_at' => now(),
            ]);
    }

    public function limitFor(int $workspaceId): ?GenerationLimit
    {
        return GenerationLimit::query()->where('workspace_id', $workspaceId)->first();
    }
}
