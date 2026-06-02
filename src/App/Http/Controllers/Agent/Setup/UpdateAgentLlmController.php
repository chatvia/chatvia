<?php

namespace App\Http\Controllers\Agent\Setup;

use App\Http\Requests\Agent\Crud\UpdateAgentLlmRequest;
use Domain\Agent\Actions\Settings\UpdateAgentLlmAction;
use Domain\Agent\Models\Agent;

class UpdateAgentLlmController
{
    public function __invoke(UpdateAgentLlmRequest $request, Agent $agent, UpdateAgentLlmAction $update): void
    {
        abort_if(! $request->user()->can('view', $agent), 404);

        $update->execute($agent, $request->toData());
    }
}
