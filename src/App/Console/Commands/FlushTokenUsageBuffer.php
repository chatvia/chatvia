<?php

namespace App\Console\Commands;

use Domain\Billing\Listeners\RecordLlmTokenUsage;
use Domain\Billing\Services\UserCreditSystem;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;

#[Description('Drain the Redis token-usage buffer into user_usages.tokens — one UPDATE per workspace per minute instead of one per LLM op.')]
#[Signature('app:flush-token-usage-buffer')]
class FlushTokenUsageBuffer extends Command
{
    public function handle(UserCreditSystem $creditSystem): int
    {
        $buffered = Redis::hgetall(RecordLlmTokenUsage::BUFFER_KEY);

        foreach ($buffered as $workspaceId => $tokens) {
            // hdel before writing: concurrent increments land in a fresh entry for the next flush.
            Redis::hdel(RecordLlmTokenUsage::BUFFER_KEY, (string) $workspaceId);

            $creditSystem->setUserId((int) $workspaceId)->recordTokenUsage((int) $tokens);
        }

        $this->info('Flushed token usage for '.count($buffered).' workspace(s).');

        return self::SUCCESS;
    }
}
