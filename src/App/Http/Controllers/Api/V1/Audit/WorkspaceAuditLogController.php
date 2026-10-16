<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Audit;

use App\Http\Requests\Api\V1\Audit\AuditLogRequest;
use App\Http\Resources\Api\V1\Audit\AuditEntryData;
use App\Identity\Workspace\Models\User;
use Dedoc\Scramble\Attributes\Group;
use Domain\Conversation\Models\AgentActivity;
use Support\Pagination\CursorPage;

/**
 * The workspace-wide tool-call audit log (Governed MCP Gateway) over the
 * public API, gated by the `account.governance` team permission — the same
 * rows as the dashboard's Settings → Governance page, across all agents.
 */
#[Group(name: 'Audit log', weight: 150)]
class WorkspaceAuditLogController
{
    /**
     * List workspace audit log
     *
     * Every tool call any of the workspace's agents made — one immutable row
     * per gateway decision — newest first, optionally filtered by `outcome`
     * and `connector`. The list is paged with a cursor: to get the next page,
     * send the `next_cursor` of the response as `cursor`. On the last page
     * `next_cursor` is null.
     *
     * @return array{data: list<AuditEntryData>, next_cursor: string|null}
     */
    public function __invoke(AuditLogRequest $request): array
    {
        /** @var User $user */
        $user = $request->user();

        $query = AgentActivity::query()
            ->forWorkspace($user->workspace()->id)
            ->with('agent:id,external_id');

        if ($request->outcome() !== null) {
            $query->where('outcome', $request->outcome());
        }
        if ($request->connector() !== null) {
            $query->where('connector_slug', $request->connector());
        }

        $page = CursorPage::newestFirst($query, $request->perPage(), $request->cursor());

        return [
            'data' => array_map(AuditEntryData::fromModel(...), $page->items()),
            'next_cursor' => $page->nextCursor()?->encode(),
        ];
    }
}
