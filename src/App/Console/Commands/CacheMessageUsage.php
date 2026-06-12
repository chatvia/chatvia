<?php

namespace App\Console\Commands;

use App\Identity\Workspace\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Support\Enums\User\UserType;

#[Description('Refresh the cached can-send-messages flag and sent-messages counter for workspaces with an active credit cycle.')]
#[Signature('app:cache-message-usage')]
class CacheMessageUsage extends Command
{
    public function handle(): void
    {
        // Workspace accounts with a live credit cycle only — this run covers cycle rollover.
        User::query()
            ->whereIn('type', UserType::workspaceTypes())
            ->whereExists(function ($query): void {
                $query->select(DB::raw(1))
                    ->from('user_credits')
                    ->whereColumn('user_credits.user_id', 'users.id')
                    ->where('user_credits.ends_at', '>=', now());
            })
            ->chunkById(100, function ($users): void {
                /** @var User $user */
                foreach ($users as $user) {
                    $creditSystem = $user->creditSystem();
                    $creditSystem->refreshCanSendMessages();
                    $creditSystem->refreshSentMessages();
                }
            });
    }
}
