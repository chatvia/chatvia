<?php

declare(strict_types=1);

namespace App\Http\Controllers\Agent\Setup;

use App\Http\Requests\Agent\Crud\UpdateAgentLlmRequest;
use Domain\Agent\Actions\Settings\UpdateAgentLlmAction;
use Domain\Agent\Models\Agent;

/**
 * Sets the model an agent answers with and its generation settings. Gated by
 * `agent.permission:agent.setup`.
 */
class UpdateAgentLlmController
{
    public function __invoke(UpdateAgentLlmRequest $request, Agent $agent, UpdateAgentLlmAction $update): void
    {
        $update->execute($agent, $request->toData());
    }
}
