<?php

declare(strict_types=1);

namespace Domain\Agent\Jobs;

use Domain\Agent\Actions\Domain\VerifyAgentDomainAction;
use Domain\Agent\Models\AgentDomain;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\DeleteWhenMissingModels;
use Illuminate\Queue\Attributes\Queue;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;

/**
 * Checks one custom domain in the background ({@see VerifyAgentDomainAction}),
 * after it is saved and on the schedule.
 */
#[DeleteWhenMissingModels]
#[Queue('default')]
// One try: the schedule checks the domain again soon anyway.
#[Tries(1)]
// Two calls of up to eight seconds each, with room to spare.
#[Timeout(30)]
class VerifyAgentDomainJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly AgentDomain $domain) {}

    public function handle(VerifyAgentDomainAction $verify): void
    {
        $verify->execute($this->domain);
    }
}
