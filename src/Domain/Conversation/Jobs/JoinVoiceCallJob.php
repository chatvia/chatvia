<?php

declare(strict_types=1);

namespace Domain\Conversation\Jobs;

use Domain\Conversation\Actions\Voice\RunVoiceCallAction;
use Domain\Conversation\Actions\Voice\StartVoiceCallAction;
use Domain\Conversation\Data\Voice\VoiceCallData;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Queue;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;

/**
 * Sends a `voice` queue worker to join a live voice call. The worker stays
 * on the call until it ends, so the job lasts as long as the call. A call is
 * never retried: by then the visitor has hung up.
 *
 * The timeout is the longest call ({@see StartVoiceCallAction}, 25 minutes)
 * plus two minutes to wrap up — below `redis-long`'s retry_after (1900 s).
 * Each call still hangs up at its own time limit.
 */
#[Queue('voice')]
#[Tries(1)]
#[Timeout(1620)]
class JoinVoiceCallJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public VoiceCallData $call) {}

    public function handle(RunVoiceCallAction $run): void
    {
        $run->execute($this->call);
    }
}
