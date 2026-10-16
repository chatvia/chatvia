<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Audit;

use App\Http\Requests\Api\V1\Audit\AuditLogRequest;
use App\Http\Resources\Api\V1\Audit\AuditEntryData;
use Dedoc\Scramble\Attributes\Group;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Models\AgentActivity;
use Support\Pagination\CursorPage;

/**
 * One agent's tool-call audit log (Governed MCP Gateway) over the public API,
 * gated by `agent.audit.view` — the same rows as the dashboard's per-agent
 * Audit page.
 */
#[Group(name: 'Audit log', weight: 150)]
class AgentAuditLogController
{
    /**
     * List agent audit log
     *
     * Every tool call the agent made — one immutable row per gateway decision
     * — newest first, optionally filtered by `outcome` and `connector`. The
     * list is paged with a cursor: to get the next page, send the
     * `next_cursor` of the response as `cursor`. On the last page
     * `next_cursor` is null.
     *
     * @return array{data: list<AuditEntryData>, next_cursor: string|null}
     */
    public function __invoke(AuditLogRequest $request, Agent $agent): array
    {
        $query = AgentActivity::query()
            ->forAgent($agent->id)
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
