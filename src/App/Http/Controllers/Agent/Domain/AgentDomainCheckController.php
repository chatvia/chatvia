<?php

declare(strict_types=1);

namespace App\Http\Controllers\Agent\Domain;

use Domain\Agent\Actions\Domain\VerifyAgentDomainAction;
use Domain\Agent\Models\Agent;
use Illuminate\Http\RedirectResponse;

/**
 * "Check again" on the Deploy page: checks the agent's custom domain now,
 * while the person waits, so the page shows the answer at once.
 */
class AgentDomainCheckController
{
    public function __invoke(Agent $agent, VerifyAgentDomainAction $verify): RedirectResponse
    {
        $domain = $agent->domain()->first();

        abort_if($domain === null, 404);

        $verify->execute($domain);

        return back();
    }
}
