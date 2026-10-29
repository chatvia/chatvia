<?php

declare(strict_types=1);

namespace App\Identity\Workspace\Services;

use App\Identity\Workspace\Models\GenerationLimit;
use Carbon\CarbonInterface;
use Domain\Conversation\Enums\GeneratedMediaKind;
use Support\Radar\IssueRaised;
use Support\Radar\IssueTypeKey;
use Support\Radar\ServiceKey;

/**
 * Counts what a workspace's agents make against its monthly caps
 * ({@see GenerationLimit}): pictures and audio files against one, videos
 * against their own. A slot is taken when a file is asked for
 * ({@see reserve()}) and given back when making it fails ({@see release()}),
 * so a failed file costs the workspace nothing. A refusal tells Radar, as
 * only an admin can raise the cap.
 *
 * Each step is one UPDATE that also restarts both counts when a new month
 * has begun: the database decides from the stored period, so two requests
 * at once never pass the cap together or lose a count. A workspace without
 * a cap for the kind always may.
 */
class GenerationAllowance
{
    /** Takes one file of the workspace's month; false when the kind's cap is reached. */
    public function reserve(int $workspaceId, GeneratedMediaKind $kind): bool
    {
        $now = now();
        $monthStart = $now->copy()->startOfMonth();
        [$used, $cap, $otherUsed] = $this->columns($kind);

        $taken = GenerationLimit::query()->getConnection()->update(<<<SQL
            UPDATE generation_limits SET
                {$used} = CASE WHEN period_starts_at >= ? THEN {$used} + 1 ELSE 1 END,
                {$otherUsed} = CASE WHEN period_starts_at >= ? THEN {$otherUsed} ELSE 0 END,
                period_starts_at = GREATEST(period_starts_at, ?),
                updated_at = ?
            WHERE workspace_id = ?
                AND ({$cap} IS NULL OR CASE WHEN period_starts_at >= ? THEN {$used} ELSE 0 END < {$cap})
        SQL, [$monthStart, $monthStart, $monthStart, $now, $workspaceId, $monthStart]);

        if ($taken > 0 || ! GenerationLimit::query()->where('workspace_id', $workspaceId)->exists()) {
            return true;
        }

        $files = $kind === GeneratedMediaKind::Video ? 'videos' : 'pictures and audio files';
        IssueRaised::dispatch(ServiceKey::WORKSPACES, IssueTypeKey::GENERATION_LIMIT_REACHED, "The monthly limit on {$files} is used up.", subject: (string) $workspaceId);

        return false;
    }

    /**
     * Gives back the file reserved at $reservedAt, when making it failed. A
     * file reserved in an earlier month is not given back: that month's count
     * is gone.
     */
    public function release(int $workspaceId, GeneratedMediaKind $kind, CarbonInterface $reservedAt): void
    {
        $monthStart = now()->startOfMonth();

        if ($reservedAt->lessThan($monthStart)) {
            return;
        }

        [$used] = $this->columns($kind);

        GenerationLimit::query()
            ->where('workspace_id', $workspaceId)
            ->where('period_starts_at', '>=', $monthStart)
            ->where($used, '>', 0)
            ->update([
                $used => GenerationLimit::query()->getConnection()->raw("{$used} - 1"),
                'updated_at' => now(),
            ]);
    }

    public function limitFor(int $workspaceId): ?GenerationLimit
    {
        return GenerationLimit::query()->where('workspace_id', $workspaceId)->first();
    }

    /**
     * The kind's count and cap, and the other count (reset with it in a new month).
     *
     * @return array{string, string, string}
     */
    private function columns(GeneratedMediaKind $kind): array
    {
        return $kind === GeneratedMediaKind::Video
            ? ['videos_used', 'monthly_video_limit', 'generations_used']
            : ['generations_used', 'monthly_limit', 'videos_used'];
    }
}
