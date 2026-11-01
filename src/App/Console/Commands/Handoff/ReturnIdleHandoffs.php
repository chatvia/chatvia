<?php

declare(strict_types=1);

namespace App\Console\Commands\Handoff;

use Domain\Conversation\Actions\Handoff\ReturnIdleHandoffsToAiAction;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Every-minute sweep that gives quiet live chats back to the AI (see
 * ReturnIdleHandoffsToAiAction).
 */
#[Description('Hand live chats back to the AI when nobody has written for the agent\'s idle time.')]
#[Signature('chatvia:handoff:return-idle')]
class ReturnIdleHandoffs extends Command
{
    public function handle(ReturnIdleHandoffsToAiAction $returnIdle): int
    {
        $returned = $returnIdle->execute();

        $this->info("Returned {$returned} live chat(s) to the AI.");

        return self::SUCCESS;
    }
}
