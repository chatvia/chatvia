<?php

namespace App\Http\Middleware\Access;

use App\Identity\Workspace\Models\User;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Symfony\Component\HttpFoundation\Response;

/**
 * Dashboard and API guard (`workspace.scope`): ties the request to the
 * signed-in user's workspace.
 *
 *  - Shares the workspace id as hidden Context, so the jobs this request
 *    queues carry it too.
 *  - Every record the route binds from the URL (an agent, a team, a
 *    function, a teammate…) must belong to that workspace. A record of
 *    another workspace answers 404, the same as an id that does not exist,
 *    so a guessed id never confirms that the record is real. Permission
 *    checks inside the workspace (403) come after.
 *
 * A record carries its workspace in `workspace_id` (null marks a built-in
 * record anyone may use); a teammate is checked through their workspace
 * membership. Records without either (a person's own data export, a passkey)
 * are left to their policies.
 */
class ScopeRequestToWorkspace
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User) {
            $workspaceId = $user->workspace()->id;

            Context::addHidden('workspace_id', $workspaceId);

            foreach ($request->route()?->parameters() ?? [] as $record) {
                if ($record instanceof Model) {
                    abort_unless($this->belongsTo($record, $workspaceId), 404);
                }
            }
        }

        return $next($request);
    }

    private function belongsTo(Model $record, int $workspaceId): bool
    {
        if ($record instanceof User) {
            return $record->workspaceMembership?->workspace_id === $workspaceId;
        }

        $owner = $record->getAttributes()['workspace_id'] ?? null;

        return $owner === null || (int) $owner === $workspaceId;
    }
}
