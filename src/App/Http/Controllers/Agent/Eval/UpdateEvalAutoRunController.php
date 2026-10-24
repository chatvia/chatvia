<?php

declare(strict_types=1);

namespace App\Http\Controllers\Agent\Eval;

use App\Http\Requests\Agent\Eval\UpdateEvalAutoRunRequest;
use Domain\Agent\Models\Agent;
use Illuminate\Http\RedirectResponse;

/**
 * Saves the agent's "Run evals after changes" switch (the `evals` settings
 * group). A single settings write, so no Action.
 */
class UpdateEvalAutoRunController
{
    public function __invoke(UpdateEvalAutoRunRequest $request, Agent $agent): RedirectResponse
    {
        $agent->setSetting('auto_run', $request->autoRun(), 'evals');

        return back();
    }
}
