<?php

namespace App\Http\Controllers\Agent;

use App\Http\Requests\Agent\Crud\RenameAgentRequest;
use Domain\Agent\Actions\Account\RenameAgentAction;
use Domain\Agent\Models\Agent;
use Illuminate\Http\RedirectResponse;

/**
 * Rename an agent (and re-slug its `slug` for knowledge_base agents).
 * Authorization lives on {@see RenameAgentRequest}; the rename itself in
 * {@see RenameAgentAction}.
 */
class RenameAgentController
{
    public function __invoke(RenameAgentRequest $request, Agent $agent, RenameAgentAction $rename): RedirectResponse
    {
        $rename->execute($agent, $request->toData());

        return back()->with('success', __('Agent updated successfully'));
    }
}
