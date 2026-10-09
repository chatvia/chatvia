<?php

namespace App\Console\Commands;

use Domain\Billing\Actions\Credits\FlushTokenUsageBufferAction;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Scheduled every minute: moves buffered LLM token counts into the usage
 * table.
 */
#[Description('Drain the Redis token-usage buffer into user_usages.tokens — one UPDATE per workspace per minute instead of one per LLM op.')]
#[Signature('app:flush-token-usage-buffer')]
class FlushTokenUsageBuffer extends Command
{
    public function handle(FlushTokenUsageBufferAction $flush): int
    {
        $flushed = $flush->execute();

        $this->info("Flushed token usage for {$flushed} workspace(s).");

        return self::SUCCESS;
    }
}
