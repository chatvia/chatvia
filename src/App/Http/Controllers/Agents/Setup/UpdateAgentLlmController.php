<?php

namespace App\Http\Controllers\Agents\Setup;

use App\Http\Requests\Agents\Crud\UpdateAgentLlmRequest;
use Domain\Agents\Actions\Settings\UpdateAgentLlmAction;
use Domain\Agents\Models\Agent;

class UpdateAgentLlmController
{
    public function __invoke(UpdateAgentLlmRequest $request, Agent $agent, UpdateAgentLlmAction $update): void
    {
        abort_if(! $request->user()->can('view', $agent), 404);

        $update->execute($agent, $request->toData());
    }
}
