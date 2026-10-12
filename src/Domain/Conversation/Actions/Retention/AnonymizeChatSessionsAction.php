<?php

declare(strict_types=1);

namespace Domain\Conversation\Actions\Retention;

use Domain\Security\Models\WorkspaceSecurityPolicy;
use Illuminate\Support\Facades\DB;

/**
 * Clears the visitor details of chat sessions older than the "anonymize
 * after" window a workspace chose in its security policy: contact details,
 * IP address, browser string, location and the pages the visitor came from.
 * The session is stamped `anonymized_at`, so it is cleared only once.
 *
 * What analytics needs stays: the agent, the country, the device type,
 * browser and OS, the locale. Messages, leads and the signed-in chatter's
 * account are not touched.
 *
 * Sessions go in batches, so one run never holds a huge update. The update
 * skips `updated_at`: clearing details is not visitor activity.
 */
class AnonymizeChatSessionsAction
{
    /**
     * The columns that identify a visitor, set to null.
     *
     * @var list<string>
     */
    public const array CLEARED_COLUMNS = [
        'name', 'email', 'phone',
        'ip_address', 'is_proxy', 'user_agent',
        'city', 'region', 'zip_code', 'time_zone',
        'referrer_url', 'page_url',
    ];

    /**
     * @return int how many sessions were anonymized
     */
    public function execute(int $batchSize = 5000): int
    {
        // Never below the floor, even for a value saved before it was raised.
        $floor = (int) config('chatvia.retention.anonymize_floor_days', 1);
        $batchSize = max(1, $batchSize);

        $windows = WorkspaceSecurityPolicy::query()
            ->whereNotNull('anonymize_after_days')
            ->pluck('anonymize_after_days', 'workspace_id');

        $cleared = array_fill_keys(self::CLEARED_COLUMNS, null);
        $anonymized = 0;

        foreach ($windows as $workspaceId => $days) {
            $cutoff = now()->subDays(max($floor, (int) $days));

            do {
                // Served by the partial index chat_sessions_to_anonymize_idx.
                $batch = DB::table('chat_sessions')
                    ->whereIn('id', fn ($query) => $query->select('id')
                        ->from('chat_sessions')
                        ->where('workspace_id', $workspaceId)
                        ->whereNull('anonymized_at')
                        ->where('created_at', '<', $cutoff)
                        ->limit($batchSize))
                    ->update([...$cleared, 'anonymized_at' => now()]);

                $anonymized += $batch;
            } while ($batch > 0);
        }

        return $anonymized;
    }
}
