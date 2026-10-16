<?php

namespace App\Http\Controllers\Settings\Function;

use App\Http\Requests\Settings\Function\CreateAgentFunctionRequest;
use App\Http\Requests\Settings\Function\UpdateAgentFunctionRequest;
use App\Identity\Workspace\Models\User;
use Domain\Integration\Function\Actions\CreateAgentFunctionAction;
use Domain\Integration\Function\Actions\UpdateAgentFunctionAction;
use Domain\Integration\Function\Data\Resources\WorkspaceFunctionRowData;
use Domain\Integration\Function\Models\AgentFunction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The workspace webhook functions page (Settings → Functions) and its create,
 * update and delete endpoints. The public API offers the same through
 * `/api/v1/functions`, using the same Actions.
 */
class FunctionController
{
    public function edit(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        // Functions belong to the workspace owner; teammates see the same list.
        $functions = $user->workspace()->functions()
            ->withCount('agents')
            ->latest()
            ->get();

        return Inertia::render('workspace/integrations/Functions', [
            'functions' => WorkspaceFunctionRowData::collect($functions)->toArray(),
        ]);
    }

    public function store(CreateAgentFunctionRequest $request, CreateAgentFunctionAction $create): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $create->execute($user, $request->toData());

        return back();
    }

    public function update(UpdateAgentFunctionRequest $request, AgentFunction $function, UpdateAgentFunctionAction $update): RedirectResponse
    {
        $update->execute($function, $request->toData());

        return back();
    }

    public function destroy(AgentFunction $function): RedirectResponse
    {
        // Deleted through the model (not a mass delete) so the observer drops
        // the cached tool pool of every agent that used the function.
        $function->delete();

        return back();
    }
}
