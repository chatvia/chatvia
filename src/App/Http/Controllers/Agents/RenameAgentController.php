<?php

namespace App\Http\Controllers\Agents;

use App\Http\Requests\Agents\Crud\RenameAgentRequest;
use Domain\Agents\Actions\Account\RenameAgentAction;
use Domain\Agents\Models\Agent;
use Illuminate\Http\RedirectResponse;

/**
 * Rename an agent (and re-slug its `sub_domain` for knowledge_base agents).
 * Authorization lives on {@see RenameAgentRequest}; the rename itself in
 * {@see RenameAgentAction}.
 */
class RenameAgentController
{
    public function __invoke(RenameAgentRequest $request, Agent $agent, RenameAgentAction $rename): RedirectResponse
    {
        $rename->execute($agent, $request->toData());

        return back()->with('success', 'Agent updated successfully');
    }
}
