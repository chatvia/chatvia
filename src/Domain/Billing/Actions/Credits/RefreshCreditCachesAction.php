<?php

namespace Domain\Billing\Actions\Credits;

use App\Identity\Workspace\Enums\UserType;
use App\Identity\Workspace\Models\User;
use Domain\Billing\Services\UserCreditSystem;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Recomputes the cached send flag and sent-messages counter of every workspace
 * with a running credit cycle. Run on a schedule, so a cycle that rolled over
 * (or a cache entry that expired) is corrected without waiting for the next
 * message. Returns how many workspaces were refreshed.
 */
class RefreshCreditCachesAction
{
    public function __construct(
        private readonly UserCreditSystem $creditSystem,
    ) {}

    public function execute(): int
    {
        $refreshed = 0;

        User::query()
            ->whereIn('type', UserType::workspaceTypes())
            ->whereExists(fn ($query) => $query->select(DB::raw(1))
                ->from('user_credits')
                ->whereColumn('user_credits.user_id', 'users.id')
                ->where('user_credits.ends_at', '>=', now()))
            ->chunkById(100, function (Collection $workspaces) use (&$refreshed): void {
                foreach ($workspaces as $workspace) {
                    $credits = $this->creditSystem->forWorkspace($workspace->id);
                    $credits->refreshCanSendMessages();
                    $credits->refreshSentMessages();
                    $refreshed++;
                }
            });

        return $refreshed;
    }
}
