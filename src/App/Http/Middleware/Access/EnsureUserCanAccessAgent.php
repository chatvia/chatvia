<?php

namespace App\Http\Middleware\Access;

use App\Identity\Workspace\Models\User;
use Closure;
use Domain\Agent\Models\Agent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/**
 * Dashboard and API gate (`can.access.agent`): the signed-in user may open the
 * route-bound `{agent}`.
 *
 *  - An agent of another workspace does not exist for the caller: 404, so a
 *    guessed id never confirms that the agent is real.
 *  - Inside the workspace, the `access-agent` gate decides (the owner, and
 *    teammates whose role covers the agent); anyone else gets a 403.
 *  - Every other record the route binds must belong to that agent (its
 *    `agent_id`) or, for records the workspace shares between agents
 *    (sources, tools, topics, chatters), to the agent's workspace (its
 *    `workspace_id`; null marks a built-in record anyone may use). Anything
 *    else is a 404, so `/agents/{mine}/…/{theirs}` never reaches a record of
 *    another agent or workspace.
 *
 * Routes without an `{agent}` model pass through unchanged, so the alias can
 * sit on a whole route group.
 */
class EnsureUserCanAccessAgent
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $agent = $request->route('agent');

        if ($agent instanceof Agent) {
            $user = $request->user();

            abort_unless($user instanceof User && $agent->workspace_id === $user->workspace()->id, 404);

            Gate::authorize('access-agent', $agent);

            $this->ensureRecordsBelongTo($request, $agent);
        }

        return $next($request);
    }

    private function ensureRecordsBelongTo(Request $request, Agent $agent): void
    {
        foreach ($request->route()?->parameters() ?? [] as $record) {
            if ($record instanceof Model && ! $record instanceof Agent) {
                abort_unless($this->belongsTo($record, $agent), 404);
            }
        }
    }

    private function belongsTo(Model $record, Agent $agent): bool
    {
        $attributes = $record->getAttributes();

        if (isset($attributes['agent_id'])) {
            return (int) $attributes['agent_id'] === $agent->id;
        }

        if (isset($attributes['workspace_id'])) {
            return (int) $attributes['workspace_id'] === $agent->workspace_id;
        }

        return true;
    }
}
