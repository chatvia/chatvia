<?php

declare(strict_types=1);

namespace App\Http\Controllers\Agent\Domain;

use App\Http\Requests\Agent\Domain\UpdateAgentDomainRequest;
use Domain\Agent\Actions\Domain\SaveAgentDomainAction;
use Domain\Agent\Models\Agent;
use Illuminate\Http\RedirectResponse;

/**
 * The custom domain of an agent's Hosted Chat, on the Deploy page: save one
 * (it is checked right away) or remove it, after which the chat is only at
 * its APP_URL address again.
 */
class AgentDomainController
{
    public function update(UpdateAgentDomainRequest $request, Agent $agent, SaveAgentDomainAction $save): RedirectResponse
    {
        $save->execute($request->toData($agent));

        return back();
    }

    public function destroy(Agent $agent): RedirectResponse
    {
        $agent->domain()->first()?->delete();

        return back();
    }
}
