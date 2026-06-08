<?php

namespace App\Http\Controllers\Agent\Eval;

use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Evaluation\Actions\RunEvalSuiteAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Triggers a full eval run for the agent. Gated by `agent.evals.manage` on the
 * route; the cases are scored asynchronously, so this returns immediately and
 * the new run appears as "running" on the Evals page.
 */
class RunAgentEvalController
{
    public function __construct(
        private readonly RunEvalSuiteAction $runEvalSuite,
    ) {}

    public function __invoke(Request $request, Agent $agent): RedirectResponse
    {
        abort_if($request->user()->cannot('view', $agent), 404);

        /** @var User $user */
        $user = $request->user();

        $this->runEvalSuite->execute($agent, $user);

        return to_route('agents.evals', $agent);
    }
}
