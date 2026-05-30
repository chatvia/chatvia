<?php

namespace App\Http\Controllers\Agents\Templates;

use App\Auth\Models\User;
use App\Http\Controllers\Controller;
use Domain\Agents\Actions\AttachAgentTemplateAction;
use Domain\Agents\Actions\DetachAgentTemplateAction;
use Domain\Agents\Models\Agent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Attach/detach an account-level template to a single agent. Attaching flips
 * the agent to read-through (config resolved from the template at runtime);
 * detaching copies the template's current config onto the agent and clears
 * `agent_template_id`.
 */
class AgentTemplateController extends Controller
{
    public function store(Request $request, Agent $agent, AttachAgentTemplateAction $attach): RedirectResponse
    {
        abort_if($request->user()->cannot('view', $agent), 404);

        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'template_id' => ['required', 'string'],
        ]);

        $template = $user->templates()
            ->where('external_id', $validated['template_id'])
            ->firstOrFail();

        $attach->execute($agent, $template);

        return back();
    }

    public function destroy(Request $request, Agent $agent, DetachAgentTemplateAction $detach): RedirectResponse
    {
        abort_if($request->user()->cannot('view', $agent), 404);

        $detach->execute($agent);

        return back();
    }
}
