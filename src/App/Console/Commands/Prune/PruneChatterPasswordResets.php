<?php

declare(strict_types=1);

namespace App\Console\Commands\Prune;

use App\Identity\Chatter\Actions\PruneExpiredChatterPasswordResetTokensAction;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Scheduled clean-up of expired chatter password reset tokens, the chatter
 * side of `auth:clear-resets` (see
 * {@see PruneExpiredChatterPasswordResetTokensAction}).
 */
#[Description('Delete expired chatter password reset tokens (chatter_password_reset_tokens).')]
#[Signature('chatvia:prune:chatter-password-resets')]
class PruneChatterPasswordResets extends Command
{
    public function handle(PruneExpiredChatterPasswordResetTokensAction $prune): int
    {
        $deleted = $prune->execute();

        $this->info("Pruned {$deleted} expired chatter password reset token(s).");

        return self::SUCCESS;
    }
}
